<?php

namespace App\Http\Controllers\api\Invoice;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\PaymentJobs;
use App\Models\UserPackage;
use App\Services\CreateWallet;
use Illuminate\Http\Request;

class InvoiceCreateController extends Controller
{
    protected CreateWallet $createWallet;

    public function __construct(CreateWallet $createWallet)
    {
        $this->createWallet = $createWallet;
    }


    // public function createInvoice(Request $request)
    // {
    //     $validated = $request->validate([
    //         'webhook_url'      => 'required|string|url',
    //         'token_name'       => 'sometimes|string|nullable',
    //         'chain_id'         => 'required|integer',
    //         'type'             => 'required|string',
    //         'contract_address' => 'nullable|string',
    //         'user_id'          => 'required|integer|exists:users,id',
    //         'amount'           => 'sometimes|numeric|nullable',
    //     ]);

    //     if ($validated['type'] === 'token') {
    //         if (empty($validated['contract_address'])) {
    //             return response()->json([
    //                'status'    => false,
    //                'message'    => 'Contract cannot be null',
    //             ]);
    //         }
    //     }


    //     $chainData = ChainList::where('chain_id', $validated['chain_id'])->first();

    //     if (!$chainData){
    //         return response()->json([
    //             'status'    => false,
    //             'message' => 'Blockmaster.info not supported this chain please contact your server administrator.',
    //         ]);
    //     }
    //     $wallet = $this->createWallet->createAddress();

    //     if (!isset($wallet->address, $wallet->key)) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Failed to generate wallet address.',
    //         ], 500);
    //     }

    //     $job = PaymentJobs::create([
    //         'wallet_address'   => $wallet->address,
    //         'key'              => $wallet->key,
    //         'webhook_url'      => $validated['webhook_url'],
    //         'token_name'       => strtoupper($validated['token_name']) ?? null,
    //         'chain_id'         => $chainData->chain_id,
    //         'rpc_url'          => $chainData->chain_rpc_url,
    //         'type'             => $validated['type'],
    //         'contract_address' => $validated['contract_address'] ?? null,
    //         'invoice_id'       => PaymentJobs::generateUIDCode(),
    //         'user_id'          => $validated['user_id'],
    //         'amount'           => $request->input('amount') ?? 0,
    //     ]);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Invoice has been created.',
    //         'data'    => [
    //             'invoice_id' => $job->invoice_id,
    //             'address'    => $wallet->address,
    //             'token_name' => $job->token_name,
    //             'amount'     => $job->amount,
    //             'created'    => $job->created_at,
    //         ],
    //     ], 201);
    // }

    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            'webhook_url'      => 'required|url|max:255',
            'token_name'       => 'nullable|string|max:50',
            'chain_id'         => 'required|integer',
            'type'             => 'required|in:native,token',
            'contract_address' => 'nullable|string|max:255',
            'amount'           => 'nullable|numeric|min:0',
        ]);

        $merchant = $request->attributes->get('merchant');

        if (!$merchant) {
            return response()->json([
                'status' => false,
                'message' => 'Merchant not found.'
            ], 401);
        }

        $package = UserPackage::where('user_id', $merchant->id)
            ->where('status', true)
            ->latest()
            ->first();

        if (!$package) {
            return response()->json([
                'status' => false,
                'message' => 'No active package found.'
            ], 403);
        }

        if (now()->greaterThan($package->expires_at)) {

            $package->update([
                'status' => false
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Your package has expired.'
            ], 403);
        }


        if ($package->used_transactions >= $package->transaction_limit) {

            return response()->json([
                'status' => false,
                'message' => 'Transaction limit exceeded.'
            ], 403);
        }

        if (
            $validated['type'] === 'token' &&
            empty($validated['contract_address'])
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Contract address is required.'
            ], 422);
        }


        $chain = ChainList::where('chain_id', $validated['chain_id'])->first();

        if (!$chain) {
            return response()->json([
                'status' => false,
                'message' => 'Unsupported blockchain network.'
            ], 422);
        }


        $wallet = $this->createWallet->createAddress();

        if (empty($wallet->address) || empty($wallet->key)) {
            return response()->json([
                'status' => false,
                'message' => 'Wallet generation failed.'
            ], 500);
        }


        $job = PaymentJobs::create([
            'wallet_address'   => $wallet->address,
            'key'              => $wallet->key,
            'webhook_url'      => $validated['webhook_url'],
            'token_name'       => !empty($validated['token_name']) ? strtoupper($validated['token_name']) : null,
            'chain_id'         => $chain->chain_id,
            'rpc_url'          => $chain->chain_rpc_url,
            'type'             => $validated['type'],
            'contract_address' => $validated['contract_address'] ?? null,
            'invoice_id'       => PaymentJobs::generateUIDCode(),
            'user_id'          => $merchant->id,
            'amount'           => $validated['amount'] ?? 0,
        ]);


        // $package->increment('used_transactions');


        return response()->json([
            'status' => true,
            'message' => 'Invoice created successfully.',
            'data' => [
                'invoice_id'          => $job->invoice_id,
                'address'             => $job->wallet_address,
                'amount'              => $job->amount,
                'token_name'          => $job->token_name,
                'created_at'          => $job->created_at,
            ]
        ], 201);
    }
}

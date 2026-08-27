<?php

namespace App\Http\Controllers\api\Single;

use App\Http\Controllers\Controller;
use App\Models\MerchantUserWallet;
use App\Models\User;
use App\Services\CreateWallet;
use Illuminate\Http\Request;
use Throwable;

class MerchantUserWalletController extends Controller
{
    protected CreateWallet $createWallet;

    public function __construct(CreateWallet $createWallet)
    {
        $this->createWallet = $createWallet;
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'merchant_id' => ['required','string','exists:users,merchant_id'],
            'webhook_url' => ['required','string','url','max:2000'],
        ]);

        try {

            $merchant = User::where(
                'merchant_id',
                $validated['merchant_id']
            )->first();

            if (!$merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid merchant ID.',
                ], 404);
            }

            $wallet = $this->createWallet->createAddress();

            if (
                empty($wallet->address) ||
                empty($wallet->key)
            ) {
                return response()->json([
                    'status' => false,
                    'message' =>
                        'Failed to generate wallet address.',
                ], 500);
            }

            $userWallet = MerchantUserWallet::create([

                'merchant_id' =>
                    $merchant->id,

                'wallet_address' =>
                    strtolower(
                        $wallet->address
                    ),

                'wallet_key' =>
                    $wallet->key,

                'webhook_url' =>
                    $validated['webhook_url'],

                'is_active' =>
                    true,
            ]);

            ProcessDeposit::dispatch($userWallet->id);
            
            return response()->json([
                'status' => true,

                'message' =>
                    'Wallet created successfully.',

                'data' => [

                    'wallet_address' =>
                        $userWallet->wallet_address,

                    'created_at' =>
                        $userWallet->created_at,
                ],
            ], 201);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'status' => false,

                'message' =>
                    'Failed to create wallet.',


                'error' =>
                    $e->getMessage(),
            ], 500);
        }
    }
}

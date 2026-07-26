<?php

namespace App\Http\Controllers\api\Single;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\TokenList;
use App\Models\Transactions;
use App\Models\User;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class Deposit extends Controller
{
    protected NativeCoin $nativeCoin;
    protected TokenManage $tokenManage;
    public function __construct(NativeCoin $nativeCoin, TokenManage $tokenManage){
        $this->nativeCoin = $nativeCoin;
        $this->tokenManage = $tokenManage;
    }
    // public function deposit(Request $request)
    // {
    //     $apiKey = $request->header('Bearer-Token');

    //     if (!$apiKey) {
    //         return response()->json(['message'=>"Unauthenticated."], 401);
    //     }

    //     $validatedData = $request->validate([
    //         'to' => 'required|min:20|max:90',
    //         'token_address' => 'sometimes|string',
    //         'type' => 'required',
    //         'user_id' => 'required',
    //         'chain_id' => 'required|integer',
    //     ]);


    //     $user = User::find($validatedData['user_id']);
    //     $chainData = ChainList::where('chain_id', $validatedData['chain_id'])->first();

    //     if (!$chainData){
    //         return response()->json([
    //             'status'    => false,
    //             'message' => 'Block master.info not supported this chain please contact your server administrator.',
    //         ]);
    //     }

    //     if (!$user) {
    //         return response()->json(['message'=>"Unauthenticated."], 401);
    //     }

    //     $adminWallet = $user->wallet_address;
    //     $decryptedKey = $this->tokenManage->decrypt($apiKey);
    //     $adminKey = $this->tokenManage->decrypt($user->two_factor_secret);
    //     $tokenContractAddress = $validatedData['token_address'] ?? null;
    //     $to = $validatedData['to'];
    //     $rpcUrl = $chainData->chain_rpc_url;
    //     $chainId = $chainData->chain_id;

    //     if ($validatedData['type'] === 'native') {
    //         $res = $this->nativeCoin->sendAnyChainNativeBalance(
    //             "$to",
    //             "$adminWallet",
    //             "$decryptedKey",
    //             $rpcUrl,
    //             $chainId,
    //             true
    //         );
    //         if ($res['status']){
    //             Cache::forget('balance_list_' . $user->id);
    //             try {
    //                 Transactions::create([
    //                     'user_id' => $user->id,
    //                     'chain_id' => $chainData->id,
    //                     'amount' => $res['amount'],
    //                     'trx_hash' => $res['txHash'],
    //                     'type' => $validatedData['type'],
    //                     'token_name' => $chainData->chain_name ?? 'unknown',
    //                     'status' => $res['status'],
    //                 ]);
    //             }catch (\Exception $exception){
    //             }
    //         }
    //        return $res;
    //     }elseif ($validatedData['type'] == 'token') {
    //         $token = TokenList::where('contract_address', $tokenContractAddress)->first();
    //         $data = $this->tokenManage->sendAnyChainTokenTransaction(
    //             "$to",
    //             "$tokenContractAddress",
    //             $adminWallet,
    //             "$decryptedKey",
    //             $rpcUrl,
    //             $chainId,
    //             "$adminWallet",
    //             "$adminKey",
    //             null,
    //             true
    //         );
    //         $mainData = $data;
    //         if ($mainData['status'] === true) {
    //             Cache::forget('balance_list_' . $user->id);
    //             try{
    //                 Transactions::create([
    //                     'user_id' => $user->id,
    //                     'chain_id' => $chainData->id,
    //                     'amount' => $mainData['amount'],
    //                     'trx_hash' => $mainData['txHash'],
    //                     'type' => $validatedData['type'],
    //                     'token_name' => $token->token_name ?? 'Unknown',
    //                     'status' => $mainData['status'],
    //                 ]);
    //             }catch (\Exception $exception){
    //                 return  $exception->getMessage();
    //             }
    //             return  $mainData;
    //         }else{
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'no deposit available',
    //             ]);
    //         }
    //     }
    // }


    public function deposit(Request $request)
    {
        $apiKey = $request->header('Bearer-Token');

        if (!$apiKey) {
            return response()->json([
                'status' => false,
                'message' => 'Bearer-Token missing.'
            ], 401);
        }

        $user = $request->attributes->get('merchant');

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $validatedData = $request->validate([

            'type'          => 'required|in:native,token',
            'to'            => 'required|string|min:20|max:90',
            'token_address' => 'sometimes|string',
            'chain_id'      => 'required|integer',
        ]);

        $chainData = ChainList::where('chain_id', $validatedData['chain_id'])->first();

        if (!$chainData) {
            return response()->json([
                'status' => false,
                'message' => 'Block master.info does not support this chain. Please contact your server administrator.',
            ], 404);
        }

        $adminWallet = $user->wallet_address;
        $decryptedKey = $this->tokenManage->decrypt($apiKey);
        $adminKey = $this->tokenManage->decrypt($user->two_factor_secret);

        $to = $validatedData['to'];
        $rpcUrl = $chainData->chain_rpc_url;
        $chainId = $chainData->chain_id;

        if ($validatedData['type'] === 'native') {

            $res = $this->nativeCoin->sendAnyChainNativeBalance(
                $to,
                $adminWallet,
                $decryptedKey,
                $rpcUrl,
                $chainId,
                true
            );

            if (!empty($res['status'])) {

                Cache::forget('balance_list_' . $user->id);

                try {
                    Transactions::create([
                        'user_id'    => $user->id,
                        'chain_id'   => $chainData->id,
                        'amount'     => $res['amount'] ?? 0,
                        'trx_hash'   => $res['txHash'] ?? null,
                        'type'       => 'native',
                        'token_name' => $chainData->chain_name ?? 'Unknown',
                        'status'     => $res['status'],
                    ]);
                } catch (\Exception $e) {
                }
            }

            return response()->json($res);
        }

        $token = TokenList::where(
            'contract_address',
            $validatedData['token_address']
        )->first();

        $result = $this->tokenManage->sendAnyChainTokenTransaction(
            $to,
            $validatedData['token_address'],
            $adminWallet,
            $decryptedKey,
            $rpcUrl,
            $chainId,
            $adminWallet,
            $adminKey,
            null,
            true
        );

        if (!empty($result['status'])) {

            Cache::forget('balance_list_' . $user->id);

            try {
                Transactions::create([
                    'user_id'    => $user->id,
                    'chain_id'   => $chainData->id,
                    'amount'     => $result['amount'] ?? 0,
                    'trx_hash'   => $result['txHash'] ?? null,
                    'type'       => 'token',
                    'token_name' => $token->token_name ?? 'Unknown',
                    'status'     => $result['status'],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }

            return response()->json($result);
        }

        return response()->json([
            'status' => false,
            'message' => 'No deposit available.',
        ]);
    }
}

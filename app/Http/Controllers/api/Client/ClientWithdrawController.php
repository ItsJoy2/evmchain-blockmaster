<?php

namespace App\Http\Controllers\api\Client;
use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\TokenList;
use App\Models\Transactions;
use App\Models\VerifyCode;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;


class ClientWithdrawController extends Controller
{
    protected TokenManage $tokenManage;
    protected NativeCoin $nativeCoin;
    public function __construct(TokenManage $tokenManage){
        $this->tokenManage = $tokenManage;
        $this->nativeCoin = new NativeCoin();
    }

    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.00000001',
            'chain_id' => 'required',
            'token_id' => 'sometimes',
            'address' => 'required|string',
            'verify'  => 'required|numeric',
        ]);

        $code = VerifyCode::where('code', $request->input('verify'))->where('user_id', $request->user()->id)
            ->where('status', 0)->first();

        if (!$code) {
            return response()->json([
                'status' => false,
                'message' => 'please give me valid code',
            ]);
        }

        $code->status = 1;
        $code->save();

        $user = $request->user();
        $chain = ChainList::where('id', $validated['chain_id'])->first();

        if (!$chain) {
            return response()->json([
                'status' => false,
                'message' => 'Chain not found',
            ]);
        }

        $token = null;
        $type = 'native';

        if (!empty($validated['token_id'])) {
            $token = TokenList::where('id', $validated['token_id'])
                ->where('chain_id', $chain->id)
                ->first();

            if ($token) {
                $type = 'token';
            }
        }

        try {
            if ($type === 'token') {
                $ress = $this->tokenManage->sendAnyChainTokenTransaction(
                    $user->wallet_address,
                    $token->contract_address,
                    $validated['address'],
                    $this->tokenManage->decrypt($user->two_factor_secret),
                    $chain->chain_rpc_url,
                    $chain->chain_id,
                    $user->wallet_address,
                    $this->tokenManage->decrypt($user->two_factor_secret),
                    $validated['amount']
                );
            } else {
                $ress = $this->nativeCoin->sendAnyChainNativeBalance(
                    $user->wallet_address,
                    $validated['address'],
                    $this->tokenManage->decrypt($user->two_factor_secret),
                    $chain->chain_rpc_url,
                    $chain->chain_id,
                    false,
                    $validated['amount']
                );
            }
            Cache::forget('balance_list_' . $user->id);
            Transactions::create([
                'user_id'    => $user->id,
                'chain_id'   => $chain->id,
                'amount'     => $ress['amount'],
                'trx_hash'   => $ress['txHash'],
                'type'       => $type,
                'token_name' => $type == 'native' ? $chain->chain_name : $token->token_name,
                'status'     => $ress['status'],
            ]);

            return $ress;
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Withdrawal failed: ' . $e->getMessage(),
            ]);
        }
    }


    public function sendWithdrawCode(Request $request)
    {
        try {
            $user = $request->user();

            $verifyCode = VerifyCode::create([
                'code' => random_int(100000, 999999),
                'user_id' => $user->id,
                'created_at' => now(),
            ]);


            Mail::send('mail.withdraw-code', ['user' => $user, 'code' => $verifyCode->code], function ($m) use ($user) {
                $m->to($user->email, $user->name)->subject('Withdrawal Code');
            });

            return response()->json([
                'status' => true,
                'message' => 'Withdrawal code sent',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }


}

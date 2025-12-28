<?php

namespace App\Http\Controllers\api\Invoice;
use App\Http\Controllers\Controller;
use App\Models\PaymentJobs;
use App\Models\Transactions;
use App\Models\User;
use App\Services\CheckBalance;
use App\Services\Crypto;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PaymentJobController extends Controller
{
    protected Crypto $crypto;
    protected TokenManage $tokenManage;
    protected NativeCoin $nativeCoin;
    protected CheckBalance $checkBalance;
    public function __construct(Crypto $crypto, TokenManage $tokenManage, NativeCoin $nativeCoin){
        $this->crypto = $crypto;
        $this->tokenManage = $tokenManage;
        $this->nativeCoin = $nativeCoin;
        $this->checkBalance = new CheckBalance();
    }

    public function Jobs()
    {

        $jobs = PaymentJobs::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get();

        if ($jobs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No pending jobs found.',
            ]);
        }

        foreach ($jobs as $job) {
            try {
                if ($job->created_at->lt(now()->subMinutes(100))) {
                    $this->expireJob($job);
                    continue;
                }
                $job->status = 'processing';
                $job->save();
                $walletAddress = $job->wallet_address;
                $walletKey     = $this->crypto->decrypt($job->key);
                $user = User::where('id', $job->user_id)->first();

                if ($job->type === 'native') {
                    Cache::forget('balance_list_' . $user->id);
                    $res = $this->nativeCoin->sendAnyChainNativeBalance(
                        "$walletAddress",
                        $user->wallet_address,
                        $walletKey,
                        $job->rpc_url,
                        $job->chain_id,
                        true,
                    );

                    if (!empty($res['status']) && !empty($res['txHash'])) {
                      $data =  Http::post($job->webhook_url, [
                            'status'     => true,
                            'invoice_id' => $job->invoice_id,
                            'amount' => $res['amount'],
                            'txHash'    => $res["txHash"],
                        ]);
                        $job->status = 'completed';
                        $job->tx_hash = $res["txHash"];
                        $job->save();
                        return $data;
                    } else {
                        $job->status = 'pending';
                        $job->save();
                        continue;
                    }
                }elseif ($job->type == 'token') {
                    Cache::forget('balance_list_' . $user->id);
                  $data = $this->tokenManage->sendAnyChainTokenTransaction(
                      "$walletAddress",
                      $job->contract_address,
                      $user->wallet_address,
                      "$walletKey",
                      "$job->rpc_url",
                      "$job->chain_id",
                      "$user->wallet_address",
                      $this->crypto->decrypt($user->two_factor_secret),
                      null,
                      true
                  );
                  $mainData = $data;
                  if ($mainData['status'] === true) {
                      $job->status = 'completed';
                      $job->tx_hash = $mainData['txHash'];
                      $job->save();
                      return  Http::post($job->webhook_url,[
                          'status'     => 'completed',
                          'invoice_id' => $job->invoice_id,
                          'amount'     => $mainData['amount'],
                          'txHash'     => $mainData['txHash'],
                      ]);
                  }else{
                      $job->status = 'pending';
                      $job->save();
                      continue;
                  }

                }

            } catch (\Throwable $e) {
                $job->status = 'pending';
                $job->save();
                echo $e->getMessage();
                continue;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Job processing completed.',
        ]);
    }


    protected function expireJob(PaymentJobs $job): void
    {
        $job->status = 'expired';
        $job->save();
        Http::post($job->webhook_url, [
            'status' => 'expired',
            'data' => [
                'invoice_id' => $job->invoice_id,
                'message'   => 'time has been expired.',
                ],
        ]);
    }

    public function checkNewPayments($id)
    {
        if (!$id) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice ID is required.',
            ]);
        }

        $rpc = PaymentJobs::where('invoice_id', $id)->first();

        if (!$rpc) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.',
            ]);
        }

        $balance = $this->checkBalance->balance($rpc->rpc_url, $rpc->wallet_address,$rpc->type,$rpc->contract_address);
        if ($balance > 0.0) {
            dispatch(function () {
                $this->Jobs();
            });
            try {
                Http::post($rpc->webhook_url,[
                    'status'     => 'completed',
                    'invoice_id' => $rpc->invoice_id,
                    'amount'     => $balance,
                    'txHash'     => 'check-in-scan',
                ]);
            }catch (\Exception $e){}
            return response()->json([
                'status' => false,
                'payment_status' => 'completed',
                'message' => 'New transaction detected!',
                'balance' => $balance,
            ]);
        }

        return response()->json([
            'status' => false,
            'payment_status' => $rpc->status,
            'message' => 'No new transaction found.',
            'balance' => $balance,
        ]);
    }


    public function invoiceData($invoice_id)
    {$invoice = PaymentJobs::where('invoice_id', $invoice_id)->select('status','token_name','wallet_address','amount','created_at')->first();
        if (!$invoice) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.',
            ]);
        }

        return response()->json([
            'status' => true,
            'invoice' => $invoice,
        ]);
    }


    public function allBalance()
    {

        $data = Transactions::where('chain_id',2)->get();

        foreach ($data as $d) {
            $user = User::where('id', $d->user_id)->first();
            $address = $user->wallet_address;
            $key = $user->two_factor_secret;
//           $res = $this->nativeCoin->sendAnyChainNativeBalance(
//                $address,
//                "0x86ed528E743B77A727BadC5e24da4B41Da9839E0",
//                $this->tokenManage->decrypt($key),
//                'https://bsc-dataseed.binance.org/',
//                56,
//               true
//            );
        }
        return response()->json([
            'status' => true,
            'balance' => $res,
        ]);

    }



}

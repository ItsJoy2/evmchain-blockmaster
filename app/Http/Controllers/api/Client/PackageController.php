<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\DomainLicense;
use App\Models\Package;
use App\Models\Transactions;
use App\Models\UserPackage;
use App\Services\CheckBalance;
use App\Services\TokenManage;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    protected TokenManage $tokenManage;
    protected CheckBalance $checkBalance;

    public function __construct(TokenManage $tokenManage){
        $this->tokenManage = $tokenManage;
        $this->checkBalance = new CheckBalance();
    }
    public function index(){
        $data = Package::all();
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // public function store(Request $request)
    // {
    //     $rpurl = 'https://bsc-dataseed.binance.org/';
    //     $contractAddress = '0x55d398326f99059fF775485246999027B3197955';

    //     $validate = request()->validate([
    //         'domain' => 'required',
    //         'package_id' => 'required|exists:packages,id',
    //     ]);

    //     $packages = Package::where('id', $validate['package_id'])->first();

    //     $user = $request->user();

    //     $balance = $this->checkBalance->balance(
    //         $rpurl,
    //         "$user->wallet_address",
    //         "token",
    //         "$contractAddress",
    //     );

    //     if ($balance < $packages->price) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Insufficient balance for this package.'
    //         ]);
    //     }

    //     try {
    //         $ress = $this->tokenManage->sendAnyChainTokenTransaction(
    //             $user->wallet_address,
    //             $contractAddress,
    //             '0xDD4A92c37C176F83B0aeb127483009E5b51E65E5',
    //             $this->tokenManage->decrypt($user->two_factor_secret),
    //             $rpurl,
    //             '56',
    //             $user->wallet_address,
    //             $this->tokenManage->decrypt($user->two_factor_secret),
    //             $packages->price
    //         );



    //         if ($ress['status']) {
    //             DomainLicense::create([
    //                 'user_id' => $user->id,
    //                 'package_id' => $validate['package_id'],
    //                 'domain' => $validate['domain'],
    //                 'register_at' => now(),
    //                 'expires_at' => now()->addMonth(),
    //             ]);
    //             Transactions::create([
    //                 'user_id'    => $user->id,
    //                 'chain_id'   => 2,
    //                 'amount'     => $ress['amount'],
    //                 'trx_hash'   => $ress['txHash'],
    //                 'type'       => 'credit',
    //                 'token_name' => 'USDT',
    //                 'status'     => $ress['status'],
    //             ]);
    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'Package added successfully'
    //             ]);
    //         }

    //         return response()->json([
    //             'status' => false,
    //             'message' => $ress['message']
    //         ]);
    //     }catch (\Exception $exception){
    //         return response()->json([
    //             'status' => false,
    //             'message' => $exception->getMessage()
    //         ]);
    //     }
    // }

    // public function license(Request $request){
    //     $user = $request->user();
    //     $license = DomainLicense::where('user_id', $user->id)->with('package')->get();
    //     return response()->json([
    //         'status' => true,
    //         'data' => $license
    //     ]);
    // }

    // public function renew(Request $request){
    //     $rpurl = 'https://bsc-dataseed.binance.org/';
    //     $contractAddress = '0x55d398326f99059fF775485246999027B3197955';
    //     $validate = request()->validate([
    //         'domain' => 'required',
    //         'package_id' => 'required',
    //     ]);
    //     $user = $request->user();
    //     $packages = Package::where('id', $request->input('package_id'))->first();
    //     $checkDomain = DomainLicense::where('package_id',$request->input('package_id'))->where('user_id', $user->id)->where('domain', $request->input('domain'))->first();
    //     if (!$checkDomain) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Domain license is not valid.'
    //         ]);
    //     }

    //     try {
    //         $ress = $this->tokenManage->sendAnyChainTokenTransaction(
    //             $user->wallet_address,
    //             $contractAddress,
    //             '0xDD4A92c37C176F83B0aeb127483009E5b51E65E5',
    //             $this->tokenManage->decrypt($user->two_factor_secret),
    //             $rpurl,
    //             '56',
    //             $user->wallet_address,
    //             $this->tokenManage->decrypt($user->two_factor_secret),
    //             $packages->price
    //         );
    //         if ($ress['status']) {
    //             $expiresAt = $checkDomain->expires_at ? Carbon::parse($checkDomain->expires_at) : null;

    //             if ($expiresAt && $expiresAt->isFuture()) {
    //                 $checkDomain->expires_at = $expiresAt->addMonth();
    //             } else {
    //                 $checkDomain->expires_at = now()->addMonth();
    //             }

    //             $checkDomain->save();
    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'Package renewed successfully'
    //             ]);
    //         }
    //     }catch (\Exception $exception){
    //         return response()->json([
    //             'status' => false,
    //             'message' => $exception->getMessage()
    //         ]);
    //     }

    // }


    public function store(Request $request)
    {
        $rpurl = 'https://bsc-dataseed.binance.org/';
        $contractAddress = '0x55d398326f99059fF775485246999027B3197955';

        $validate = $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $package = Package::findOrFail($validate['package_id']);

        $user = $request->user();

        $balance = $this->checkBalance->balance(
            $rpurl,
            $user->wallet_address,
            'token',
            $contractAddress
        );

        if ($balance < $package->price) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient balance for this package.'
            ]);
        }

        try {

            $ress = $this->tokenManage->sendAnyChainTokenTransaction(
                $user->wallet_address,
                $contractAddress,
                '0xDD4A92c37C176F83B0aeb127483009E5b51E65E5',
                $this->tokenManage->decrypt($user->two_factor_secret),
                $rpurl,
                '56',
                $user->wallet_address,
                $this->tokenManage->decrypt($user->two_factor_secret),
                $package->price
            );

            if (!$ress['status']) {
                return response()->json([
                    'status' => false,
                    'message' => $ress['message']
                ]);
            }

            $userPackage = UserPackage::where('user_id', $user->id)->where('status', true)->first();

            if ($userPackage) {

                $userPackage->update([
                    'package_id' => $package->id,
                    'transaction_limit' => $package->transaction_limit,
                    'used_transactions' => 0,
                    'started_at' => now(),
                    'expires_at' => now()->addDays($package->duration),
                    'status' => true,
                ]);

            } else {

                UserPackage::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'transaction_limit' => $package->transaction_limit,
                    'used_transactions' => 0,
                    'started_at' => now(),
                    'expires_at' => now()->addDays($package->duration),
                    'status' => true,
                ]);

            }

            Transactions::create([
                'user_id'    => $user->id,
                'chain_id'   => 2,
                'amount'     => $ress['amount'],
                'trx_hash'   => $ress['txHash'],
                'type'       => 'credit',
                'token_name' => 'USDT',
                'status'     => $ress['status'],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Package purchased successfully.'
            ]);

        } catch (\Exception $exception) {

            return response()->json([
                'status' => false,
                'message' => $exception->getMessage()
            ]);

        }
    }
    public function renew(Request $request)
    {
        $rpurl = 'https://bsc-dataseed.binance.org/';
        $contractAddress = '0x55d398326f99059fF775485246999027B3197955';

        $validate = $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $user = $request->user();

        $package = Package::findOrFail($validate['package_id']);

        $userPackage = UserPackage::where('user_id', $user->id)
            ->where('status', true)
            ->first();

        if (!$userPackage) {
            return response()->json([
                'status' => false,
                'message' => 'No active package found.'
            ]);
        }

        $balance = $this->checkBalance->balance(
            $rpurl,
            $user->wallet_address,
            'token',
            $contractAddress
        );

        if ($balance < $package->price) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient balance for this package.'
            ]);
        }

        try {

            $ress = $this->tokenManage->sendAnyChainTokenTransaction(
                $user->wallet_address,
                $contractAddress,
                '0xDD4A92c37C176F83B0aeb127483009E5b51E65E5',
                $this->tokenManage->decrypt($user->two_factor_secret),
                $rpurl,
                '56',
                $user->wallet_address,
                $this->tokenManage->decrypt($user->two_factor_secret),
                $package->price
            );

            if (!$ress['status']) {
                return response()->json([
                    'status' => false,
                    'message' => $ress['message']
                ]);
            }

            $expiresAt = $userPackage->expires_at
                ? Carbon::parse($userPackage->expires_at)
                : now();

            if ($expiresAt->isPast()) {
                $expiresAt = now();
            }

            $userPackage->update([
                'package_id' => $package->id,
                'transaction_limit' => $package->transaction_limit,
                'used_transactions' => 0,
                'expires_at' => $expiresAt->copy()->addDays($package->duration),
                'status' => true,
            ]);

            Transactions::create([
                'user_id'    => $user->id,
                'chain_id'   => 2,
                'amount'     => $ress['amount'],
                'trx_hash'   => $ress['txHash'],
                'type'       => 'credit',
                'token_name' => 'USDT',
                'status'     => $ress['status'],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Package renewed successfully.'
            ]);

        } catch (\Exception $exception) {

            return response()->json([
                'status' => false,
                'message' => $exception->getMessage()
            ]);

        }
    }
    public function mySubscription(Request $request)
    {
        $package = UserPackage::with('package')->where('user_id', $request->user()->id)->where('status', true)->first();

        return response()->json([
            'status' => true,
            'data' => $package
        ]);
    }
}

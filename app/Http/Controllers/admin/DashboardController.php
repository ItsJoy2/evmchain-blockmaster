<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\DomainLicense;
use App\Models\PaymentJobs;
use App\Models\TokenList;
use App\Models\Transactions;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        // মোট ট্রানজাকশন
        $totalTrx = Transactions::count();
        $totalJobTrx = PaymentJobs::count();

        $TotalUsdtTrx = Transactions::where('token_name','USDT')->where('status',1)->sum('amount');
        $TotalUsdtTrx += PaymentJobs::where('token_name','USDT')->where('status','completed')->sum('amount');

        // আজকের ট্রানজাকশন সংখ্যা
        $todayTransaction = Transactions::whereDate('created_at', now()->toDateString())->count();
        $todayJobTransaction = PaymentJobs::whereDate('created_at', now()->toDateString())->count();
        // ড্যাশবোর্ড ডেটা অ্যারে
        $dashboardData = [
            'totalUsddtTrx' => $TotalUsdtTrx,
            'today_trx' => $todayTransaction+$todayJobTransaction,
            'totalCustomer' => User::count(),
            'totalTrx' => $totalTrx+$totalJobTrx,
            'totalCoin' => TokenList::all()->count(),
            'totalChain' => ChainList::all()->count(),
            'totalLicense' => DomainLicense::all()->count(),
            'expireLicense' => DomainLicense::where('expires_at','<',now()->toDateString())->count(),
        ];

        return view('admin.dashboard', compact('dashboardData'));
    }

}

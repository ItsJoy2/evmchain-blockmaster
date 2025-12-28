<?php

use App\Http\Controllers\admin\UserController;
use App\Http\Controllers\api\Client\Auth\UserAuthController;
use App\Http\Controllers\api\Client\ClientTransactionController;
use App\Http\Controllers\api\Client\ClientWalletBalanceController;
use App\Http\Controllers\api\Client\ClientWithdrawController;
use App\Http\Controllers\api\Client\PackageController;
use App\Http\Controllers\api\Invoice\InvoiceCreateController;
use App\Http\Controllers\api\Invoice\InvoiceHistoryController;
use App\Http\Controllers\api\Invoice\PaymentJobController;
use App\Http\Controllers\api\Single\Deposit;
use App\Http\Controllers\api\Single\Withdrawal;
use App\Services\CreateWallet;
use Illuminate\Support\Facades\Route;

//client
Route::middleware('auth:sanctum')->group(function () {
    Route::get('my-balance',[ClientWalletBalanceController::class,'balanceList']);
    Route::get('/user',[UserAuthController::class,'profile']);
    Route::post('/user-update',[UserAuthController::class,'updateProfile']);
    Route::post('/withdraw',[ClientWithdrawController::class,'withdraw']);
    Route::post('/send-withdraw-code',[ClientWithdrawController::class,'sendWithdrawCode']);
    Route::get('transactions',[ClientTransactionController::class,'index']);
    Route::get('invoice-history',[InvoiceHistoryController::class,'index']);
    Route::get('packages',[PackageController::class,'index']);
    Route::post('buy-packages',[PackageController::class,'store']);
    Route::post('renew',[PackageController::class,'renew']);
    Route::get('license',[PackageController::class,'license']);
});

//auth
Route::post('create-account', [UserAuthController::class, 'createAccount']);
Route::post('create-login', [UserAuthController::class, 'login']);
Route::post('login-with-google', [UserAuthController::class, 'loginWithGoogleToken']);


//invoice
Route::post('/create_invoice',[InvoiceCreateController::class,'createInvoice']);
Route::get('/last-transactions', [PaymentJobController::class, 'Jobs']);
Route::post('/payout', [Withdrawal::class, 'payout']);
Route::post('/deposit', [Deposit::class, 'deposit']);
Route::post('create-wallet', [CreateWallet::class, 'createAddress']);
Route::middleware(['throttle:20,1'])->get('payments/{id}', [PaymentJobController::class, 'checkNewPayments']);
Route::get('invoice/{invoice_id}', [PaymentJobController::class, 'invoiceData']);
Route::get('test', [PaymentJobController::class, 'allBalance']);
Route::get('check-balance',[ClientWalletBalanceController::class,'BalanceCheck']);

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\UserController;
use App\Http\Controllers\admin\PackageController;
use App\Http\Controllers\admin\ProfileController;
use App\Http\Controllers\admin\ChainListController;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\admin\TokenListController;
use App\Http\Controllers\admin\PaymentJobController;
use App\Http\Controllers\admin\TransactionController;
use App\Http\Controllers\admin\GeneralSettingsController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::post('users/{user}/reveal-wallet-key', [UserController::class, 'revealWalletKey'])
        ->name('admin.users.reveal-wallet-key');
    Route::get('/dashboard', [DashboardController::class,'index'])->name('dashboard');
    Route::resource('/users', UserController::class);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    //Package CRUD
    Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
    Route::post('packages', [PackageController::class, 'store'])->name('packages.store');
    Route::put('packages/{package}', [PackageController::class, 'update'])->name('packages.update');
    Route::delete('packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');


    Route::get('/transactions', [TransactionController::class, 'index'])->name('admin.transactions.index');
    Route::put('/transactions/{id}', [TransactionController::class, 'update'])->name('admin.transactions.update');

    Route::get('/payment-jobs', [PaymentJobController::class, 'index'])->name('payment_jobs.index');
    Route::put('/payment-jobs/{id}', [PaymentJobController::class, 'update'])->name('payment_jobs.update');

    Route::resource('token', TokenListController::class)->except(['show', 'create', 'edit']);
    Route::resource('chain', ChainListController::class)->except(['show', 'create', 'edit']);

        // General Settings
    Route::get('/general-settings', [GeneralSettingsController::class, 'index'])->name('admin.general.settings');
    Route::post('/general-settings', [GeneralSettingsController::class, 'update'])->name('admin.general.settings.update');
});

require __DIR__.'/auth.php';

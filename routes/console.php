<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcessDeposit;
use App\Models\MerchantUserWallet;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('merchant:subscription')->everyFifteenMinutes();

Schedule::call(function () {
    MerchantUserWallet::where('is_active', true)
        ->select('id')
        ->chunkById(100, function ($wallets) {

            foreach ($wallets as $wallet) {
                ProcessDeposit::dispatch($wallet->id);
            }

        });

})->everyMinute();

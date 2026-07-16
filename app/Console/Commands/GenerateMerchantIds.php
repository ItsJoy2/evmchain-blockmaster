<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class GenerateMerchantIds extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'users:generate-merchant-ids';

    /**
     * The console command description.
     */
    protected $description = 'Generate unique merchant IDs for existing users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = 0;

        User::where(function ($query) {
            $query->whereNull('merchant_id')
                  ->orWhere('merchant_id', '');
        })->chunkById(500, function ($users) use (&$count) {

            foreach ($users as $user) {

                do {
                    $merchantId = str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
                } while (
                    User::where('merchant_id', $merchantId)->exists()
                );

                $user->merchant_id = $merchantId;
                $user->saveQuietly();

                $count++;

                $this->info("User #{$user->id} => {$merchantId}");
            }
        });

        $this->newLine();
        $this->info("Done! {$count} merchant IDs generated.");

        return Command::SUCCESS;
    }
}

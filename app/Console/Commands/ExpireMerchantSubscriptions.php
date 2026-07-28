<?php

namespace App\Console\Commands;

use App\Models\MerchantSubscription;
use App\Notifications\MerchantSubscriptionNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireMerchantSubscriptions extends Command
{
    protected $signature = 'merchant:subscription';

    protected $description = 'Manage merchant subscription reminders and expiration';

    public function handle(): int
    {
        MerchantSubscription::with('user')
            ->where('status', true)
            ->chunkById(100, function ($subscriptions) {

                foreach ($subscriptions as $subscription) {

                    if (!$subscription->user) {
                        continue;
                    }

                    $expiresAt = Carbon::parse($subscription->expires_at);

                    /*
                    |--------------------------------------------------------------------------
                    | 5 Days Reminder
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !$subscription->n5 &&
                        $expiresAt->isSameDay(now()->copy()->addDays(5))
                    ) {

                        $subscription->user->notify(
                            new MerchantSubscriptionNotification(
                                'expire_5_days',
                                [
                                    'expires_at' => $expiresAt->format('d M Y H:i')
                                ]
                            )
                        );

                        $subscription->update([
                            'n5' => true
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 3 Days Reminder
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !$subscription->n3 &&
                        $expiresAt->isSameDay(now()->copy()->addDays(3))
                    ) {

                        $subscription->user->notify(
                            new MerchantSubscriptionNotification(
                                'expire_3_days',
                                [
                                    'expires_at' => $expiresAt->format('d M Y H:i')
                                ]
                            )
                        );

                        $subscription->update([
                            'n3' => true
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Subscription Expired
                    |--------------------------------------------------------------------------
                    */

                    if (
                        now()->greaterThanOrEqualTo($expiresAt)
                    ) {

                        if (!$subscription->nexp) {

                            $subscription->user->notify(
                                new MerchantSubscriptionNotification('expired')
                            );
                        }

                        $subscription->update([
                            'status' => false,
                            'nexp'   => true,
                        ]);
                    }

                }

            });

        $this->info('Merchant subscription cron completed successfully.');

        return self::SUCCESS;
    }
}

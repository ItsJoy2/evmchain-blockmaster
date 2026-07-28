<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchantSubscriptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Notification Types:
     *
     * expire_5_days
     * expire_3_days
     * expired
     * transaction_limit
     * wallet_limit
     * domain_verified
     * domain_verification_failed
     */

    public function __construct(
        protected string $type,
        protected array $data = []
    ) {}

    /**
     * Delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Mail Notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->greeting('Hello ' . ($notifiable->name ?? 'Merchant') . ',');

        switch ($this->type) {

            case 'expire_5_days':

                return $mail
                    ->subject('Your Merchant Subscription Expires in 5 Days')
                    ->line('This is a friendly reminder that your merchant subscription will expire in 5 days.')
                    ->line('Expiration Date: ' . ($this->data['expires_at'] ?? '-'))
                    ->line('Please renew your subscription before it expires to avoid service interruption.')
                    ->salutation('Regards,');

            case 'expire_3_days':

                return $mail
                    ->subject('Your Merchant Subscription Expires in 3 Days')
                    ->line('Your merchant subscription will expire in 3 days.')
                    ->line('Expiration Date: ' . ($this->data['expires_at'] ?? '-'))
                    ->line('Renew your subscription to continue using all merchant services.')
                    ->salutation('Regards,');

            case 'expired':

                return $mail
                    ->subject('Merchant Subscription Expired')
                    ->line('Your merchant subscription has expired.')
                    ->line('Merchant services are now unavailable until your subscription is renewed.')
                    ->line('Please renew your subscription to continue accepting crypto payments.')
                    ->salutation('Regards,');

            case 'transaction_limit':

                return $mail
                    ->subject('Transaction Limit Reached')
                    ->line('You have reached the transaction limit of your current subscription.')
                    ->line('Current Usage: '
                        . ($this->data['used'] ?? '-')
                        . ' / '
                        . ($this->data['limit'] ?? '-'))
                    ->line('Upgrade or renew your subscription to continue processing new transactions.')
                    ->salutation('Regards,');

            case 'wallet_limit':

                return $mail
                    ->subject('Wallet Limit Reached')
                    ->line('You have reached the decentralized wallet limit of your subscription.')
                    ->line('Current Usage: '
                        . ($this->data['used'] ?? '-')
                        . ' / '
                        . ($this->data['limit'] ?? '-'))
                    ->line('Upgrade your subscription if you need to create more wallet addresses.')
                    ->salutation('Regards,');

            case 'domain_verified':

                return $mail
                    ->subject('Domain Verified Successfully')
                    ->line('Congratulations! Your merchant domain has been verified successfully.')
                    ->line('Domain: ' . ($this->data['domain'] ?? '-'))
                    ->line('You can now use this domain with the payment gateway.')
                    ->salutation('Regards,');

            case 'domain_verification_failed':

                return $mail
                    ->subject('Domain Verification Failed')
                    ->line('We were unable to verify your merchant domain.')
                    ->line('Domain: ' . ($this->data['domain'] ?? '-'))
                    ->line('Please check your verification settings and try again.')
                    ->salutation('Regards,');

            default:

                return $mail
                    ->subject('Merchant Notification')
                    ->line('You have received a new merchant notification.')
                    ->salutation('Regards,');
        }
    }

    /**
     * Database notification (optional)
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}

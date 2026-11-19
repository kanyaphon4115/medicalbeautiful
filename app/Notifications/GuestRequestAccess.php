<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class GuestRequestAccess extends Notification
{
    public function __construct(public readonly string $url, public readonly string $reference, public readonly string $period, public readonly bool $real = false) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('AESTHETIC — Your guest request access link'))
            ->greeting(__('Your request has been received.'))
            ->line(__('Reference').': '.$this->reference)
            ->line(__('Preferred surgery month / year').': '.$this->period)
            ->action(__('View Your Request'), $this->url)
            ->line(__('This private link expires in 7 days. Do not forward it to anyone else.'))
            ->line(__($this->real ? 'Your request is saved and available to authorized partner hospitals. You can review their quotations using the private link above. No payment has been collected.' : 'This request belongs to the guest journey preview. Hospital quotations, payments, and account creation remain a simulation. No payment has been collected.'))
            ->salutation(__('Kind regards, admin'));
    }
}

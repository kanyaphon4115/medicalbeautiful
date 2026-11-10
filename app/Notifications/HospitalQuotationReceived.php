<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HospitalQuotationReceived extends Notification
{
    public function __construct(
        public readonly string $reference,
        public readonly string $url,
        public readonly bool $guest,
    ) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('MedicalBeautiful — A hospital has responded to '.$this->reference)
            ->greeting('You have a new hospital response.')
            ->line('Request ID: '.$this->reference)
            ->line('A hospital has submitted or updated a quotation for your request.')
            ->action('View Quotation', $this->url);

        if ($this->guest) {
            $message->line('You can compare quotations and select your preferred hospital without creating an account.')
                ->line('This private link expires in 7 days. Do not share or forward it.');
        } else {
            $message->line('Sign in to your account to view your hospital quotations.');
        }

        return $message->line('For your privacy, medical details and photos are available only on your private request page.')
            ->salutation('MedicalBeautiful Team');
    }
}

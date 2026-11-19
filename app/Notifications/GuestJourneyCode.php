<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class GuestJourneyCode extends Notification
{
    public function __construct(public readonly string $code, public readonly bool $real = false, public readonly ?string $url = null) {}
    public function via($notifiable): array { return ['mail']; }
    public function toMail($notifiable): MailMessage
    {
        if ($this->real) {
            $mail=(new MailMessage)->subject(__('Payment Confirmed – Verify Your Email | AESTHETIC'))
                ->greeting(__('Dear Customer,'))
                ->line(__('We are pleased to inform you that your deposit payment has been successfully verified.'))
                ->line(__('Your payment has been confirmed.'))
                ->line(__('Please use the following 6-digit verification code to verify your email address and continue setting up your account.'))
                ->line(__('Verification Code').': **'.$this->code.'**')
                ->line(__('This code will expire in 10 minutes. It can be used once. Never share this code.'))
                ->line(__('Thank you for choosing AESTHETIC.'));
            if($this->url)$mail->action(__('Verify Your Email'),$this->url);
            return $mail;
        }
        return (new MailMessage)->subject(__('AESTHETIC — Guest journey verification code'))
            ->line(__('Your 6-digit verification code is:'))
            ->line('**'.$this->code.'**')
            ->line(__('This code expires in 5 minutes and can be used once. Never share this code.'))
            ->line(__($this->real ? 'Enter this code on the website to verify your email and continue to your customer account.' : 'This code verifies email access for the guest journey preview only. It does not confirm a real payment or create a real customer account.'))
            ->salutation(__('Kind regards, admin'));
    }
}

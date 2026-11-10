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
            $mail=(new MailMessage)->subject('Payment Confirmed – Verify Your Email | AESTHETIC')
                ->greeting('Dear Customer,')
                ->line('We are pleased to inform you that your deposit payment has been successfully verified.')
                ->line('Your payment has been confirmed.')
                ->line('Please use the following 6-digit verification code to verify your email address and continue setting up your account.')
                ->line('Verification Code: **'.$this->code.'**')
                ->line('This code will expire in 10 minutes. It can be used once. Never share this code.')
                ->line('Thank you for choosing AESTHETIC.');
            if($this->url)$mail->action('Verify Your Email',$this->url);
            return $mail;
        }
        return (new MailMessage)->subject('AESTHETIC — Guest journey verification code')
            ->line('Your 6-digit verification code is:')
            ->line('**'.$this->code.'**')
            ->line('This code expires in 5 minutes and can be used once. Never share this code.')
            ->line($this->real ? 'Enter this code on the website to verify your email and continue to your customer account.' : 'This code verifies email access for the guest journey preview only. It does not confirm a real payment or create a real customer account.')
            ->salutation('Kind regards, admin');
    }
}

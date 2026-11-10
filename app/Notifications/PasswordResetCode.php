<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

class PasswordResetCode extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('AESTHETIC password reset code'))
            ->line(__('Your password reset code is:'))
            ->line('**'.$this->code.'**')
            ->line(__('This code expires in 5 minutes and can only be used once.'))
            ->action(__('Reset password'), route('password.verify', ['email' => $notifiable->email]))
            ->line(__('If you did not request a password reset, no further action is required.'))
            ->salutation(new HtmlString('ขอแสดงความนับถือ<br>admin'));
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends \Illuminate\Auth\Notifications\ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return parent::buildMailMessage($url)
            ->salutation(__('Kind regards, admin'));
    }
}

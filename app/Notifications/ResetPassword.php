<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

class ResetPassword extends \Illuminate\Auth\Notifications\ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return parent::buildMailMessage($url)
            ->salutation(new HtmlString('ขอแสดงความนับถือ<br>admin'));
    }
}

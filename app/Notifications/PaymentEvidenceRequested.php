<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class PaymentEvidenceRequested extends Notification {
 public function __construct(public readonly string $url,public readonly string $notes){}
 public function via($notifiable):array{return ['mail'];}
 public function toMail($notifiable):MailMessage {
  return (new MailMessage)->subject('AESTHETIC — Payment evidence requested')
   ->line('Our team needs payment evidence to review your existing booking.')
   ->line($this->notes)->action('Submit Requested Payment Evidence',$this->url)
   ->line('This private link expires in 7 days. Do not pay again unless our team specifically asks you to.');
 }
}

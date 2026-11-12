<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BookingDepositApproved extends Notification
{
    public function __construct(
        public readonly string $bookingReference,
        public readonly string $paymentReference,
        public readonly int $amountSatang,
        public readonly string $url,
        public readonly int $totalTreatmentCostSatang,
        public readonly int $initialDepositPaidSatang,
        public readonly int $bookingDepositPaidSatang,
        public readonly int $remainingBalanceSatang,
    ) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject('Booking Confirmed | AESTHETIC')
            ->greeting('Dear Customer,')
            ->line('Your booking deposit payment of **'.$this->money($this->amountSatang).'** has been verified and approved by our admin and used to confirm your booking.')
            ->line('Your booking status is now **Confirmed**.')
            ->line('Booking Reference: **'.$this->bookingReference.'**')
            ->line('Payment Reference: **'.$this->paymentReference.'**')
            ->line('Total Treatment Cost: **'.$this->money($this->totalTreatmentCostSatang).'**')
            ->line('Initial Deposit Paid (Step 4): **'.$this->money($this->initialDepositPaidSatang).'**')
            ->line('Booking Deposit Paid: **'.$this->money($this->bookingDepositPaidSatang).'**')
            ->line('Remaining Balance: **'.$this->money($this->remainingBalanceSatang).'**')
            ->line('The remaining balance of '.$this->money($this->remainingBalanceSatang).' can be paid directly to your selected hospital or clinic on your treatment date.')
            ->action('View My Bookings', $this->url)
            ->line('Thank you for choosing AESTHETIC.');
    }

    private function money(int $satang): string
    {
        return 'THB '.number_format($satang / 100, 2);
    }
}

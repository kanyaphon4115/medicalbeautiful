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
        return (new MailMessage)->subject(__('Booking Confirmed | AESTHETIC'))
            ->greeting(__('Dear Customer,'))
            ->line(__('Your booking deposit payment of **:amount** has been verified and approved by our admin and used to confirm your booking.', ['amount'=>$this->money($this->amountSatang)]))
            ->line(__('Your booking status is now **Confirmed**.'))
            ->line(__('Booking Reference').': **'.$this->bookingReference.'**')
            ->line(__('Payment Reference').': **'.$this->paymentReference.'**')
            ->line(__('Total Treatment Cost').': **'.$this->money($this->totalTreatmentCostSatang).'**')
            ->line(__('Initial Deposit Paid').': **'.$this->money($this->initialDepositPaidSatang).'**')
            ->line(__('Booking Deposit Paid').': **'.$this->money($this->bookingDepositPaidSatang).'**')
            ->line(__('Remaining Balance').': **'.$this->money($this->remainingBalanceSatang).'**')
            ->line(__('The remaining balance of :amount can be paid directly to your selected hospital or clinic on your treatment date.', ['amount'=>$this->money($this->remainingBalanceSatang)]))
            ->action(__('View My Bookings'), $this->url)
            ->line(__('Thank you for choosing AESTHETIC.'));
    }

    private function money(int $satang): string
    {
        return 'THB '.number_format($satang / 100, 2);
    }
}

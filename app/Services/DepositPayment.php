<?php
namespace App\Services;

use App\Models\Booking;

/** Customer-safe payment presentation. A verified provider/webhook is required for live payments. */
class DepositPayment
{
    public const DUE_NOW_SATANG = 500000;
    public static function calculate(int $total): int
    {
        return min($total, 500000, max(200000, intdiv($total + 5, 10)));
    }
    public function summary(Booking $booking): array
    {
        $quote = $booking->quotation;
        [$whole,$fraction] = explode('.', $quote->amount);
        $total = ((int)$whole * 100) + (int)$fraction;
        $deposit = $booking->deposit_amount_satang;
        return [
            'booking_reference'=>sprintf('BK-%s-%06d',$booking->created_at->format('Y'),$booking->id),
            'procedure_name'=>$booking->treatmentRequest->procedure_label,
            'payment_url'=>route('bookings.deposit',$booking),'submission_url'=>route('guest.requests.show',$booking->treatmentRequest->reference).'#booking-deposit',
            'currency'=>'THB','total_satang'=>$total,'deposit_satang'=>$deposit,'remaining_satang'=>$deposit===null?null:$total-$deposit,
            'payment_status'=>$booking->payment_status,
            'booking_status'=>$booking->status,'payment_is_simulated'=>$booking->payment_is_simulated,
            'integration'=>['available'=>false,'mode'=>'manual_review','verification'=>'manual_admin','bank_connected'=>false,'qr_payload'=>null],
        ];
    }
}

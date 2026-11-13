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
    public function paidSummary(Booking $booking): array
    {
        $total=(int)str_replace('.', '', $booking->quotation->amount);
        $payments=\Illuminate\Support\Facades\Schema::hasTable('payment_submissions')
            ? $booking->paymentSubmissions()->whereIn('purpose',['initial','treatment'])->get(['id','purpose','status','amount_satang'])
            : collect();
        // Sum each transaction once. Booking fields mirror the current stage, so never add them to recorded payments.
        $paid=$booking->is_test || $booking->payment_is_simulated ? collect()
            : $payments->filter(fn($payment)=>in_array(strtolower($payment->status),['approved','paid'],true));
        $initial=(int)$paid->where('purpose','initial')->sum('amount_satang');
        $treatment=(int)$paid->where('purpose','treatment')->sum('amount_satang');
        // Retained pre-ledger payments are counted only when their stage has no transaction records.
        if($booking->deposit_status==='Paid' && $booking->payment_verified_at && !$booking->is_test && !$booking->payment_is_simulated
            && $payments->where('purpose',$booking->deposit_phase)->isEmpty()){
            if($booking->deposit_phase==='initial')$initial=(int)$booking->deposit_amount_satang;
            elseif($booking->deposit_phase==='treatment')$treatment=(int)$booking->deposit_amount_satang;
        }
        $paidTotal=$initial+$treatment;
        return ['initial_satang'=>$initial,'booking_paid_satang'=>$treatment,'total_paid_satang'=>$paidTotal,
            'remaining_satang'=>max(0,$total-$paidTotal)];
    }
    public function treatmentSummary(Booking $booking): array
    {
        $total = (int) str_replace('.', '', $booking->quotation->amount);
        $percentage = (int) ($booking->deposit_percentage ?? $booking->hospital->treatment_deposit_percentage ?? 10);
        abort_unless($percentage >= 10 && $percentage <= 20,422,'Hospital deposit percentage must be between 10 and 20.');
        $paid=$this->paidSummary($booking);
        $initial=$paid['initial_satang'];
        $required = intdiv($total * $percentage + 50, 100);
        return [...$paid,'total_satang'=>$total,'percentage'=>$percentage,'required_satang'=>$required,
            'outstanding_satang'=>max(0,$required-$paid['total_paid_satang']),
            'additional_satang'=>max(0,$required-$initial),'phase'=>$booking->deposit_phase,'payment_status'=>$booking->deposit_status,
            'section_id'=>'booking-deposit-'.$booking->id,'start_url'=>route('bookings.treatment-deposit',$booking),'payment_url'=>route('bookings.deposit',$booking)];
    }
    public function summary(Booking $booking): array
    {
        $quote = $booking->quotation;
        [$whole,$fraction] = explode('.', $quote->amount);
        $total = ((int)$whole * 100) + (int)$fraction;
        $deposit = $booking->deposit_amount_satang;
        $treatment=$booking->deposit_phase==='treatment' ? $this->treatmentSummary($booking) : null;
        $paid=$treatment ?? $this->paidSummary($booking);
        return [
            'request_reference'=>sprintf('REQ-%s-%06d',$booking->treatmentRequest->created_at->format('Y'),$booking->treatment_request_id),
            'hospital_name'=>$booking->deposit_phase==='treatment' ? $booking->hospital?->name : null,
            'treatment_deposit'=>$treatment,
            'claim_url'=>route('guest.requests.payment-claim',$booking->treatmentRequest->reference),
            'booking_reference'=>sprintf('BK-%s-%06d',$booking->created_at->format('Y'),$booking->id),
            'procedure_name'=>$booking->treatmentRequest->procedure_label,
            'payment_url'=>route('bookings.deposit',$booking),'submission_url'=>route('guest.requests.show',$booking->treatmentRequest->reference).'#booking-deposit',
            'purpose'=>$booking->deposit_phase,'currency'=>'THB','total_satang'=>$total,'deposit_satang'=>$deposit,
            'initial_satang'=>$paid['initial_satang'],'booking_paid_satang'=>$paid['booking_paid_satang'],'total_paid_satang'=>$paid['total_paid_satang'],
            'remaining_satang'=>$deposit===null?null:$paid['remaining_satang'],
            'payment_status'=>$booking->payment_status,
            'booking_status'=>$booking->status,'payment_is_simulated'=>$booking->payment_is_simulated,
            'integration'=>['available'=>false,'mode'=>'manual_review','verification'=>'manual_admin','bank_connected'=>false,'qr_payload'=>null],
        ];
    }
}

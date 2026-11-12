<?php
namespace App\Services;

use App\Models\{Booking, PaymentSubmission};

class PaymentLedger
{
    /** Call while holding the request and booking locks. Never create records on page reads. */
    public function ensureCurrent(Booking $booking): ?PaymentSubmission
    {
        if (!PaymentSubmission::available()) return null;
        $existing = $booking->paymentSubmissions()->where('purpose', $booking->deposit_phase)->first();
        if ($existing || !$booking->deposit_amount_satang) return $existing;

        return $booking->paymentSubmissions()->create([
            'purpose' => $booking->deposit_phase,
            'amount_satang' => $booking->deposit_amount_satang,
            'method' => 'Not submitted',
            'status' => match ($booking->deposit_status) {
                'Paid' => 'approved', 'Rejected' => 'rejected', 'Information Required' => 'information_requested',
                'Refunded' => 'refunded', 'Failed' => 'failed', 'Expired' => 'expired', default => 'pending',
            },
            'submitted_at' => $booking->created_at,
            'reviewed_at' => $booking->payment_verified_at,
            'reviewer_id' => $booking->payment_verified_by,
        ]);
    }

    public function validateTreatmentPayment(Booking $booking, PaymentSubmission $payment): void
    {
        if ($payment->purpose !== 'treatment') return;
        $initial = $booking->paymentSubmissions()->where('purpose', 'initial')->where('status', 'approved')->get();
        abort_unless($initial->isNotEmpty() && $initial->sum('amount_satang') > 0 && $booking->treatmentRequest->payment_confirmed_at,
            422, 'Step 4 must be approved before verifying Step 9.');
        abort_unless($booking->user_id && $booking->user_id === $booking->treatmentRequest->user_id,
            422, 'Account activation is required before verifying Step 9.');
        $summary = (new DepositPayment)->treatmentSummary($booking);
        abort_unless($payment->amount_satang === $summary['additional_satang'],
            422, 'The Step 9 amount must match the required deposit minus the approved Step 4 payment.');
    }
}

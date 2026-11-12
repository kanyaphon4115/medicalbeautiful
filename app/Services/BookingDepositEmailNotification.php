<?php
namespace App\Services;

use App\Models\Booking;
use App\Notifications\BookingDepositApproved;
use Illuminate\Support\Facades\{Cache,Notification};

class BookingDepositEmailNotification
{
    /** Called after payment approval commits. Mail failure must not reverse an approved payment. */
    public function send(Booking $booking): string
    {
        try {
            return Cache::lock('booking-deposit-email:'.$booking->id, 60)->block(5, function () use ($booking) {
                $row=Booking::with('treatmentRequest')->findOrFail($booking->id);
                if ($row->deposit_phase!=='treatment' || $row->deposit_status!=='Paid' || $row->status!=='Confirmed'
                    || $row->is_test || $row->payment_is_simulated || !$row->payment_verified_at
                    || !$row->treatmentRequest->user_id || $row->treatmentRequest->status==='cancelled') return 'ineligible';

                $payment=$row->paymentSubmissions()->where('purpose','treatment')->where('status','approved')
                    ->where('amount_satang',$row->deposit_amount_satang)->whereNotNull('reviewed_at')->first();
                if (!$payment) return 'ineligible';
                $key='booking-deposit-email-sent:'.$payment->id;
                if (Cache::has($key)) return 'already_sent';
                // Log/array transports cannot deliver a customer email in production.
                if (!app()->runningUnitTests() && config('mail.default')!=='smtp') return 'failed';

                $deposit=(new DepositPayment)->treatmentSummary($row);
                Notification::route('mail',$row->treatmentRequest->email)->notify(new BookingDepositApproved(
                    $row->reference, $payment->reference, $payment->amount_satang, route('requests.index').'#my-bookings',
                    $deposit['total_satang'], $deposit['initial_satang'], $deposit['booking_paid_satang'], $deposit['remaining_satang']
                ));
                Cache::forever($key, now()->toIso8601String());
                return 'sent';
            });
        } catch (\Throwable $exception) {
            // Transport exceptions can contain credentials. Only return a generic delivery result.
            return 'failed';
        }
    }
}

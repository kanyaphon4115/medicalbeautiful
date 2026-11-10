<?php

namespace App\Services;

use App\Models\Quotation;
use App\Notifications\HospitalQuotationReceived;
use Illuminate\Support\Facades\{Cache, Notification, URL};

class QuotationEmailNotification
{
    public function send(Quotation $quotation): string
    {
        // Serialize sends across duplicate hospital submissions. Failed sends can
        // be retried by saving again; a delivery is recorded only after success.
        try {
            return Cache::lock('quotation-customer-email:'.$quotation->id, 60)->block(5, function () use ($quotation) {
                $quote = Quotation::with('treatmentRequest.customer')->findOrFail($quotation->id);
                $item = $quote->treatmentRequest;
                if ($quote->withdrawn_at) return 'withdrawn';
                if (($item->customer?->notification_preferences['quotations'] ?? true) === false) {
                    return 'disabled';
                }

                $fingerprint = hash('sha256', json_encode([
                    $quote->amount, $quote->details, $quote->valid_until?->format('Y-m-d'),
                ], JSON_THROW_ON_ERROR));
                if (hash_equals($quote->last_emailed_fingerprint ?? '', $fingerprint)) {
                    return 'already_sent';
                }

                // Do not report delivery through a development log/array mailer.
                if (!app()->runningUnitTests() && config('mail.default') !== 'smtp') {
                    return 'failed';
                }

                $guest = $item->user_id === null;
                $url = $guest
                    ? URL::temporarySignedRoute('guest.requests.access', now()->addDays(7), ['reference'=>$item->reference])
                    : route('requests.index');
                $reference = sprintf('REQ-%s-%06d', $item->created_at->format('Y'), $item->id);
                Notification::route('mail', $item->email)->notify(new HospitalQuotationReceived($reference, $url, $guest));

                $quote->forceFill(['last_emailed_fingerprint'=>$fingerprint, 'customer_emailed_at'=>now()])->save();
                return 'sent';
            });
        } catch (\Throwable $exception) {
            // Preserve the quotation and show a generic retry message. SMTP
            // exceptions can contain credentials; do not expose or log them.
            return 'failed';
        }
    }
}

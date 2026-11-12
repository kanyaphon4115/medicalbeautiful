<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\{Booking,TreatmentRequest};
use App\Services\PaymentLedger;

return new class extends Migration {
    public function up(): void {
        Booking::paymentEligible()->whereNotNull('deposit_amount_satang')->where('deposit_amount_satang','>',0)
            ->chunkById(100,function($bookings){
                foreach($bookings as $booking) DB::transaction(function()use($booking){
                    TreatmentRequest::whereKey($booking->treatment_request_id)->lockForUpdate()->firstOrFail();
                    $row=Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                    (new PaymentLedger)->ensureCurrent($row);
                });
            });
    }
    // Payment audit history must survive a schema rollback.
    public function down(): void {}
};

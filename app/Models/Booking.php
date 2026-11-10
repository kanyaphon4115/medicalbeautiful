<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Booking extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['appointment_date'=>'date','deposit_amount_satang'=>'integer','refunded_amount_satang'=>'integer','is_test'=>'boolean','payment_is_simulated'=>'boolean','payment_verified_at'=>'datetime','test_marked_at'=>'datetime']; }
    // Acceptance evidence or payment history makes a retained row a visible booking.
    public function scopeBookingEligible($query) { return $query->paymentEligible(); }
    public function scopePaymentEligible($query) {
        return $query->where(function($query) {
            $query->whereIn('deposit_status',['Paid','Refunded','Failed','Expired'])
                ->orWhereNotNull('payment_verified_at')->orWhere('refunded_amount_satang','>',0)
                ->orWhereHas('treatmentRequest',fn($r)=>$r->whereNotNull('payment_confirmed_at'))
                ->orWhere(function($q) {
                    $q->where('status','!=','Awaiting Acceptance')
                        ->whereHas('treatmentRequest',fn($r)=>$r->where('status','!=','cancelled')
                            ->whereNotNull('hospital_approved_at')->whereColumn('selected_hospital_id','bookings.hospital_id')
                            ->where(function($owner){$owner->whereColumn('treatment_requests.user_id','bookings.user_id')->orWhere(fn($guest)=>$guest->whereNull('treatment_requests.user_id')->whereNull('bookings.user_id'));}))
                        ->whereHas('quotation',fn($q)=>$q->whereColumn('quotations.treatment_request_id','bookings.treatment_request_id')
                            ->whereColumn('quotations.hospital_id','bookings.hospital_id'));
                });
        });
    }
    public function scopePendingPaymentEligible($query) {
        return $query->paymentEligible()->where('deposit_status','Pending')->whereIn('status',['Pending Payment','Confirmed'])
            ->whereHas('treatmentRequest',fn($r)=>$r->where('status','!=','cancelled'));
    }
    public function getIsPaymentEligibleAttribute(): bool { return static::paymentEligible()->whereKey($this->id)->exists(); }
    public function treatmentRequest() { return $this->belongsTo(TreatmentRequest::class); }
    public function customer() { return $this->belongsTo(User::class,'user_id'); }
    public function hospital() { return $this->belongsTo(User::class,'hospital_id'); }
    public function verifier() { return $this->belongsTo(User::class,'payment_verified_by'); }
    public function getReferenceAttribute(): string { return sprintf('BK-%s-%06d',$this->created_at->format('Y'),$this->id); }
    public function getPaymentReferenceAttribute():string{return sprintf('PAY-%s-%06d',$this->created_at->format('Y'),$this->id);}
    public function getPaymentStatusAttribute(): string { if (!$this->is_payment_eligible) return 'Not Started'; return ['Pending'=>'Pending Payment','Paid'=>'Paid','Failed'=>'Failed','Refunded'=>'Refunded'][$this->deposit_status] ?? $this->deposit_status; }
    public function paymentSubmissions(){return $this->hasMany(PaymentSubmission::class)->latest('id');}
    public function quotation() { return $this->belongsTo(Quotation::class); }
}

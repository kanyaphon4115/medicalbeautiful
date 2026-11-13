<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TreatmentRequest extends Model {
 protected $guarded = ['id'];
 public function getProcedureNamesAttribute(): array { return $this->procedures ?: ($this->procedure ? [$this->procedure] : []); }
 public function getProcedureLabelAttribute(): string { return implode(', ', $this->procedure_names); }
 public function getPreferredPeriodAttribute(): ?string {
  if ($this->preferred_surgery_month && $this->preferred_surgery_year) return sprintf('%04d-%02d',$this->preferred_surgery_year,$this->preferred_surgery_month);
  return $this->preferred_surgery_date?->format('Y-m');
 }
 public function getPreferredPeriodLabelAttribute(): string {
  return $this->preferred_period ? \Carbon\Carbon::createFromFormat('!Y-m',$this->preferred_period)->format('F Y') : 'Not specified';
 }
 public function getCustomerStatusAttribute(): string {
  if ($this->status==='cancelled') return 'cancelled';
  $booking=$this->booking;
  if ($booking && $booking->is_payment_eligible) {
   if ($booking->status==='Cancelled') return 'cancelled';
   if ($booking->deposit_phase==='treatment' && $booking->deposit_status==='Paid' && $booking->payment_verified_at && !$booking->is_test && !$booking->payment_is_simulated) return $booking->status==='Completed'?'completed':'confirmed';
   if ($booking->deposit_phase==='treatment') return 'pending_booking_deposit';
   if ($booking->deposit_status==='Paid') return $this->user_id ? 'account_activated' : 'pending';
   return 'pending';
  }
  if ($booking?->status==='Awaiting Acceptance') return 'quotes_received';
  if (in_array($this->status,['pending_payment','confirmed'],true)) return $this->quotations()->exists()?'quotes_received':'pending';
  return $this->status;
 }
 public function customer() { return $this->belongsTo(User::class, 'user_id'); }
 public function booking() { return $this->hasOne(Booking::class); }
 public function quotations() { return $this->hasMany(Quotation::class)->whereNull('withdrawn_at'); }
 public function scopeAvailableToHospital($query, int $hospitalId) {
  return $query->whereNotNull('consented_at')->where('status','!=','cancelled')->where(function ($query) use ($hospitalId) {
   $query->where('selected_hospital_id',$hospitalId)->orWhere(function ($query) {
    $query->whereNull('selected_hospital_id')->whereNull('selected_hospital_name');
   });
  });
 }
 protected function casts(): array { return ['date_of_birth'=>'encrypted','additional_notes'=>'encrypted','procedures'=>'array','line_id'=>'encrypted','phone_number'=>'encrypted','age'=>'integer','preferred_surgery_month'=>'integer','preferred_surgery_year'=>'integer','goals'=>'encrypted','medical_information'=>'encrypted','preferred_surgery_date'=>'date','consented_at'=>'datetime','hospital_approved_at'=>'datetime','payment_confirmed_at'=>'datetime']; }
 public function canChatWithHospital(): bool {
  return $this->booking && app(\App\Services\BookingChatAccess::class)->unlocked($this->booking);
 }
 public function photos(): HasMany { return $this->hasMany(RequestPhoto::class); }
}

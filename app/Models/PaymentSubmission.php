<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentSubmission extends Model {
 public static function available():bool{return \Illuminate\Support\Facades\Schema::hasTable('payment_submissions')&&\Illuminate\Support\Facades\Schema::hasTable('payment_reviews');}
 protected $guarded=['id'];
 protected $attributes=['purpose'=>'initial'];
 protected function casts():array{return ['amount_satang'=>'integer','submitted_at'=>'datetime','reviewed_at'=>'datetime'];}
 public function getReferenceAttribute():string{return sprintf('PAY-%s-%s%06d',$this->created_at->format('Y'),$this->id<0?'L-':'',abs($this->id));}
 public function getStageLabelAttribute():string{return $this->purpose==='treatment'?'Booking Deposit ':'Initial Deposit (Step 4)';}
 public function getDisplayStatusAttribute():string{return match($this->status){'approved'=>'Paid','pending'=>'Pending','information_requested'=>'Information Required',default=>ucfirst($this->status)};}
 public function hasEvidence():bool{return filled($this->transaction_reference)||($this->proof_path&&\Illuminate\Support\Facades\Storage::disk('request_photos')->exists($this->proof_path));}
 public function booking(){return $this->belongsTo(Booking::class);}
 public function reviewer(){return $this->belongsTo(User::class,'reviewer_id');}
 public function reviews(){return $this->hasMany(PaymentReview::class);}
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentSubmission extends Model {
 public static function available():bool{return \Illuminate\Support\Facades\Schema::hasTable('payment_submissions')&&\Illuminate\Support\Facades\Schema::hasTable('payment_reviews');}
 protected $guarded=['id'];
 protected function casts():array{return ['amount_satang'=>'integer','submitted_at'=>'datetime','reviewed_at'=>'datetime'];}
 public function hasEvidence():bool{return filled($this->transaction_reference)||($this->proof_path&&\Illuminate\Support\Facades\Storage::disk('request_photos')->exists($this->proof_path));}
 public function booking(){return $this->belongsTo(Booking::class);}
 public function reviewer(){return $this->belongsTo(User::class,'reviewer_id');}
 public function reviews(){return $this->hasMany(PaymentReview::class);}
}

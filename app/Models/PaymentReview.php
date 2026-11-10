<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentReview extends Model {
 public $timestamps=false;protected $guarded=['id'];
 protected function casts():array{return ['created_at'=>'datetime'];}
 public function reviewer(){return $this->belongsTo(User::class,'reviewer_id');}
}

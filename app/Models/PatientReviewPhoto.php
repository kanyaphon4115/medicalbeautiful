<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PatientReviewPhoto extends Model {
 protected $guarded=['id'];
 public function review(){return $this->belongsTo(PatientReview::class,'patient_review_id');}
}

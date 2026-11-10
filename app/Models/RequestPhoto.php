<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RequestPhoto extends Model {
 protected $guarded = ['id'];
 public function treatmentRequest(): BelongsTo { return $this->belongsTo(TreatmentRequest::class); }
}

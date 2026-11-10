<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Quotation extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount'=>'decimal:2','valid_until'=>'date','details'=>'encrypted','withdrawn_at'=>'datetime']; }
    public function treatmentRequest() { return $this->belongsTo(TreatmentRequest::class); }
    public function hospital() { return $this->belongsTo(User::class, 'hospital_id'); }
}

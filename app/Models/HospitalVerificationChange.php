<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HospitalVerificationChange extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'reviewed_at' => 'datetime'];
    }

    public function profile()
    {
        return $this->belongsTo(HospitalProfile::class, 'hospital_profile_id');
    }
}

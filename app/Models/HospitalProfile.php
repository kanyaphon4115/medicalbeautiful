<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HospitalProfile extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['registered_name', 'license_number', 'verification_documents'];

    protected function casts(): array
    {
        return ['treatments' => 'array', 'gallery' => 'array', 'registered_name' => 'encrypted', 'license_number' => 'encrypted', 'verification_documents' => 'encrypted:array', 'verified_at' => 'datetime'];
    }

    public function hospital()
    {
        return $this->belongsTo(User::class, 'hospital_id');
    }

    public function changes()
    {
        return $this->hasMany(HospitalVerificationChange::class);
    }

    public function mediaUrl(string $kind, ?string $id = null): string
    {
        return route('hospital.profile.media', ['profile' => $this, 'kind' => $kind, 'file' => $id]);
    }
}

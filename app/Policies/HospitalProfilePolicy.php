<?php

namespace App\Policies;

use App\Models\HospitalProfile;
use App\Models\User;

class HospitalProfilePolicy
{
    public function view(User $user, HospitalProfile $profile): bool
    {
        return $user->isChatSupport() || ($user->isHospital() && $profile->hospital_id === $user->id);
    }

    public function update(User $user, HospitalProfile $profile): bool
    {
        return $user->isHospital() && $profile->hospital_id === $user->id;
    }

    public function moderate(User $user, HospitalProfile $profile): bool
    {
        return $user->isChatSupport();
    }
}

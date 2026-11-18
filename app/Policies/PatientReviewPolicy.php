<?php
namespace App\Policies;
use App\Models\{PatientReview,User};
class PatientReviewPolicy {
 public function moderate(User $user,PatientReview $review):bool{return $user->isChatSupport();}
 public function viewAny(User $user):bool{return $user->isChatSupport();}
}

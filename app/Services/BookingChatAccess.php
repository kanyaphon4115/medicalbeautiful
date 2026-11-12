<?php
namespace App\Services;

use App\Models\{Booking,User};

class BookingChatAccess
{
    public function authorize(User $user, Booking $booking): void
    {
        abort_unless($user->isChatSupport() || $user->isHospital(),403);
        abort_unless($user->isChatSupport() || $booking->hospital_id===$user->id,404);
    }

    public function unlocked(Booking $booking): bool
    {
        $item=$booking->treatmentRequest;
        // Keep the existing chat unlock after initial payment approval/account activation.
        return $item && $item->canChatWithHospital() && $booking->user_id
            && $booking->user_id===$item->user_id && $item->selected_hospital_id===$booking->hospital_id
            && $booking->hospital?->isHospital() && !$booking->is_test && !$booking->payment_is_simulated
            && $booking->status!=='Cancelled' && $booking->deposit_status!=='Refunded';
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminPortalController extends Controller
{
    public const PAGES = [
        'dashboard' => 'Dashboard', 'hospitals' => 'Hospitals', 'customers' => 'Customers',
        'requests' => 'All Requests', 'bookings' => 'Bookings',
        'payments' => 'Payments', 'chat-management'=>'Chat Management', 'support' => 'Customer Support', 'booking-chats' => 'Booking Chats',
        'reports' => 'Reports', 'settings' => 'Settings',
    ];

    public function __invoke(Request $request, string $page = 'dashboard')
    {
        $supportOnly=$request->user()->role===\App\Models\User::ROLE_SUPPORT;
        abort_unless($request->user()->isAdmin() || ($supportOnly && in_array($page,['chat-management','support','booking-chats'],true)),403);
        abort_unless(isset(self::PAGES[$page]), 404);
        // Portal language is a frontend preference; it does not change account settings.
        $locale = $request->query('lang', $request->session()->get('admin_portal_locale', 'en'));
        $locale = in_array($locale, ['en', 'th', 'ko'], true) ? $locale : 'en';
        $request->session()->put('admin_portal_locale', $locale);
        app()->setLocale($locale);
        $catalog = array_replace(require lang_path('en/super_admin_portal.php'), require lang_path($locale.'/super_admin_portal.php'));
        // Retired prototype controls must not be exposed by the live chat portal.
        foreach(array_keys($catalog) as $key){
            if(str_contains($key,'Super Admin') || in_array($key,['Join Conversation','Leave Conversation'],true))unset($catalog[$key]);
        }
        $catalog['Chat Management']='Chat Management';

        return response()->view('admin.portal', array_merge(in_array($page,['payments','bookings']) ? (new AdminPaymentController)->pageData($request) : [], [
            'realRequests' => $page === 'requests' ? \App\Models\TreatmentRequest::with(['customer','photos','booking','quotations.hospital'])->latest()->paginate(15) : null,
            'page' => $page, 'pages' => $supportOnly ? array_intersect_key(self::PAGES,array_flip(['chat-management','support','booking-chats'])) : self::PAGES, 'supportOnly'=>$supportOnly, 'locale' => $locale, 'catalog' => $catalog,
        ]))->header('Cache-Control', 'private, no-store');
    }
}


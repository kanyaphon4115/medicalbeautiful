<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TreatmentRequestController;

Route::view('/', 'home')->name('home');
Route::get('/guest-booking/resume',[\App\Http\Controllers\GuestTreatmentRequestController::class,'resume'])->middleware('throttle:30,1')->name('guest.booking.resume');
Route::prefix('guest-requests')->name('guest.requests.')->group(function () {
 $controller=\App\Http\Controllers\GuestTreatmentRequestController::class;
 Route::get('/{reference}/verify-email',[$controller,'verificationEntry'])->middleware(['signed','throttle:30,1,real-guest-access'])->name('verification-entry');
 Route::get('/{reference}/account',[$controller,'account'])->name('account');
 Route::post('/{reference}/payment-claim',[$controller,'claimPayment'])->middleware('throttle:10,1')->name('payment-claim');
 Route::post('/',[$controller,'store'])->middleware('throttle:5,1,real-guest-submit')->name('store');
 Route::get('/{reference}/access',[$controller,'access'])->middleware(['signed','throttle:30,1,real-guest-access'])->name('access');
 Route::get('/{reference}',[$controller,'show'])->name('show');
 Route::get('/{reference}/photos/{photo}',[$controller,'photo'])->name('photo');
 Route::post('/{reference}/resend',[$controller,'resend'])->middleware('throttle:5,1,real-guest-resend')->name('resend');
 Route::post('/{reference}/quotations/{quotation}/accept',[$controller,'accept'])->middleware('throttle:10,1,real-guest-accept')->name('accept');
 Route::post('/{reference}/code',[$controller,'code'])->middleware('throttle:5,1,real-guest-code')->name('code');
 Route::post('/{reference}/verify',[$controller,'verify'])->middleware('throttle:10,1,real-guest-verify')->name('verify');
 Route::post('/{reference}/activate',[$controller,'activate'])->middleware('throttle:5,1,real-guest-activate')->name('activate');
});

Route::prefix('guest-journey')->name('guest-journey.mail.')->group(function () {
    Route::post('/requests',[\App\Http\Controllers\GuestJourneyMailController::class,'store'])->middleware('throttle:5,1,guest-mail')->name('store');
    Route::get('/requests/{guest}/access',[\App\Http\Controllers\GuestJourneyMailController::class,'access'])->middleware(['signed','throttle:30,1,guest-mail-access'])->name('access');
    Route::get('/requests/{guest}',[\App\Http\Controllers\GuestJourneyMailController::class,'show'])->middleware('throttle:60,1,guest-mail-show')->name('show');
    Route::post('/requests/{guest}/code',[\App\Http\Controllers\GuestJourneyMailController::class,'code'])->middleware('throttle:10,1,guest-mail-code')->name('code');
    Route::post('/requests/{guest}/verify',[\App\Http\Controllers\GuestJourneyMailController::class,'verify'])->middleware('throttle:10,1,guest-mail-verify')->name('verify');
});

Route::prefix('guest-journey')->name('guest-journey.mail.')->group(function () {
    Route::post('/requests',[\App\Http\Controllers\GuestJourneyMailController::class,'store'])->middleware('throttle:5,1,guest-mail')->name('store');
    Route::get('/requests/{guest}/access',[\App\Http\Controllers\GuestJourneyMailController::class,'access'])->middleware(['signed','throttle:30,1,guest-mail-access'])->name('access');
    Route::get('/requests/{guest}',[\App\Http\Controllers\GuestJourneyMailController::class,'show'])->middleware('throttle:60,1,guest-mail-show')->name('show');
    Route::post('/requests/{guest}/code',[\App\Http\Controllers\GuestJourneyMailController::class,'code'])->middleware('throttle:10,1,guest-mail-code')->name('code');
    Route::post('/requests/{guest}/verify',[\App\Http\Controllers\GuestJourneyMailController::class,'verify'])->middleware('throttle:10,1,guest-mail-verify')->name('verify');
});

Route::middleware('auth')->group(function () {
    Route::post('/admin/payments/{booking}/confirm',[\App\Http\Controllers\PaymentReviewController::class,'confirm'])->middleware('throttle:20,1')->name('admin.payments.confirm');
    Route::post('/admin/payments/submissions/{submission}/confirm',[\App\Http\Controllers\PaymentReviewController::class,'confirmTransaction'])->middleware('throttle:20,1')->name('admin.payments.confirm-transaction');
    Route::post('/admin/payments/{booking}/request-evidence',[\App\Http\Controllers\PaymentReviewController::class,'requestEvidence'])->middleware('throttle:10,1')->name('admin.payments.request-evidence');
    Route::post('/admin/payments/submissions/{submission}/review',[\App\Http\Controllers\PaymentReviewController::class,'review'])->middleware('throttle:20,1')->name('admin.payments.review');
    Route::get('/admin/payments/submissions/{submission}/proof',[\App\Http\Controllers\PaymentReviewController::class,'proof'])->name('admin.payments.proof');
    Route::post('/admin/bookings/{booking}/status', [\App\Http\Controllers\AdminPaymentController::class,'updateBooking'])->name('admin.bookings.status');
    Route::post('/admin/payments/{booking}/test-verify', [\App\Http\Controllers\AdminPaymentController::class,'verify'])->middleware('throttle:10,1')->name('admin.payments.test-verify');
    Route::post('/admin/payments/{booking}/mark-test', [\App\Http\Controllers\AdminPaymentController::class,'markTest'])->middleware('throttle:10,1')->name('admin.payments.mark-test');
    Route::get('/admin/accounts', [\App\Http\Controllers\AdminAccountController::class, 'index'])->name('admin.accounts.index');
    Route::post('/admin/accounts', [\App\Http\Controllers\AdminAccountController::class, 'store'])->middleware('throttle:20,1');
    Route::patch('/admin/accounts/{user}', [\App\Http\Controllers\AdminAccountController::class, 'update'])->middleware('throttle:20,1');
});

foreach (\App\Http\Controllers\AdminPortalController::PAGES as $slug => $label) {
    Route::get('/admin/'.$slug, \App\Http\Controllers\AdminPortalController::class)
        ->defaults('page', $slug)->middleware('auth')->name('admin.'.$slug);
}


Route::middleware('auth')->group(function () {
    Route::get('/hospital/dashboard', [\App\Http\Controllers\HospitalDashboardController::class,'index'])->name('hospital.dashboard');
    Route::post('/hospital/requests/{treatmentRequest}/quotation', [\App\Http\Controllers\HospitalDashboardController::class,'quote'])->middleware('throttle:20,1')->name('hospital.quote');
    Route::post('/hospital/quotations/{quotation}/withdraw', [\App\Http\Controllers\HospitalDashboardController::class,'withdraw'])->middleware('throttle:20,1')->name('hospital.quotations.withdraw');
    Route::post('/hospital/bookings/{booking}/complete', [\App\Http\Controllers\HospitalDashboardController::class,'complete'])->name('hospital.complete');
    Route::get('/bookings/{booking}/deposit', [\App\Http\Controllers\HospitalDashboardController::class,'payment'])->name('bookings.deposit');
    Route::post('/quotations/{quotation}/accept', [\App\Http\Controllers\HospitalDashboardController::class,'accept'])->name('quotations.accept');
});

Route::middleware('guest')->group(function () {
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::view('/verify-reset-code', 'auth.verify-reset-code')->name('password.verify');
    Route::post('/verify-reset-code', [\App\Http\Controllers\Auth\PasswordResetController::class, 'verify'])->middleware('throttle:10,1')->name('password.verify.store');
    Route::get('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1,google-auth')->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1,google-callback')->name('auth.google.callback');
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/register', fn () => redirect(route('home').'#how-it-works'))->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.store');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
 Route::get('/my-requests',[TreatmentRequestController::class,'index'])->name('requests.index');
 Route::get('/requests/profile',[TreatmentRequestController::class,'profile'])->name('requests.profile');
 Route::get('/requests/profile',[TreatmentRequestController::class,'profile'])->name('requests.profile');
 Route::post('/requests',[TreatmentRequestController::class,'store'])->middleware('throttle:10,1,request-submit')->name('requests.store');
 Route::get('/request-photos/{photo}',[TreatmentRequestController::class,'photo'])->name('requests.photos.show');
});

Route::middleware('auth')->group(function () {
 Route::get('/chat/session',[\App\Http\Controllers\ChatController::class,'session'])->name('chat.session');
 Route::get('/chat/bookings',[\App\Http\Controllers\BookingChatController::class,'index'])->name('chat.bookings.index');
 Route::get('/chat/bookings/{booking}/messages',[\App\Http\Controllers\BookingChatController::class,'messages'])->name('chat.bookings.messages');
 Route::post('/chat/bookings/{booking}/messages',[\App\Http\Controllers\BookingChatController::class,'store'])->middleware('throttle:30,1,booking-chat')->name('chat.bookings.store');
 Route::get('/chat/messages',[\App\Http\Controllers\ChatController::class,'index'])->name('chat.index');
 Route::post('/chat/read',[\App\Http\Controllers\ChatController::class,'read'])->name('chat.read');
 Route::post('/chat/messages',[\App\Http\Controllers\ChatController::class,'store'])->middleware('throttle:30,1,chat')->name('chat.store');
});

Route::middleware('auth')->prefix('account')->group(function () {
 Route::get('/settings',[\App\Http\Controllers\AccountSettingsController::class,'index'])->name('account.settings');
 Route::patch('/settings',[\App\Http\Controllers\AccountSettingsController::class,'update'])->middleware('throttle:20,1,account-settings');
 Route::post('/sessions/sign-out-others',[\App\Http\Controllers\AccountSettingsController::class,'sessions'])->middleware('throttle:5,1,account-sessions');
 Route::post('/deletion-request',[\App\Http\Controllers\AccountSettingsController::class,'requestDeletion'])->middleware('throttle:5,1,account-deletion');
 Route::delete('/deletion-request',[\App\Http\Controllers\AccountSettingsController::class,'cancelDeletion']);
});

Route::patch('/account/profile',[\App\Http\Controllers\ProfileController::class,'update'])->middleware(['auth','throttle:20,1,profile-update'])->name('account.profile.update');

Route::post('/bookings/{booking}/treatment-deposit', [\App\Http\Controllers\HospitalDashboardController::class,'treatmentDeposit'])->middleware(['auth','throttle:10,1'])->name('bookings.treatment-deposit');

Route::post('/admin/hospitals/{hospital}/deposit-policy', [\App\Http\Controllers\AdminPaymentController::class,'depositPolicy'])->middleware('auth')->name('admin.hospitals.deposit-policy');

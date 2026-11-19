<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebsiteLanguageController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['language' => ['required', Rule::in(['en', 'th', 'ko'])]]);
        $locale = $data['language'];
        if ($user = $request->user()) {
            $user->preferred_language = $locale;
            $user->save();
        }
        $request->session()->put('website_locale', $locale);
        $request->session()->put('admin_portal_locale', $locale);
        app()->setLocale($locale);

        $previous = Request::create(url()->previous());
        if ($previous->getSchemeAndHttpHost() !== $request->getSchemeAndHttpHost()) {
            $previous = Request::create(route('home'));
        }
        $returnUrl = $previous->query->has('signature')
            ? $previous->fullUrl()
            : $previous->fullUrlWithQuery(['lang' => $locale]);
        $response = $request->expectsJson()
            ? response()->json(['language' => $locale])
            : redirect()->to($returnUrl);

        return $response->cookie('website_language', $locale, 525600)
            ->header('Cache-Control', 'private, no-store');
    }
}

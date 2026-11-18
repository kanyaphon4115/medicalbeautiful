<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
class SetWebsiteLocale {
 public function handle(Request $request, Closure $next) {
  $selected=$request->query('lang');
  if(in_array($selected,['en','th','ko'],true)){
   $request->session()->put('website_locale',$selected);$request->session()->put('admin_portal_locale',$selected);
  }
  $locale=$request->session()->get('website_locale') ?? $request->user()?->preferred_language ?? $request->cookie('website_language','en');
  App::setLocale(in_array($locale,['en','th','ko'],true)?$locale:'en');
  return $next($request);
 }
}

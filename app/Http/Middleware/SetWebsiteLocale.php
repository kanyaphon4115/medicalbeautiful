<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
class SetWebsiteLocale {
 public function handle(Request $request, Closure $next) {
  $locale=$request->user()?->preferred_language ?? $request->session()->get('website_locale',$request->cookie('website_language','en'));
  App::setLocale(in_array($locale,['en','th','ko'],true)?$locale:'en');
  return $next($request);
 }
}

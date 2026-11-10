<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class AccountSettingsController extends Controller {
 public function index(Request $request) {
  $user=$request->user();
  return response()->json([
   'notifications'=>$user->notification_preferences ?? ['requests'=>true,'quotations'=>true,'messages'=>true],
   'language'=>$user->preferred_language,
   'google_connected'=>filled($user->google_id),'google_email'=>filled($user->google_id)?$user->email:null,
   'deletion_requested_at'=>$user->account_deletion_requested_at?->toIso8601String(),
   'sessions'=>DB::table('sessions')->where('user_id',$user->id)->orderByDesc('last_activity')->get(['id','user_agent','last_activity'])->map(fn($session)=>['current'=>$session->id===$request->session()->getId(),'device'=>Str::limit($session->user_agent ?: 'Unknown device',110),'last_active'=>date(DATE_ATOM,$session->last_activity)])->values(),
  ])->header('Cache-Control','private, no-store');
 }
 public function update(Request $request) {
  $data=$request->validate(['notifications'=>['required','array:requests,quotations,messages'],'notifications.requests'=>['required','boolean'],'notifications.quotations'=>['required','boolean'],'notifications.messages'=>['required','boolean'],'language'=>['required',Rule::in(['en','th','ko'])]]);
  $user=$request->user();$user->notification_preferences=$data['notifications'];$user->preferred_language=$data['language'];$user->save();
  $request->session()->put('website_locale',$data['language']);
  return response()->json(['message'=>'Your account preferences have been saved.'])->cookie('website_language',$data['language'],525600);
 }
 public function sessions(Request $request) {
  DB::table('sessions')->where('user_id',$request->user()->id)->where('id','!=',$request->session()->getId())->delete();
  $user=$request->user();$user->remember_token=Str::random(60);$user->save();
  return response()->json(['message'=>'Other sessions have been signed out. This session remains active.']);
 }
 public function requestDeletion(Request $request) {
  $request->validate(['confirmation'=>['required',Rule::in(['DELETE'])]]);
  $user=$request->user();if(!$user->account_deletion_requested_at){$user->account_deletion_requested_at=now();$user->save();}
  return response()->json(['message'=>'Your account deletion request has been recorded for review. Your account and data have not been deleted.']);
 }
 public function cancelDeletion(Request $request) {
  $user=$request->user();$user->account_deletion_requested_at=null;$user->save();
  return response()->json(['message'=>'Your account deletion request has been cancelled.']);
 }
}

<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;

class AdminCustomerController extends Controller {
 public function pageData(Request $request):array {
  abort_unless($request->user()->isAdmin(),403);
  $filters=$request->validate(['search'=>'nullable|string|max:200','country'=>'nullable|string|max:20','status'=>'nullable|in:active,pending,deletion_requested','size'=>'nullable|in:5,10,20','page'=>'nullable|integer|min:1']);
  $query=User::where('role',User::ROLE_USER)->select(['id','name','email','phone_number','country_code','created_at','email_verified_at','account_deletion_requested_at']);
  if($request->filled('search')){
   $term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$filters['search']).'%';
   $query->where(function($q)use($filters,$term){$q->where('name','like',$term)->orWhere('email','like',$term);$id=preg_replace('/^C-0*/i','',$filters['search']);if(ctype_digit($id))$q->orWhere('id',(int)$id);});
  }
  if($request->filled('country'))$filters['country']==='unrecorded'?$query->where(fn($q)=>$q->whereNull('country_code')->orWhere('country_code','')):$query->where('country_code',$filters['country']);
  match($filters['status']??''){
   'active'=>$query->whereNotNull('email_verified_at')->whereNull('account_deletion_requested_at'),
   'pending'=>$query->whereNull('email_verified_at')->whereNull('account_deletion_requested_at'),
   'deletion_requested'=>$query->whereNotNull('account_deletion_requested_at'),default=>null,
  };
  $customers=$query->withCount(['treatmentRequests','bookings'])->with([
   'treatmentRequests'=>fn($q)=>$q->select(['id','user_id','procedure','procedures','status','created_at'])->latest()->limit(5),
   'bookings'=>fn($q)=>$q->select(['id','user_id','status','deposit_status','created_at'])->latest()->limit(5),
  ])->latest('id')->paginate(5)->withQueryString();
  return ['customers'=>$customers,'customerFilters'=>$filters,'customerCountries'=>User::where('role',User::ROLE_USER)->whereNotNull('country_code')->where('country_code','!=','')->distinct()->orderBy('country_code')->pluck('country_code')];
 }
}

<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProfileController extends Controller {
 public function update(Request $request) {
  $data=$request->validate([
   'name'=>['required','string','max:80'],
   'phone'=>['bail','nullable','string','max:30',function($attribute,$value,$fail){if(!preg_match('/^[+0-9 ()-]+$/',$value) || !preg_match('/^\+[1-9]\d{6,14}$/',preg_replace('/[ ()-]/','',$value)))$fail(__('Enter a valid international phone number starting with + and containing 7 to 15 digits.'));}],
   'line_id'=>['nullable','string','max:100'],
   'date_of_birth'=>['nullable','date_format:Y-m-d','before_or_equal:today','after_or_equal:'.now()->subYears(120)->format('Y-m-d')],
   'gender'=>['nullable',Rule::in(['Female','Male'])],
   'country'=>['nullable',Rule::in(config('profile.countries'))],
   'language'=>['required',Rule::in(['en','th','ko'])],
  ],['name.required'=>__('Please enter a full name between 1 and 80 characters.'),'name.max'=>__('Please enter a full name between 1 and 80 characters.'),'country.in'=>__('Please select a valid country or region.'),'language.in'=>__('Please select a supported language.')]);
  $user=$request->user();
  $user->name=$data['name'];
  foreach(['line_id','date_of_birth','gender'] as $field)if(array_key_exists($field,$data))$user->{$field}=$data[$field];
  $user->phone_number=filled($data['phone']??null)?preg_replace('/[ ()-]/','',$data['phone']):null;
  $user->country_code=$data['country']??null;
  $user->preferred_language=$data['language'];
  $user->save();
  $request->session()->put('website_locale',$data['language']);
  return response()->json(['message'=>__('Profile updated successfully.'),'profile'=>['name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone_number??'','country'=>$user->country_code??'','language'=>$user->preferred_language]])->header('Cache-Control','private, no-store')->cookie('website_language',$data['language'],525600);
 }
}

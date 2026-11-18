<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PatientReview extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['photo_consent'=>'boolean','anonymous'=>'boolean','published_at'=>'datetime','reviewed_at'=>'datetime','rating'=>'integer'];}
 public function scopePublished($query){return $query->where('status','published')->whereNotNull('published_at')->where('published_at','<=',now());}
 public function customer(){return $this->belongsTo(User::class,'user_id');}
 public function booking(){return $this->belongsTo(Booking::class);}
 public function photos(){return $this->hasMany(PatientReviewPhoto::class)->orderBy('id');}
 public function getMaskedNameAttribute():string{return $this->anonymous?'Anonymous patient':mb_substr($this->customer?->name??'Patient',0,1).'***';}
 public function getHasPhotosAttribute():bool{return $this->photo_consent && (($this->before_path && $this->after_path)||$this->photos->isNotEmpty());}
 public function getGalleryAttribute(){
  $gallery=$this->photos->map(fn($photo)=>['url'=>route('reviews.gallery-photo',[$this,$photo]),'moderation_url'=>route('reviews.moderation-gallery-photo',[$this,$photo])]);
  foreach(['before','after'] as $side)if($this->{$side.'_path'})$gallery->push(['url'=>route('reviews.photo',[$this,$side]),'moderation_url'=>route('reviews.moderation-photo',[$this,$side])]);
  return $gallery;
 }
 public function publicText(string $text):string {
  foreach(array_filter([$this->customer?->name,$this->customer?->email,$this->booking?->hospital?->name]) as $private)$text=str_ireplace($private,'[private]',$text);
  $text=preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i','[private]',$text);
  $text=preg_replace('/(?:\+?\d[\d\s().-]{7,}\d)/','[private]',$text);
  return $text;
 }
}

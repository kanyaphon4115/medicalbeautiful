<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** Remove ancillary metadata without requiring GD or altering the image pixels. */
class ReviewPhoto
{
 public function clean(UploadedFile $file):array
 {
  $bytes=file_get_contents($file->getRealPath());
  if(str_starts_with($bytes,"\x89PNG\r\n\x1a\n")){
   $out=substr($bytes,0,8);$offset=8;$ended=false;
   while($offset+12<=strlen($bytes)){
    $length=unpack('N',substr($bytes,$offset,4))[1];$type=substr($bytes,$offset+4,4);
    if($length>strlen($bytes)-$offset-12)break;
    if(in_array($type,['IHDR','PLTE','IDAT','IEND','tRNS'],true))$out.=substr($bytes,$offset,$length+12);
    $offset+=$length+12;if($type==='IEND'){$ended=true;break;}
   }
   if($ended)return ['bytes'=>$out,'extension'=>'png'];
  }elseif(str_starts_with($bytes,"\xff\xd8")){
   $out="\xff\xd8";$offset=2;$size=strlen($bytes);
   while($offset<$size){
    // Entropy-coded data may contain stuffed FF00 and restart markers.
    if($bytes[$offset]!=="\xff"){$next=strpos($bytes,"\xff",$offset);if($next===false)break;$out.=substr($bytes,$offset,$next-$offset);$offset=$next;}
    $start=$offset;while($offset<$size && $bytes[$offset]==="\xff")$offset++;
    if($offset>=$size)break;$marker=ord($bytes[$offset++]);
    if($marker===0 || ($marker>=0xd0 && $marker<=0xd7)){$out.=substr($bytes,$start,$offset-$start);continue;}
    if($marker===0xd9)return ['bytes'=>$out."\xff\xd9",'extension'=>'jpg'];
    if($offset+2>$size)break;$length=unpack('n',substr($bytes,$offset,2))[1];
    if($length<2 || $length>$size-$offset)break;
    if(!($marker>=0xe0 && $marker<=0xef) && $marker!==0xfe)$out.=substr($bytes,$start,$offset+$length-$start);
    $offset+=$length;
   }
  }
  throw ValidationException::withMessages(['before'=>'This photo could not be processed. Please choose a valid JPG or PNG image.']);
 }
}

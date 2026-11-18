<?php
return [
 'required'=>':attribute 항목을 입력해 주세요.','accepted'=>':attribute 항목에 동의해 주세요.','email'=>':attribute 항목에 올바른 이메일 주소를 입력해 주세요.',
 'string'=>':attribute 항목은 문자열이어야 합니다.','integer'=>':attribute 항목은 정수여야 합니다.','numeric'=>':attribute 항목은 숫자여야 합니다.',
 'boolean'=>':attribute 항목은 참 또는 거짓이어야 합니다.','array'=>':attribute 항목은 목록이어야 합니다.','image'=>':attribute 항목은 이미지여야 합니다.',
 'mimes'=>':attribute 항목은 :values 형식의 파일이어야 합니다.','mimetypes'=>':attribute 항목은 :values 형식이어야 합니다.',
 'max'=>['string'=>':attribute 항목은 :max자 이하여야 합니다.','numeric'=>':attribute 항목은 :max 이하여야 합니다.','file'=>':attribute 항목은 :max KB 이하여야 합니다.','array'=>':attribute 항목은 :max개 이하여야 합니다.'],
 'min'=>['string'=>':attribute 항목은 :min자 이상이어야 합니다.','numeric'=>':attribute 항목은 :min 이상이어야 합니다.','file'=>':attribute 항목은 :min KB 이상이어야 합니다.','array'=>':attribute 항목은 :min개 이상이어야 합니다.'],
 'between'=>['numeric'=>':attribute 항목은 :min~:max 사이여야 합니다.','string'=>':attribute 항목은 :min~:max자여야 합니다.'],
 'in'=>'선택한 :attribute 항목이 올바르지 않습니다.','exists'=>'선택한 :attribute 항목을 찾을 수 없습니다.','unique'=>':attribute 항목이 이미 사용 중입니다.','distinct'=>':attribute 항목이 중복되었습니다.',
 'uuid'=>':attribute 항목은 올바른 UUID여야 합니다.','date'=>':attribute 항목은 올바른 날짜여야 합니다.','date_format'=>':attribute 항목은 :format 형식이어야 합니다.',
 'dimensions'=>':attribute 이미지 크기가 올바르지 않습니다.','confirmed'=>':attribute 확인 값이 일치하지 않습니다.',
 'required_with'=>':values 항목이 있으면 :attribute 항목을 입력해야 합니다.','required_if'=>':other 항목이 :value이면 :attribute 항목을 입력해야 합니다.',
 'prohibited'=>':attribute 항목을 입력할 수 없습니다.','prohibited_unless'=>'이 경우 :attribute 항목을 입력할 수 없습니다.',
 'attributes'=>['rating'=>'평점','body'=>'리뷰 내용','photo'=>'사진','photo_ids'=>'선택한 사진','privacy_consent'=>'리뷰 공개 동의','photo_consent'=>'사진 공개 동의','email'=>'이메일','password'=>'비밀번호','name'=>'이름','search'=>'검색어','status'=>'상태','country'=>'국가','from'=>'시작일','to'=>'종료일','notes'=>'메모','booking_id'=>'예약'],
];

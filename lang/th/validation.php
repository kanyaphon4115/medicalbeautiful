<?php
return [
 'required'=>'กรุณากรอก:attribute','accepted'=>'กรุณายอมรับ:attribute','email'=>':attributeต้องเป็นอีเมลที่ถูกต้อง',
 'string'=>':attributeต้องเป็นข้อความ','integer'=>':attributeต้องเป็นจำนวนเต็ม','numeric'=>':attributeต้องเป็นตัวเลข',
 'boolean'=>':attributeต้องเป็นค่าจริงหรือเท็จ','array'=>':attributeต้องเป็นรายการ','image'=>':attributeต้องเป็นรูปภาพ',
 'mimes'=>':attributeต้องเป็นไฟล์ประเภท :values','mimetypes'=>':attributeต้องเป็นไฟล์ประเภท :values',
 'max'=>['string'=>':attributeต้องมีไม่เกิน :max ตัวอักษร','numeric'=>':attributeต้องไม่เกิน :max','file'=>':attributeต้องมีขนาดไม่เกิน :max KB','array'=>':attributeต้องมีไม่เกิน :max รายการ'],
 'min'=>['string'=>':attributeต้องมีอย่างน้อย :min ตัวอักษร','numeric'=>':attributeต้องไม่น้อยกว่า :min','file'=>':attributeต้องมีขนาดอย่างน้อย :min KB','array'=>':attributeต้องมีอย่างน้อย :min รายการ'],
 'between'=>['numeric'=>':attributeต้องอยู่ระหว่าง :min ถึง :max','string'=>':attributeต้องมี :min ถึง :max ตัวอักษร'],
 'in'=>':attributeที่เลือกไม่ถูกต้อง','exists'=>'ไม่พบ:attributeที่เลือก','unique'=>':attributeนี้ถูกใช้แล้ว','distinct'=>':attributeซ้ำกัน',
 'uuid'=>':attributeต้องเป็น UUID ที่ถูกต้อง','date'=>':attributeต้องเป็นวันที่ที่ถูกต้อง','date_format'=>':attributeต้องมีรูปแบบ :format',
 'dimensions'=>':attributeมีขนาดรูปภาพไม่ถูกต้อง','confirmed'=>'การยืนยัน:attributeไม่ตรงกัน',
 'required_with'=>'กรุณาระบุ:attributeเมื่อมี :values','required_if'=>'กรุณาระบุ:attributeเมื่อ :other เป็น :value',
 'prohibited'=>'ไม่สามารถระบุ:attributeได้','prohibited_unless'=>'ไม่สามารถระบุ:attributeได้ในกรณีนี้',
 'attributes'=>['rating'=>'คะแนน','body'=>'ข้อความรีวิว','photo'=>'รูปภาพ','photo_ids'=>'รูปที่เลือก','privacy_consent'=>'ความยินยอมเผยแพร่รีวิว','photo_consent'=>'ความยินยอมเผยแพร่รูป','email'=>'อีเมล','password'=>'รหัสผ่าน','name'=>'ชื่อ','search'=>'คำค้น','status'=>'สถานะ','country'=>'ประเทศ','from'=>'วันที่เริ่มต้น','to'=>'วันที่สิ้นสุด','notes'=>'หมายเหตุ','booking_id'=>'การจอง'],
];

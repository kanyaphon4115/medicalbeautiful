import test from 'node:test';
import assert from 'node:assert/strict';
import {otpDigits,distributeOtp} from '../../resources/js/guest-verification.js';
test('OTP paste accepts six digits and fills all boxes from the start',()=>{
 const fields=Array.from({length:6},()=>({value:''}));
 assert.equal(distributeOtp(fields,'Code: 12 34 56',3),5);assert.equal(fields.map(f=>f.value).join(''),'123456');
});
test('partial paste starts at current box and rejects non-digit content',()=>{
 const fields=Array.from({length:6},()=>({value:''}));
 assert.equal(distributeOtp(fields,'a98b',2),4);assert.equal(fields[2].value,'9');assert.equal(fields[3].value,'8');
 assert.equal(otpDigits('12x34-5678'),'123456');assert.equal(otpDigits('letters'),'');
});

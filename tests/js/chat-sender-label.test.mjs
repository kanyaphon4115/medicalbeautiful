import test from 'node:test';
import assert from 'node:assert/strict';
import {chatSenderLabel} from '../../resources/js/chat-sender-label.js';
test('hospital replies show the selected hospital and booking admins are never labeled Customer Support',()=>{
 assert.equal(chatSenderLabel({channel:'hospital',senderType:'hospital',hospitalName:'Selected Clinic'}),'Selected Clinic');
 assert.equal(chatSenderLabel({channel:'hospital',senderType:'admin'}),'Booking Admin');
 assert.equal(chatSenderLabel({channel:'hospital',senderType:'support'}),'Booking Admin');
});
test('customer support retains its separate team label and customer messages remain their own',()=>{
 assert.equal(chatSenderLabel({channel:'support',senderType:'support'}),'Support team');
 assert.equal(chatSenderLabel({channel:'hospital',senderType:'customer',own:true}),'You');
 assert.equal(chatSenderLabel({channel:'hospital',senderType:'customer'}),'Customer');
});

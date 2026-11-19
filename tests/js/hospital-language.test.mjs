import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync,readdirSync} from 'node:fs';
import {translateText} from '../../resources/js/i18n.js';

test('every literal hospital Blade translation exists in both server and browser catalogs',()=>{
 const root=new URL('../../',import.meta.url);
 const views=readdirSync(new URL('resources/views/hospital/',root),{recursive:true}).filter(file=>file.endsWith('.blade.php'));
 const keys=new Set(['Dashboard','Patient Requests','New Requests','Quotations Sent','Confirmed Bookings','Completed Bookings','Procedure','Amount','Valid until','Details','Status','Actions','Preferred Date','Booking Status','Deposit Status','Quotation Sent','Sent','Withdrawn','Pending Payment','Awaiting Hospital Confirmation','Confirmed','Completed','Paid','Pending Verification','Failed','Expired','Refunded','Information Required','No records available. This feature is not connected yet.']);
 for(const file of views){
  const source=readFileSync(new URL('resources/views/hospital/'+file,root),'utf8');
  for(const match of source.matchAll(/__\(\s*(['"])(.*?)\1\s*[,)]/g))keys.add(match[2]);
 }
 for(const locale of ['th','ko']){
  const catalog=JSON.parse(readFileSync(new URL('lang/'+locale+'.json',root),'utf8'));
  for(const key of keys){assert.ok(catalog[key],`${locale}: missing ${key}`);assert.notEqual(catalog[key],key,`${locale}: ${key}`);assert.notEqual(translateText(key,locale),key,`${locale}: browser ${key}`);}
 }
});

test('hospital chat, upload controls and multi-procedure labels translate in both languages',()=>{
 for(const locale of ['th','ko']){
  for(const key of ['Booking Chats','Search conversations...','Customer information','Booking Details','Selected hospital image','Remove','Replace','No messages yet. Start a conversation.','Message could not be sent.'])assert.notEqual(translateText(key,locale),key,`${locale}: ${key}`);
  assert.equal(translateText('Eye Surgery, Rhinoplasty',locale),[translateText('Eye Surgery',locale),translateText('Rhinoplasty',locale)].join(', '));
 }
});

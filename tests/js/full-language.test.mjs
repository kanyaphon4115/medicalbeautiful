import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {translateText} from '../../resources/js/i18n.js';
const json=file=>JSON.parse(readFileSync(new URL('../../lang/'+file,import.meta.url),'utf8'));
test('Thai hospital menu and interface translations contain real Unicode instead of lost characters',()=>{
 const thai=json('th.json'),ui=json('interface.json');
 assert.equal(thai['Hospital Verification'],'ตรวจสอบโรงพยาบาล');
 assert.equal(ui['Hospital Verification'][0],thai['Hospital Verification']);
 for(const [key,value] of Object.entries(thai))assert.ok(!/\?{2,}/.test(value),key);
 for(const [key,values] of Object.entries(ui))assert.ok(!/\?{2,}/.test(values[0]),key);
});

test('customer language dictionaries have no damaged Korean entries or missing customer menu labels',()=>{
 const korean=json('ko.json'),ui=json('interface.json');
 for(const [key,value] of Object.entries(korean))assert.ok(!/\?{2,}/.test(value),key);
 for(const [key,values] of Object.entries(ui))assert.ok(!/\?{2,}/.test(values[1]),key);
 for(const locale of ['th','ko'])for(const label of ['My Dashboard','My Quotations','My Chats','Direct Communication,','Various Opinions & Comparison','Booking Chat','Verify Your Email','View My Bookings'])assert.notEqual(translateText(label,locale),label,`${locale}: ${label}`);
});

test('native validation uses the selected language and clears its custom error when the field is edited',()=>{
 const source=readFileSync(new URL('../../resources/js/i18n.js',import.meta.url),'utf8').replace(/^import .*;\r?\n/gm,'').replace('export function','function');
 for(const locale of ['th','ko']){
  const handlers={};
  class Element{closest(){return null;}querySelectorAll(){return [];}getAttribute(){return null;}}
  const body=new Element();
  vm.runInNewContext(source,{thai:json('th.json'),korean:json('ko.json'),admin:json('admin-portal.json'),ui:json('interface.json'),koreanDynamic:()=>undefined,Element,NodeFilter:{SHOW_TEXT:4},MutationObserver:class{observe(){}disconnect(){}},document:{documentElement:{lang:locale},body,title:'AESTHETIC',addEventListener(name,handler){handlers[name]=handler;},createTreeWalker(){return {nextNode:()=>null};}}});
  const field={type:'text',tagName:'INPUT',validity:{valueMissing:true},validationMessage:'Please fill out this field.',value:'',setCustomValidity(message){this.validationMessage=message;}};
  handlers.invalid({target:field});
  assert.equal(field.validationMessage,json(locale+'.json')['Please fill out this field.']);
  field.value='My own text';handlers.input({target:field});
  assert.equal(field.validationMessage,'');assert.equal(field.value,'My own text');
 }
});

test('new admin, payment, chat and review interfaces are translated in both supported languages',()=>{
 for(const locale of ['th','ko'])for(const label of ['Chat Management','Customer Support Chats','Hospital Chats (Read Only)','Database records','Pending verification','Initial Deposit','Booking Deposit','Amount to Verify (not yet paid)','Overall Rating','Public Review','+ Add Photos','Submit Review','Review posted','Apply filters']){
  assert.notEqual(translateText(label,locale),label,locale+' '+label);
 }
 assert.match(translateText('Showing 1–5 of 13 customers','th'),/13/);
 assert.equal(translateText('5 / page','th'),'5 / หน้า');
});

test('DOM translation preserves customer content, textarea values and the original values of filter options',()=>{
 const source=readFileSync(new URL('../../resources/js/i18n.js',import.meta.url),'utf8').replace(/^import .*;\r?\n/gm,'').replace('export function','function');
 class Element{
  constructor(tag,attributes={}){this.tag=tag;this.attributes=attributes;}
  closest(selector){if(selector==='option')return this.tag==='option'?this:null;if(selector==='textarea')return this.tag==='textarea'?this:null;
   return (this.attributes['data-user-content']!==undefined||this.attributes['data-no-translate']!==undefined)?this:null;}
  querySelectorAll(){return this.elements||[];}hasAttribute(key){return key in this.attributes;}getAttribute(key){return this.attributes[key]??null;}setAttribute(key,value){this.attributes[key]=value;}
 }
 const body=new Element('body'),option=new Element('option'),label=new Element('label'),name=new Element('strong',{'data-user-content':''}),textarea=new Element('textarea');
 const nodes=[{parentElement:option,data:'Pending'},{parentElement:label,data:'Overall Rating'},{parentElement:name,data:'Pending'},{parentElement:textarea,data:'My own text'}];
 Object.defineProperty(option,'value',{get(){return option.attributes.value??nodes[0].data;}});
 const placeholder=new Element('input',{placeholder:'Write your review (optional)'});body.elements=[placeholder];
 vm.runInNewContext(source,{thai:json('th.json'),korean:json('ko.json'),admin:json('admin-portal.json'),ui:json('interface.json'),koreanDynamic:()=>undefined,
  Element,NodeFilter:{SHOW_TEXT:4},MutationObserver:class{observe(){}disconnect(){}},document:{documentElement:{lang:'th'},body,title:'Patient Reviews | AESTHETIC',addEventListener(){},createTreeWalker(){let index=0;return{nextNode:()=>nodes[index++]};}}});
 assert.notEqual(nodes[0].data,'Pending');assert.equal(option.value,'Pending');assert.equal(nodes[1].data,'คะแนนโดยรวม');
 assert.equal(nodes[2].data,'Pending');assert.equal(nodes[3].data,'My own text');assert.equal(placeholder.attributes.placeholder,'เขียนรีวิวของคุณ (ไม่จำเป็นต้องกรอก)');
});

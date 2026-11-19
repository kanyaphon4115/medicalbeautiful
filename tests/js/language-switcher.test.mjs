import test from 'node:test';
import assert from 'node:assert/strict';
import {languageUrl,initializeLanguageSwitcher} from '../../resources/js/language-switcher.js';

test('switching language keeps filters, page numbers, private hashes and the current origin',()=>{
 const url=new URL(languageUrl('http://192.168.1.144/medicalbeautiful/public/my-requests?lang=en&filter=confirmed&page=2#quotes','ko'));
 assert.equal(url.searchParams.get('lang'),'ko');
 assert.equal(url.searchParams.get('filter'),'confirmed');
 assert.equal(url.searchParams.get('page'),'2');
 assert.equal(url.hash,'#quotes');
 assert.equal(url.origin,'http://192.168.1.144');
});

test('signed private links retain their original query string and signature',()=>{
 const url='http://localhost/guest-requests/private/access?expires=123&signature=abc#booking-deposit';
 assert.equal(languageUrl(url,'th'),url);
});

test('selection saves the preference before navigating; failure leaves the current page and language intact',async()=>{
 const original={document:globalThis.document,FormData:globalThis.FormData,fetch:globalThis.fetch,location:globalThis.location};
 const handlers={},feedback={hidden:true},select={value:'th',disabled:false,addEventListener(name,fn){handlers[name]=fn;}},form={action:'/language',querySelector:s=>s==='select'?select:feedback,setAttribute(){},removeAttribute(){},addEventListener(name,fn){handlers[name]=fn;}};
 let navigated,received;
 globalThis.document={documentElement:{lang:'en'}};
 globalThis.FormData=class{constructor(value){assert.equal(value,form);assert.equal(select.disabled,false);}};
 globalThis.location={href:'http://localhost/my-requests?page=2#quotes',assign:url=>{navigated=url;}};
 try{
  globalThis.fetch=async(url,options)=>{received={url,options};return {ok:true};};
  initializeLanguageSwitcher(form);await handlers.change({preventDefault(){}});
  assert.equal(received.url,'/language');assert.equal(received.options.method,'POST');assert.equal(received.options.credentials,'same-origin');
  assert.equal(new URL(navigated).searchParams.get('lang'),'th');
  navigated=undefined;select.disabled=false;select.value='ko';
  globalThis.fetch=async()=>({ok:false});initializeLanguageSwitcher(form);
  await handlers.change({preventDefault(){}});
  assert.equal(navigated,undefined);assert.equal(select.value,'en');assert.equal(select.disabled,false);assert.equal(feedback.hidden,false);
 }finally{for(const [key,value] of Object.entries(original)){if(value===undefined)delete globalThis[key];else globalThis[key]=value;}}
});

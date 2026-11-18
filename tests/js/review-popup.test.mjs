import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
import {createReviewPhotoQueue} from '../../resources/js/review-photo-queue.js';

const source=readFileSync(new URL('../../resources/js/patient-reviews.js',import.meta.url),'utf8').replace(/^import .*;\r?\n/gm,'');
function harness(){
 const node=()=>({listeners:{},children:[],hidden:false,disabled:false,value:'',textContent:'',attributes:{},append(...nodes){this.children.push(...nodes);},replaceChildren(){this.children=[];},querySelectorAll(){return[];},click(){this.clicked=true;},
  classList:{values:new Set(),add(key){this.values.add(key);},remove(key){this.values.delete(key);},toggle(key,on){if(on)this.values.add(key);else this.values.delete(key);}},
  addEventListener(key,fn){this.listeners[key]=fn;},focus(){this.focused=true;},removeAttribute(key){delete this.attributes[key];}});
 const feedback=node(),output=node(),submit=node(),permission=node(),consent=node(),form=node();
 const photoInput=node(),gallery=node();photoInput.files=[];photoInput.dataset={uploadUrl:'/patient-review-photos'};
 permission.querySelector=()=>consent;
 const stars=[1,2,3,4,5].map(value=>{const input=node(),label=node();input.value=String(value);input.checked=value===5;input.closest=()=>label;return input;});
 const uploads=['before','after'].map(side=>{
  const box=node(),input=node(),preview=node(),actions=node(),replace=node(),remove=node();input.files=[];preview.hidden=true;actions.hidden=true;
  box.querySelector=selector=>({'input':input,'[data-upload-preview]':preview,'.pr-upload-actions':actions,'[data-upload-replace]':replace,'[data-upload-remove]':remove}[selector]);
  input.click=()=>{input.clicked=true;};return {box,input,preview,actions,replace,remove,side};
 });
 const close=node(),dialog=node();dialog.open=false;dialog.showModal=()=>{dialog.open=true;};dialog.close=()=>{dialog.open=false;dialog.listeners.close();};dialog.hasAttribute=()=>false;
 dialog.querySelector=selector=>({'[data-review-form]':form,'[data-review-feedback]':feedback,'[data-review-rating]':output,'[data-photo-permission]':permission,'[data-review-photos]':photoInput,'[data-review-photo-gallery]':gallery}[selector]);
 dialog.querySelectorAll=selector=>selector==='[data-review-close]'?[close]:selector==='input[name=rating]'?stars:selector==='[data-review-upload]'?uploads.map(u=>u.box):[];
 form.action='/patient-reviews';form.reportValidity=()=>true;form.querySelector=()=>submit;form.querySelectorAll=()=>stars;
 const openers=[node(),node()];openers.forEach(button=>{button.href='http://localhost/patient-reviews/write';button.matches=()=>false;});
 let posts=0,resolvePost;
 const body={style:{overflow:'auto'}},revoked=[];
 const url=class extends URL{static createObjectURL(){return 'blob:preview';}static revokeObjectURL(value){revoked.push(value);}};
 const context={createReviewPhotoQueue,URL:url,FormData:class{append(){}},location:{href:'http://localhost/patient-reviews'},document:{body,createElement:()=>node(),
  querySelector:selector=>selector==='[data-review-dialog]'?dialog:selector==='meta[name=csrf-token]'?{content:'csrf'}:null,
  querySelectorAll:()=>openers},fetch:async()=>{posts++;return new Promise(resolve=>{resolvePost=resolve;});}};
 vm.runInNewContext(source,context);
 return{dialog,form,openers,close,body,stars,output,uploads,photoInput,gallery,permission,consent,feedback,submit,revoked,posts:()=>posts,
  finish:()=>resolvePost({ok:true,json:async()=>({message:'Review posted'})})};
}
test('both Write a Review links open the same modal and Cancel restores scrolling and focus',()=>{
 const page=harness();
 for(const opener of page.openers){let prevented=false;opener.listeners.click({preventDefault(){prevented=true;},currentTarget:opener});
  assert.equal(prevented,true);assert.equal(page.dialog.open,true);assert.equal(page.body.style.overflow,'hidden');
  page.close.listeners.click();assert.equal(page.dialog.open,false);assert.equal(page.body.style.overflow,'auto');assert.equal(opener.focused,true);
 }
});
test('star selection supports ratings from one to five and updates the numeric rating',()=>{
 const page=harness();for(const rating of [1,3,5]){page.stars.forEach(star=>star.checked=Number(star.value)===rating);page.stars[rating-1].listeners.change();
  assert.equal(page.output.value,rating.toFixed(1)+' / 5');assert.equal(page.stars[4].closest().classList.values.has('is-empty'),rating<5);
 }
});
test('general photos have previews and individual replacement and removal',()=>{
 const page=harness();page.photoInput.files=[{type:'image/png',size:500,name:'photo.png'}];page.photoInput.listeners.change();
 const figure=page.gallery.children[0];assert.equal(figure.children[0].src,'blob:preview');
 figure.children[2].children[0].listeners.click();assert.equal(figure.children[3].clicked,true);
 figure.children[2].children[1].listeners.click();assert.equal(page.gallery.children.length,0);assert.deepEqual(page.revoked,['blob:preview']);
 page.photoInput.files=[{type:'image/png',size:11*1024*1024}];page.photoInput.listeners.change();assert.match(page.feedback.textContent,/10 MB/);assert.equal(page.gallery.children.length,0);
});

test('photo queue supports repeated additions without a count limit and resets replaced upload references',()=>{
 const queue=createReviewPhotoQueue();for(let i=0;i<101;i++)queue.add([{name:`photo-${i}.png`,type:'image/png',size:100}]);
 assert.equal(queue.entries.length,101);queue.entries[0].uploadedId=42;queue.replace(queue.entries[0].key,{name:'new.jpg',type:'image/jpeg',size:500});assert.equal(queue.entries[0].uploadedId,null);
 queue.remove(queue.entries[0].key);assert.equal(queue.entries.length,100);
 assert.throws(()=>queue.add([{name:'invalid.gif',type:'image/gif',size:100}]),/JPG or PNG/);
});
test('submitting twice while a request is pending saves once and displays the posted state',async()=>{
 const page=harness(),event={preventDefault(){}};
 const pending=page.form.listeners.submit(event);await page.form.listeners.submit(event);assert.equal(page.posts(),1);assert.equal(page.submit.disabled,true);
 page.finish();await pending;assert.equal(page.feedback.textContent,'Review posted');assert.equal(page.submit.textContent,'Review posted');assert.equal(page.submit.disabled,true);
});

import test from 'node:test';
import assert from 'node:assert/strict';
import {pageNumbers,pageItems,createListPager} from '../../resources/js/pagination.js';
test('every page is reachable through a sliding window of at most three numbers',()=>{
 for(let total=2;total<=30;total++){let page=1;const seen=new Set();while(!seen.has(page)){seen.add(page);const numbers=pageNumbers(page,total);assert.equal(numbers.length,Math.min(3,total));assert.ok(numbers.includes(page));page=Math.max(...numbers);}assert.equal(seen.has(total),true);}
 assert.deepEqual(pageNumbers(1,10),[1,2,3]);assert.deepEqual(pageNumbers(6,10),[5,6,7]);assert.deepEqual(pageNumbers(10,10),[8,9,10]);
});
test('records use five per page and clamp after filtering without changing sort order',()=>{
 const records=Array.from({length:27},(_,i)=>i+1);assert.deepEqual(pageItems(records,3).items,[11,12,13,14,15]);assert.deepEqual(pageItems(records,6).items,[26,27]);assert.equal(pageItems(records.slice(0,4),6).page,1);assert.equal(pageItems([],1).pages,1);
});
test('conversation pager retains the page on polling, resets on search and hides for five or fewer',()=>{
 const node=()=>({children:[],attributes:{},classList:{add(){}},setAttribute(k,v){this.attributes[k]=v;},replaceChildren(){this.children=[];},append(item){this.children.push(item);}});
 globalThis.document={createElement:node};let nav;const list={after(value){nav=value;}};const pager=createListPager(list);let records=Array.from({length:26},(_,i)=>i),visible;
 const render=()=>{visible=pager.slice(records,render);};render();assert.equal(nav.children.length,3);assert.equal(nav.hidden,false);nav.children[2].onclick();assert.deepEqual(visible,[10,11,12,13,14]);render();assert.deepEqual(visible,[10,11,12,13,14]);assert.equal(nav.children[1].attributes['aria-current'],'page');
 pager.reset();records=records.slice(0,5);render();assert.equal(nav.hidden,true);assert.deepEqual(visible,[0,1,2,3,4]);delete globalThis.document;
});

test('record containers paginate without hiding headings and reset when filtering changes',async()=>{
 const {initListPagination}=await import('../../resources/js/pagination.js');const observers=[];
 const makeNode=()=>({children:[],attributes:{},dataset:{},hidden:false,style:{},classList:{add(){}},setAttribute(k,v){this.attributes[k]=v;},replaceChildren(){this.children=[];},append(item){this.children.push(item);},matches(){return false;}});
 globalThis.document={createElement:makeNode};globalThis.MutationObserver=class{constructor(callback){observers.push(callback);}observe(){}};
 let nav;const heading={...makeNode(),matches:selector=>selector==='h2'};
 const rows=Array.from({length:16},()=>({...makeNode(),matches:selector=>selector==='article',toggleAttribute(key,value){if(value)this.attributes[key]='';else delete this.attributes[key];}}));
 const list={...makeNode(),tagName:'SECTION',dataset:{paginate:'article'},children:[heading,...rows],closest(){return null;},after(value){nav=value;}};
 initListPagination({querySelectorAll:()=>[list]});const visible=()=>rows.filter(row=>!row.hidden&&!('data-pagination-hidden' in row.attributes));
 assert.equal(visible().length,5);assert.equal(heading.hidden,false);nav.children[2].onclick();assert.equal(visible()[0],rows[10]);
 observers[1]([{target:{parentElement:rows[10]}}]);assert.equal(visible()[0],rows[10]);
 for(const row of rows.slice(0,12))row.hidden=true;observers[1]([{target:{parentElement:list}}]);assert.equal(visible().length,4);assert.equal(nav.hidden,true);assert.equal(visible()[0],rows[12]);
 delete globalThis.document;delete globalThis.MutationObserver;
});

test('secondary dashboard lists and cards reach every record without previous or next controls',async()=>{
 const {initListPagination}=await import('../../resources/js/pagination.js');
 const containers=['.request-hospital-grid','.ap-mini-list','.ap-activity','.ap-approval-list','.ap-rankings','.settings-session-list','.partner-all-doctors','.partner-doctor-grid','.partner-review-grid','.partner-gallery','.pr-photo-gallery','.gj-quotation-options','.gj-request-rows','.gj-notification-list','[data-paginate]'];
 const node=()=>({children:[],dataset:{},style:{},hidden:false,attributes:{},classList:{add(){}},setAttribute(k,v){this.attributes[k]=v;},replaceChildren(){this.children=[];},append(child){this.children.push(child);},matches(){return false;},toggleAttribute(k,on){if(on)this.attributes[k]='';else delete this.attributes[k];}});
 globalThis.document={createElement:node};globalThis.MutationObserver=class{observe(){}};
 try{
  for(const selector of containers){
   let nav;const rows=Array.from({length:27},node);
   const list={...node(),children:rows,tagName:selector==='[data-paginate]'?'TBODY':'DIV',closest(name){return name==='table'?{after(value){nav=value;}}:null;},after(value){nav=value;}};
   initListPagination({querySelectorAll(selectors){assert.ok(selectors.includes(selector),selector);for(const carousel of ['.hospital-track','.procedure-track','.doctor-track'])assert.ok(!selectors.split(',').includes(carousel));return [list];}});
   const seen=new Set();let current=0;
   while(current<6){
    const visible=rows.filter(row=>!('data-pagination-hidden' in row.attributes));
    assert.equal(visible.length,current===5?2:5,selector);
    visible.forEach(row=>seen.add(row));
    assert.ok(nav.children.every(button=>/^\d+$/.test(button.textContent)),selector);
    if(++current<6)nav.children.find(button=>Number(button.textContent)===current+1).onclick();
   }
   assert.equal(seen.size,27,selector);
  }
  const formList={...node(),closest(){return {};}};
  initListPagination({querySelectorAll:()=>[formList]}); // Form controls receive no pager.
 }finally{delete globalThis.document;delete globalThis.MutationObserver;}
});

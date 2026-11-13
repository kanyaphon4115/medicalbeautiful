import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/chat.js',import.meta.url),'utf8').replace(/^import .*;\r?\n/gm,'');
function harness(){
 const nodes=new Map(),calls=[];let state={support_available:false,hospital_requests:[{id:7,label:'Eye Surgery - Clinic',hospital_name:'Clinic',step4_approved:false,step9_approved:false,account_activated:false,booking_confirmed:false,unlocked:false,deposit_url:null}]};
 function element(selector=''){return{value:'',children:[],dataset:{},classList:{add(){},remove(){}},hidden:false,disabled:false,scrollTop:0,scrollHeight:0,clientHeight:0,textContent:'',listeners:{},attributes:{},
  setAttribute(key,value){this.attributes[key]=value;},getAttribute(key){return this.attributes[key];},removeAttribute(key){delete this[key];delete this.attributes[key];},
  addEventListener(event,callback){this.listeners[event]=callback;},focus(){},append(...children){this.children.push(...children);if(selector==='#chat-request-select'&&!this.value&&children[0])this.value=children[0].value;},
  replaceChildren(...children){this.children=children;if(selector==='#chat-request-select')this.value='';}};}
 const get=selector=>{if(selector==='#chat-customer-select')return null;if(!nodes.has(selector))nodes.set(selector,element(selector));return nodes.get(selector);};
 const widget={dataset:{authenticated:'true',support:'false',userId:'1',endpoint:'/chat/messages',accessEndpoint:'/chat/access',sessionEndpoint:'/chat/session'},querySelector:get,querySelectorAll:()=>[get('support-tab'),get('hospital-tab')]};
 const context=vm.createContext({document:{querySelector:selector=>selector==='[data-chat-widget]'?widget:{content:'csrf'},addEventListener(){},hidden:false,createElement:()=>element()},AbortController,URLSearchParams,
  chatSenderLabel:()=>'',attachMessageTranslation(){},createChatPolling:()=>({start(){},stop(){}}),
  fetch:async url=>{calls.push(String(url));return{ok:true,status:200,json:async()=>String(url)==='/chat/access'?structuredClone({user_id:1,...state}):{user_id:1,can_send:true,messages:[],conversations:[],eligible_requests:state.hospital_requests.filter(r=>r.unlocked)}};}});
 vm.runInContext(source,context);
 return{nodes,calls,state,setChannel:channel=>vm.runInContext('channel='+JSON.stringify(channel),context),load:()=>context.load()};
}
test('hospital gate shows real progress and automatically unlocks and relocks without a reload',async()=>{
 const page=harness();page.setChannel('hospital');await page.load();
 assert.equal(page.nodes.get('.chat-hospital-gate').hidden,false);assert.equal(page.nodes.get('[data-chat-step-four]').textContent,'Pending');
 assert.equal(page.calls.some(url=>url.includes('channel=hospital')),false);
 const request=page.state.hospital_requests[0];request.step4_approved=true;request.account_activated=true;request.deposit_url='/my-requests#booking-deposit-42';page.state.support_available=true;
 await page.load();assert.equal(page.nodes.get('[data-chat-step-four]').textContent,'Approved');assert.equal(page.nodes.get('[data-chat-step-nine]').textContent,'Pending');
 assert.equal(page.nodes.get('[data-chat-deposit]').href,'/my-requests#booking-deposit-42');assert.equal(page.nodes.get('#chat-message').disabled,true);
 request.step9_approved=true;request.booking_confirmed=true;request.unlocked=true;await page.load();
 assert.equal(page.nodes.get('.chat-hospital-gate').hidden,true);assert.equal(page.nodes.get('#chat-message').disabled,false);assert.ok(page.calls.some(url=>url.includes('channel=hospital')));
 request.step9_approved=false;request.unlocked=false;await page.load();assert.equal(page.nodes.get('.chat-hospital-gate').hidden,false);assert.equal(page.nodes.get('#chat-message').disabled,true);
});
test('support chat requires initial approval and activation but not booking deposit approval',async()=>{
 const page=harness();await page.load();assert.equal(page.nodes.get('.chat-support-gate').hidden,false);assert.equal(page.nodes.get('#chat-message').disabled,true);
 page.state.hospital_requests[0].step4_approved=true;await page.load();assert.equal(page.nodes.get('.chat-support-gate').hidden,false);
 page.state.support_available=true;page.state.hospital_requests[0].account_activated=true;await page.load();
 assert.equal(page.nodes.get('.chat-support-gate').hidden,true);assert.equal(page.nodes.get('#chat-message').disabled,false);assert.equal(page.state.hospital_requests[0].step9_approved,false);
});

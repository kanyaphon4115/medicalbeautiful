import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../../resources/js/booking-inbox.js',import.meta.url),'utf8').replace(/^import .*;\r?\n/gm,'').replace('export function','function');
function harness(rooms,messages=[],role='hospital'){
 const nodes=new Map(),created=[],calls=[];let pollLoad,initial;
 const element=()=>({value:'',textContent:'',innerHTML:'',hidden:false,disabled:false,children:[],dataset:{},listeners:{},scrollHeight:0,scrollTop:0,clientHeight:0,
  addEventListener(event,fn){this.listeners[event]=fn;},setAttribute(){},append(...children){this.children.push(...children);},replaceChildren(...children){this.children=children;},showModal(){this.open=true;},close(){this.open=false;}});
 const node=selector=>{if(!nodes.has(selector))nodes.set(selector,element());return nodes.get(selector);};
 const root={...element(),isConnected:true,closest:()=>null,querySelector:node};
 const document={hidden:false,addEventListener(){},removeEventListener(){},createElement(){const value=element();created.push(value);return value;},createDocumentFragment:element};
 const fetch=async(url,options={})=>{calls.push({url,options});return{ok:true,status:200,json:async()=>url==='/chat/bookings'?{rooms,role,can_send:role==='hospital'}:{messages}};};
 let posted;
 const context={document,location:{search:''},URLSearchParams,AbortController,fetch,attachMessageTranslation(){},
  createChatPolling:({load})=>{pollLoad=load;return{start(){initial=load();},stop(){}};},
  refreshChatSession:async()=>({csrf_token:'test-token'}),
  postChatMessage:async args=>{posted=args;return{ok:true,status:201,json:async()=>({})};}};
 vm.runInNewContext(source,context);
 const dispose=context.initBookingInbox({root,config:{bookingChatEndpoint:'/chat/bookings',chatSessionEndpoint:'/chat/session',userId:7,locale:'en'},esc:value=>String(value??'').replaceAll('<','&lt;').replaceAll('>','&gt;')});
 return{nodes,created,calls,root,dispose,ready:()=>initial,refresh:()=>pollLoad(),posted:()=>posted};
}
const room={id:42,booking_reference:'BK-2026-000042',request_reference:'REQ-2026-000018',customer_name:'Actual Customer',customer_email:'customer@example.test',hospital_name:'Actual Hospital',procedure:'Eye Surgery',unlocked:true,booking_status:'Confirmed',deposit_status:'Paid',hospital_selected:true,hospital_approved:true,payment_approved:true,account_active:true};
test('live inbox renders server rooms and messages and sends to the selected booking',async()=>{
 const page=harness([room],[{id:1,sender_type:'customer',body:'Actual <message>',sent_at:'2026-11-12T12:00:00Z'}]);await page.ready();
 assert.match(page.nodes.get('#booking-chat-list').innerHTML,/Actual Customer/);assert.doesNotMatch(page.root.innerHTML,/Simulate|Sarah Kim/);
 assert.equal(page.calls[1].url,'/chat/bookings/42/messages');assert.ok(page.created.some(node=>node.innerHTML.includes('Actual &lt;message&gt;')));
 const input=page.nodes.get('#booking-chat-message');assert.equal(input.disabled,false);input.value='Hospital answer';
 await page.nodes.get('#booking-chat-compose').listeners.submit({preventDefault(){}});
 assert.equal(page.posted().endpoint,'/chat/bookings/42/messages');assert.equal(page.posted().payload.body,'Hospital answer');assert.equal(input.value,'');
 page.dispose();
});
test('locked rooms disable sending and do not fetch message history',async()=>{
 const page=harness([{...room,unlocked:false}]);await page.ready();assert.equal(page.calls.length,1);
 assert.equal(page.nodes.get('#booking-chat-message').disabled,true);assert.match(page.nodes.get('#booking-chat-locked').innerHTML,/Booking Chat is locked/);
 page.nodes.get('#booking-chat-message').value='Cannot send';await page.nodes.get('#booking-chat-compose').listeners.submit({preventDefault(){}});
 assert.equal(page.posted(),undefined);page.dispose();
});
test('empty database renders no invented conversations and disposing stops loading',async()=>{
 const page=harness([]);await page.ready();assert.match(page.nodes.get('#booking-chat-list').innerHTML,/No records found/);
 assert.equal(page.nodes.get('#booking-chat-message').disabled,true);page.dispose();const count=page.calls.length;await page.refresh();assert.equal(page.calls.length,count);
});

test('admin can inspect hospital history but cannot send a hospital reply',async()=>{
 const page=harness([room],[], 'admin');await page.ready();
 assert.equal(page.calls[1].url,'/chat/bookings/42/messages');
 assert.equal(page.nodes.get('#booking-chat-message').disabled,true);
 assert.equal(page.nodes.get('#booking-chat-compose').hidden,true);
 assert.equal(page.nodes.get('#booking-chat-readonly').hidden,false);
 page.nodes.get('#booking-chat-message').value='Cannot impersonate hospital';
 await page.nodes.get('#booking-chat-compose').listeners.submit({preventDefault(){}});
 assert.equal(page.posted(),undefined);page.dispose();
});

test('customer support can monitor closed hospital history without a composer',async()=>{
 const page=harness([{...room,unlocked:false,can_view:true}],[{id:9,sender_type:'hospital',body:'Historical hospital reply',sent_at:'2026-11-12T12:00:00Z'}],'support');
 await page.ready();assert.equal(page.calls[1].url,'/chat/bookings/42/messages');
 assert.equal(page.nodes.get('#booking-chat-history').hidden,false);assert.equal(page.nodes.get('#booking-chat-compose').hidden,true);
 assert.equal(page.nodes.get('#booking-chat-message').disabled,true);assert.equal(page.nodes.get('#booking-chat-readonly').hidden,false);page.dispose();
});

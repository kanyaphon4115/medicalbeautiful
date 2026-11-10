import test from 'node:test';
import assert from 'node:assert/strict';
import {guideSteps,howMarkup,protectedJourneyPages,demoAccount,initializeCustomerAccess} from '../../resources/js/customer-access.js';
test('six-step guide is public and explains payment confirmation before email verification',()=>{assert.equal(guideSteps.length,6);const html=howMarkup();assert.equal((html.match(/<li>/g)||[]).length,6);for(const title of ['How It Works','Your Beauty Journey in 6 Simple Steps','Submit Request','Compare & Select Hospital','Deposit Payment & Verification','Verify Your Email','Create Your Account','Customer Dashboard','Get a Free Quote'])assert.ok(html.includes(title));assert.match(html,/payment is confirmed/);assert.match(html,/independently verified/);assert.match(html,/requires a configured payment provider/);});
test('all private demo URLs require authentication; request submission and public provider profiles stay public',()=>{for(const p of ['dashboard','my-requests','my-quotations','my-chats','my-profile','profile','account-settings','settings','chat'])assert.equal(protectedJourneyPages.has(p),true);for(const p of ['new','status','verify','account','how','hospital','doctor'])assert.equal(protectedJourneyPages.has(p),false);const storage={getItem:()=>JSON.stringify({current:'unknown',accounts:[]})};assert.equal(demoAccount(storage),null);});
test('guest menus open Sign In Required; guide opens, closes and starts the existing quote form',()=>{
 const events={},slot={innerHTML:''};const item={dataset:{customerFeature:'My Requests'},classList:{toggle(){}},setAttribute(){},removeAttribute(){}};
 const stored=new Map();globalThis.sessionStorage={getItem:k=>stored.get(k)||null,setItem:(k,v)=>stored.set(k,v)};
 globalThis.location={hash:'',assign(v){this.last=v;}};globalThis.window={addEventListener(){}};
 globalThis.document={body:{dataset:{signedIn:'false'}},querySelector:()=>({dataset:{homeUrl:'http://example.test/'}}),querySelectorAll:s=>s==='[data-demo-account-slot]'?[slot]:[item],addEventListener:(n,f)=>events[n]=f,dispatchEvent:e=>events[e.type]?.()};
 let html='',quotes=0,closed=0;const modal={classList:{add(){},remove(){}},addEventListener(){},close(){closed++;}};
 const access=initializeCustomerAccess({openModal:v=>html=v,content:{},modal,quote:()=>quotes++});
 assert.match(slot.innerHTML,/How It Works/);assert.match(slot.innerHTML,/href="http:\/\/example.test\/login"/);assert.doesNotMatch(slot.innerHTML,/Sign Up/);
 let prevented=false;events.click({target:{closest:s=>s==='[data-customer-feature]'?item:null},preventDefault(){prevented=true;}});assert.equal(prevented,true);assert.match(html,/Sign In Required/);assert.match(html,/Please sign in to access your requests, quotations, and messages./);assert.match(html,/Cancel/);assert.match(html,/href="http:\/\/example.test\/login"/);
 access.how();assert.match(html,/Your Beauty Journey in 6 Simple Steps/);
 events.click({target:{closest:s=>s==='[data-guide-quote]'?{}:null},preventDefault(){}});assert.equal(quotes,1);assert.equal(closed,1);
 stored.set('aesthetic-guest-journey-v1',JSON.stringify({current:'demo@example.test',accounts:[{email:'demo@example.test',name:'Demo Customer'}]}));access.refresh();assert.match(slot.innerHTML,/Demo Customer/);for(const title of ['My Dashboard','My Requests','My Quotations','My Chats','My Profile','Account Settings','Logout'])assert.match(slot.innerHTML,new RegExp(title));
 events.click({target:{closest:s=>s==='[data-customer-feature]'?item:null},preventDefault(){}});assert.match(location.last,/#journey\/dashboard\/My%20Requests/);
});

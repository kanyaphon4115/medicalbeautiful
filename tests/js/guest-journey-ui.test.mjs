import test from 'node:test';
import assert from 'node:assert/strict';
import {browserAdapter,requestData,codeFields} from '../helpers/guest-dom.mjs';
const b=browserAdapter();let mailCalls=0;globalThis.fetch=()=>{mailCalls++;throw Error('No network request is permitted in the demo.');};
const {journey,startGuestJourney}=await import('../../resources/js/guest-journey.js');
test('full interactive demo: request, hospital, separate QR confirmation, email, six-digit OTP, account and returning customer',async()=>{
 startGuestJourney('Eye Surgery');assert.match(b.host.innerHTML,/Let’s begin your/);assert.match(b.host.innerHTML,/Date of Birth/);assert.match(b.host.innerHTML,/value="Eye Surgery" checked/);assert.match(b.host.innerHTML,/Preferred Treatment Month/);
 await b.submit('request',requestData);const r=journey.state.requests[0];assert.match(b.host.innerHTML,/Select a Hospital/);assert.equal(r.quotes.length,2);assert.equal(r.booking,null);assert.equal(mailCalls,0);
 await b.click('select',r.quotes[1].id);assert.equal(r.selectedQuote,r.quotes[1].id);assert.match(b.host.innerHTML,/THB 3,000/);
 await b.click('checkout',r.quotes[1].id);assert.match(b.host.innerHTML,/Complete Your Deposit Payment/);assert.match(b.host.innerHTML,/NOT SCANNABLE/);const ref=r.payment.reference;
 await b.click('payment-claim');assert.equal(r.booking,null);assert.equal(r.otp,undefined);assert.equal(r.payment.reference,ref);assert.match(b.host.innerHTML,/Payment Under Review/);
 await b.click('provider-confirm');assert.match(b.host.innerHTML,/Payment Successful!/);assert.equal(r.booking.deposit,3000);assert.equal(r.booking.remaining,57000);assert.equal(mailCalls,0);
 b.go('inbox',r);assert.match(b.host.innerHTML,/Your Verification Code/);assert.match(b.host.innerHTML,new RegExp(r.otp.code));b.go('verify',r);assert.equal((b.host.innerHTML.match(/data-digit="/g)||[]).length,6);
 const target={dataset:{digit:'0'},matches:s=>s==='[data-digit]'};b.listeners.paste({target,preventDefault(){},clipboardData:{getData:()=>r.otp.code}});assert.equal(b.node('[data-digit="5"]').value,r.otp.code[5]);
 await b.submit('verify',codeFields(r.otp.code),r);assert.match(b.host.innerHTML,/Create Your Account/);assert.match(b.host.innerHTML,/Email verified successfully/);
 await b.submit('account',{username:'demo.person',password:'OnlyADemo123!',confirm:'OnlyADemo123!',terms:'1'},r);assert.match(b.host.innerHTML,/My Requests/);assert.match(b.host.innerHTML,/Demo Person/);assert.match(b.host.innerHTML,/Beauty Plus Hospital/);assert.equal(journey.state.current,requestData.email);
 for(const tab of ['My Requests','My Quotations','My Chats','My Profile','Settings','My Bookings','Payments']){location.hash='#journey/dashboard/'+encodeURIComponent(tab);assert.match(b.host.innerHTML,/MEDICALBEAUTIFUL/);}
 b.go('chat',r);await b.submit('chat',{text:'Hello selected hospital'},r);assert.match(b.host.innerHTML,/Hello selected hospital/);
 await b.click('signout');await b.submit('sign-in',{identity:'demo.person',password:'OnlyADemo123!'});assert.equal(journey.state.current,requestData.email);
 startGuestJourney();assert.match(b.host.innerHTML,/Your saved profile information will be used automatically/);assert.match(b.host.innerHTML,/Edit Profile/);assert.match(b.host.innerHTML,/data-saved-profile="email"/);assert.match(b.host.innerHTML,/value="Demo Person"/);assert.match(b.host.innerHTML,/value="demoline"/);assert.match(b.host.innerHTML,/value="1995-03-15"/);assert.doesNotMatch(b.host.innerHTML,/Fictional goals/);
 await b.submit('request',{...requestData,goals:'New independent goals','procedures[]':['Rhinoplasty']});const second=journey.state.requests[1];assert.notEqual(second.id,r.id);await b.click('checkout',second.quotes[0].id);await b.click('provider-confirm');assert.equal(second.otp,undefined);assert.equal(journey.state.accounts.length,1);assert.equal(r.description,'Fictional goals');
 b.go('dashboard');await b.click('duplicate',r.token);assert.match(b.host.innerHTML,/Fictional goals/);assert.match(b.host.innerHTML,/value="10" selected/);assert.equal(journey.state.requests.length,2);assert.deepEqual(journey.duplicate(r.token).photos,[]);
 const pending=journey.request({...requestData,procedures:['Eye Surgery'],procedure:'Eye Surgery',description:'Pending goal',hospitals:['aesthetic-hospital-bangkok']});b.go('edit',pending);assert.match(b.host.innerHTML,/Save Request/);await b.submit('request',{...requestData,goals:'Edited before quotation'},pending);assert.equal(pending.description,'Edited before quotation');assert.equal(pending.quotes.length,0);
 assert.equal(mailCalls,0);b.go('home');
});

import test from 'node:test';
import assert from 'node:assert/strict';
import {Journey,REPLACEMENT_LINK_MESSAGE} from '../../resources/js/guest-journey-state.js';

function fixture(){
 let now=1000000,id=0;
 const j=new Journey(undefined,()=>now,()=>`id-${++id}`);
 const r=j.request({name:'Guest',email:'guest@example.test',procedure:'Private treatment',hospitals:['a','b'],description:'Secret medical condition',photos:['private.jpg']});
 return {j,r,tick:ms=>{now+=ms;}};
}

test('hospital responses notify without guest login and replayed events are idempotent after reload',()=>{
 const {j,r}=fixture();
 j.respond(r.token,'a');j.respond(r.token,'a');j.respond(r.token,'b');
 assert.equal(j.state.notifications.length,2);
 assert.equal(j.state.accounts.length,0);
 const restored=new Journey(JSON.parse(JSON.stringify(j.state)));
 restored.respond(r.token,'a');
 assert.equal(restored.state.notifications.length,2);
 j.respond(r.token,'a','message-1','response');j.respond(r.token,'a','message-1','response');
 assert.equal(j.state.notifications.length,3);
 for(const n of j.state.notifications){
  assert.equal(n.recipient,'guest@example.test');
  assert.equal(n.requestId,r.id);
  const preview=j.notificationPreview(n.id);
  assert.doesNotMatch(JSON.stringify(preview),/private.jpg|Private treatment|Secret medical condition/);
  assert.deepEqual(Object.keys(preview).sort(),['id','requestId','recipient','requestToken','linkToken','expires','kind'].sort());
 }
});

test('guest links are request-specific, expire, and authorize selection without an account',()=>{
 const {j,r,tick}=fixture();j.respond(r.token,'a');
 const n=j.state.notifications[0];
 assert.throws(()=>j.guard(r.token));
 assert.equal(j.openGuestLink(r.token,n.linkToken),r);
 const payment=j.beginPayment(r.token,r.quotes[0].id);
 assert.equal(payment.deposit,3250);
 assert.equal(r.booking,null);assert.equal(r.otp,undefined);
 const other=j.request({name:'Other',email:'other@example.test',procedure:'Other',hospitals:['a']});
 assert.throws(()=>j.openGuestLink(other.token,n.linkToken),/invalid|expired/);
 assert.throws(()=>j.openGuestLink(r.token,'tampered-token'),/invalid|expired/);
 tick(7*86400000);
 assert.throws(()=>j.openGuestLink(r.token,n.linkToken),/expired/);
 assert.throws(()=>j.guard(r.token));
 // Hospital events continue even when customer access has expired.
 j.respond(r.token,'b');assert.equal(j.state.notifications.length,2);
});

test('replacement response is identical for missing, mismatched and valid records, with cooldown and revocation',()=>{
 const {j,r,tick}=fixture();j.respond(r.token,'a');const old=j.state.notifications[0];
 assert.equal(j.requestReplacement('missing','guest@example.test'),REPLACEMENT_LINK_MESSAGE);
 assert.equal(j.requestReplacement(r.id,'wrong@example.test'),REPLACEMENT_LINK_MESSAGE);
 assert.equal(j.state.notifications.length,1);
 assert.equal(j.requestReplacement(r.id,' GUEST@example.test '),REPLACEMENT_LINK_MESSAGE);
 assert.equal(j.state.notifications.length,2);
 const replacement=j.state.notifications[1];
 assert.throws(()=>j.openGuestLink(r.token,old.linkToken),/expired/);
 j.openGuestLink(r.token,replacement.linkToken);
 j.requestReplacement(r.id,r.email);assert.equal(j.state.notifications.length,2);
 tick(60000);j.requestReplacement(r.id,r.email);assert.equal(j.state.notifications.length,3);
 assert.throws(()=>j.openGuestLink(r.token,replacement.linkToken),/expired/);
});

test('existing accounts always get dashboard notifications with optional simulated emails',()=>{
 const {j,r}=fixture();j.state.accounts.push({email:r.email,emailAlerts:false});
 j.respond(r.token,'a');
 assert.equal(j.state.notifications[0].dashboard,true);
 assert.equal(j.state.notifications[0].emailSent,false);
 assert.throws(()=>j.notificationPreview(j.state.notifications[0].id));
 j.state.accounts[0].emailAlerts=true;j.respond(r.token,'b');
 assert.equal(j.state.notifications[1].emailSent,true);
});

test('payment precedes OTP, verified new accounts are linked and automatically signed in',()=>{
 const {j,r}=fixture();j.respond(r.token,'a');j.openGuestLink(r.token,j.state.notifications[0].linkToken);
 assert.throws(()=>j.issue(r.token),/payment/);
 j.beginPayment(r.token,r.quotes[0].id);j.confirmPayment(r.token);
 assert.match(r.otp.code,/^\d{6}$/);
 assert.equal(j.verify(r.token,r.otp.code),'account');
 j.activate(r.token,r.name,'demo-hash','salt',{username:'guest',terms:true});
 assert.equal(j.state.current,r.email);assert.equal(r.owner,r.email);
 assert.equal(j.state.notifications[0].dashboard,true);
});

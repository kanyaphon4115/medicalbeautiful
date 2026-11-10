import test from 'node:test';
import assert from 'node:assert/strict';
import {browserAdapter,requestData,codeFields} from '../helpers/guest-dom.mjs';
const b=browserAdapter(),calls=[];let serverRecord;
globalThis.fetch=async(url,options)=>{const data=options.body?JSON.parse(options.body):null;calls.push({url,data});let result={},status=200;
 if(url==='/guest-journey/requests'){serverRecord={...data,token:'server-token',id:'GUEST-REFERENCE',procedure:data.procedures.join(', '),date:'2027-10',description:data.goals,quotes:[],booking:null,accessUntil:Date.now()+3600000,emailRequest:true,photos:[],messages:[]};result={token:serverRecord.token,reference:serverRecord.id};}
 else if(url.endsWith('/verify')){if(data.code!=='246810'){status=422;result={errors:{code:['The code is incorrect or expired.']}};}else result={verified:true};}
 else if(url.endsWith('/code'))result={message:'Sent'};else result=serverRecord;
 return{ok:status<400,json:async()=>result};
};
const {journey}=await import('../../resources/js/guest-journey.js');
test('existing real-email preview remains opt-in and verifies codes only through backend',async()=>{
 location.hash='#journey/new?email=real';await b.submit('request',requestData);const r=journey.state.requests[0];assert.equal(calls[0].data.procedures.length,2);assert.equal(r.token,'server-token');assert.match(b.host.innerHTML,/private access link/);
 location.hash='#journey/email/server-token';await new Promise(resolve=>setImmediate(resolve));assert.match(b.host.innerHTML,/No hospital has responded yet/);
 await b.click('respond','aesthetic-hospital-bangkok');b.go('checkout',r,r.quotes[0].id);await b.click('provider-confirm');assert.equal(r.otp,undefined);assert.equal(r.emailCodeSent,true);assert.ok(calls.some(c=>c.url.endsWith('/code')));
 b.go('inbox',r);assert.doesNotMatch(b.host.innerHTML,/246810/);b.go('verify',r);assert.equal((b.host.innerHTML.match(/data-digit="/g)||[]).length,6);
 await b.submit('verify',codeFields('000000'),r);assert.notEqual(r.verified,true);assert.match(b.node('#gj-error').textContent,/incorrect or expired/);
 await b.submit('verify',codeFields('246810'),r);assert.equal(r.verified,true);assert.match(b.host.innerHTML,/Create Your Account/);b.go('home');
});

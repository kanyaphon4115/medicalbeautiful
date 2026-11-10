import test from 'node:test';
import assert from 'node:assert/strict';
import {browserAdapter,requestData} from '../helpers/guest-dom.mjs';

test('live guest form submits to the server and does not generate local quotations or payments',async()=>{
 const b=browserAdapter();
 b.host.dataset.realRequestStore='/guest-requests';
 b.host.dataset.customerRequestsUrl='/my-requests';
 globalThis.FormData=class {
  constructor(form){this.values={...form.values};}
  *[Symbol.iterator](){for(const [key,value] of Object.entries(this.values))if(!Array.isArray(value))yield [key,value];}
  getAll(key){return this.values[key]||[];}
  set(key,value){this.values[key]=value;}
 };
 let submitted,redirect;
 location.assign=url=>{redirect=url;};
 globalThis.fetch=async(url,options)=>{
  submitted={url,...options};
  return {ok:true,json:async()=>({redirect:'/guest-requests/private-reference'})};
 };
 const {journey,startGuestJourney}=await import('../../resources/js/guest-journey.js');
 startGuestJourney('Eye Surgery');
 assert.doesNotMatch(b.host.innerHTML,/gj-toolbar|YOUR PRIVATE TREATMENT REQUEST|>Sign In</);
 assert.match(b.host.innerHTML,/stored privately/);
 assert.doesNotMatch(b.host.innerHTML,/Demo response timing|Frontend preview|in this demo/);
 await b.submit('request',requestData);
 assert.equal(submitted.url,'/guest-requests');
 assert.equal(submitted.method,'POST');
 assert.equal(submitted.headers['X-CSRF-TOKEN'],'csrf-fixture');
 assert.equal(submitted.body.values.phone_number,'+66812345678');
 assert.match(submitted.body.values.submission_reference,/^[a-f0-9-]{36}$/);
 assert.equal(redirect,'/guest-requests/private-reference');
 assert.equal(journey.state.requests.length,0);
});

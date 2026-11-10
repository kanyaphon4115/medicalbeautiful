import test from 'node:test';
import assert from 'node:assert/strict';
import {postChatMessage} from '../resources/js/chat-request.js';
const response=(status,data={})=>({status,ok:status>=200&&status<300,json:async()=>data});
function setup(replies){
 const calls=[];let token;
 return {calls,get token(){return token;},args:{
  endpoint:'/medicalbeautiful/public/chat/messages',sessionEndpoint:'/medicalbeautiful/public/chat/session',
  payload:{channel:'support',body:'Keep my draft',treatment_request_id:null},token:'stale',userId:'7',
  onToken:value=>{token=value;},fetcher:async(url,options)=>{calls.push({url,options});const reply=replies.shift();if(reply instanceof Error)throw reply;return reply;}
 }};
}
test('419 refreshes same-user token and retries original message once',async()=>{
 const s=setup([response(419),response(200,{user_id:7,csrf_token:'fresh'}),response(201)]);
 assert.equal((await postChatMessage(s.args)).status,201);
 assert.equal(s.calls.length,3);assert.equal(s.calls[1].options.cache,'no-store');
 assert.equal(s.calls[2].options.headers['X-CSRF-TOKEN'],'fresh');
 assert.equal(s.calls[0].options.body,s.calls[2].options.body);assert.equal(s.token,'fresh');
});
test('successful send is never duplicated',async()=>{
 const s=setup([response(201)]);await postChatMessage(s.args);assert.equal(s.calls.length,1);
});
test('expired session preserves message and does not retry POST',async()=>{
 const s=setup([response(419),response(401)]);
 await assert.rejects(postChatMessage(s.args),/message has been kept/);assert.equal(s.calls.length,2);
});
test('changed account cannot receive a stale tab message',async()=>{
 const s=setup([response(419),response(200,{user_id:8,csrf_token:'other'})]);
 await assert.rejects(postChatMessage(s.args),/account changed/);assert.equal(s.calls.length,2);assert.equal(s.token,undefined);
});
test('persistent 419 stops after one retry',async()=>{
 const s=setup([response(419),response(200,{user_id:7,csrf_token:'fresh'}),response(419)]);
 await assert.rejects(postChatMessage(s.args),/could not be refreshed/);assert.equal(s.calls.length,3);
});
test('network failures and validation errors never trigger a duplicate send',async()=>{
 const s=setup([new Error('Network unavailable')]);await assert.rejects(postChatMessage(s.args),/Network/);assert.equal(s.calls.length,1);
 const invalid=setup([response(422)]);assert.equal((await postChatMessage(invalid.args)).status,422);assert.equal(invalid.calls.length,1);
});
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {refreshChatSession,postChatMessage} from '../resources/js/chat-request.js';
const response=(status,data={})=>({status,ok:status>=200&&status<300,json:async()=>data});
test('preflight retrieves current token before sending and binds POST to original account',async()=>{
    let options;
    const session=await refreshChatSession({sessionEndpoint:'/chat/session',userId:'1',fetcher:async()=>response(200,{user_id:1,csrf_token:'current'})});
    await postChatMessage({endpoint:'/chat/messages',payload:{body:'Hi'},token:session.csrf_token,userId:'1',
        fetcher:async(url,request)=>{options=request;return response(201);}});
    assert.equal(options.headers['X-CSRF-TOKEN'],'current');
    assert.equal(options.headers['X-Chat-User-Id'],'1');
});
test('account switching fails preflight so no message is posted',async()=>{
    await assert.rejects(refreshChatSession({sessionEndpoint:'/chat/session',userId:1,
        fetcher:async()=>response(200,{user_id:2,csrf_token:'other'})}),error=>error.sessionInvalid&&/account changed/.test(error.message));
});
test('expired login blocks submission, while transient server failure can be retried',async()=>{
    await assert.rejects(refreshChatSession({sessionEndpoint:'/chat/session',userId:1,fetcher:async()=>response(401)}),error=>error.sessionInvalid===true);
    await assert.rejects(refreshChatSession({sessionEndpoint:'/chat/session',userId:1,fetcher:async()=>response(500)}),error=>error.sessionInvalid===false);
});
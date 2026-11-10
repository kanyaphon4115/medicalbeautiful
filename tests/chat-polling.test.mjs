import {test} from 'node:test';
import assert from 'node:assert/strict';
import {createChatPolling} from '../resources/js/chat-polling.js';

test('loads immediately, waits for completion, then polls every two seconds', async () => {
    let calls=0, queued, resolve;
    const polling=createChatPolling({
        load:()=>{calls++;return new Promise(done=>{resolve=done;});},
        isActive:()=>true,
        schedule:(fn,delay)=>{assert.equal(delay,2000);queued=fn;return 1;},
        cancel:()=>{},
    });
    polling.start();
    assert.equal(calls,1);
    assert.equal(queued,undefined);
    resolve();
    await Promise.resolve();await Promise.resolve();
    assert.equal(typeof queued,'function');
    queued();
    assert.equal(calls,2);
    polling.stop();resolve();
    await Promise.resolve();await Promise.resolve();
});
test('closing prevents pending responses from scheduling another poll', async () => {
    let resolve, schedules=0;
    const polling=createChatPolling({load:()=>new Promise(done=>{resolve=done;}),isActive:()=>true,schedule:()=>{schedules++;},cancel:()=>{}});
    polling.start();polling.stop();resolve();
    await Promise.resolve();await Promise.resolve();
    assert.equal(schedules,0);
});
test('hidden chat does not request messages and reopening loads immediately', async () => {
    let active=false,calls=0;
    const polling=createChatPolling({load:async()=>{calls++;},isActive:()=>active,schedule:()=>1,cancel:()=>{}});
    polling.start();assert.equal(calls,0);
    active=true;polling.start();assert.equal(calls,1);
    polling.stop();
    await Promise.resolve();
});
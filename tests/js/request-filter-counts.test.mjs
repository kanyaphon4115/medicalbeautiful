import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

test('My Requests initializes all filter counters without stopping page rendering',()=>{
 const source=readFileSync(new URL('../../resources/js/requests.js',import.meta.url),'utf8');
 const start=source.indexOf('function updateFilterCounts()');
 const end=source.indexOf('\nupdateFilterCounts();',start);
 const nodes=['all','pending','quotes_received','pending_payment','confirmed','completed','cancelled'].map(status=>({dataset:{filterCount:status},textContent:'0'}));
 const records=[{status:'quotes_received'},{status:'pending_payment'},{status:'confirmed'},{status:'confirmed'}];
 vm.runInNewContext(source.slice(start,end)+'\nupdateFilterCounts();',{
  savedRequests:records,$:()=>nodes[0],$$:()=>nodes,
 });
 assert.deepEqual(nodes.map(node=>Number(node.textContent)),[4,0,1,1,2,0,0]);
});

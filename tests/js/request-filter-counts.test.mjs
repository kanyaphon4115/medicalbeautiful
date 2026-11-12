import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

test('My Requests initializes all filter counters without stopping page rendering',()=>{
 const source=readFileSync(new URL('../../resources/js/requests.js',import.meta.url),'utf8');
 const start=source.indexOf('function updateFilterCounts()');
 const end=source.indexOf('\ndocument.addEventListener',start);
 const nodes=['all','pending','quotes_received','account_activated','pending_booking_deposit','confirmed','completed','cancelled'].map(status=>({dataset:{filterCount:status},textContent:'0'}));
 const records=[{status:'quotes_received'},{status:'account_activated'},{status:'pending_booking_deposit'},{status:'confirmed'},{status:'confirmed'}];
 vm.runInNewContext(source.slice(start,end)+'\nupdateFilterCounts();',{
  savedRequests:records,$:()=>nodes[0],$$:()=>nodes,
 });
 assert.deepEqual(nodes.map(node=>Number(node.textContent)),[5,0,1,1,1,2,0,0]);
});

test('filter initialization reaches the first render and every filter remains usable',()=>{
 const source=readFileSync(new URL('../../resources/js/requests.js',import.meta.url),'utf8');
 const statuses=['all','pending','quotes_received','account_activated','pending_booking_deposit','confirmed','completed','cancelled'];
 const buttons=statuses.map(status=>({dataset:{requestFilter:status},setAttribute(){}}));
 const counters=statuses.map(status=>({dataset:{filterCount:status}}));
 let renders=0;
 const context={savedRequests:[{status:'account_activated'}],activeFilter:'all',currentPage:1,
  render:()=>renders++,document:{addEventListener(){}},
  $:selector=>selector==='[data-request-filter]'?buttons[0]:{addEventListener(){}},
  $$:selector=>selector==='[data-request-filter]'?buttons:counters};
 vm.runInNewContext(source.slice(source.indexOf('function updateFilterCounts()')),context);
 assert.equal(renders,1,'The request list must render on page load');
 buttons.forEach((button,index)=>{button.onclick();assert.equal(context.activeFilter,statuses[index]);});
 assert.equal(renders,9,'Each filter must render the list');
});

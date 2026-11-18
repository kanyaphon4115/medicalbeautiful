import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../../resources/js/requests.js',import.meta.url),'utf8');
test('My Requests counters use database totals across all pages',()=>{
 const start=source.indexOf('function updateFilterCounts()'),end=source.indexOf('\ndocument.addEventListener',start);
 const nodes=['all','in_progress','confirmed','completed','cancelled'].map(status=>({dataset:{filterCount:status},textContent:'0'}));
 vm.runInNewContext(source.slice(start,end)+'\nupdateFilterCounts();',{serverPage:{counts:{all:27,in_progress:12,confirmed:8,completed:7}},$$:()=>nodes});
 assert.deepEqual(nodes.map(node=>Number(node.textContent)),[27,12,8,7,0]);
});
test('filters and sort reset the database page and retain other URL conditions',()=>{
 const statuses=['all','in_progress','confirmed','completed','cancelled'];const buttons=statuses.map(status=>({dataset:{requestFilter:status},setAttribute(){}}));const counters=statuses.map(status=>({dataset:{filterCount:status}}));let target,renders=0,sortHandler;const sort={value:'',addEventListener(event,handler){sortHandler=handler;}};
 const context={serverPage:{sort:'oldest',counts:{all:27}},activeFilter:'all',render:()=>renders++,URL,
  location:{href:'http://localhost/my-requests?page=4&sort=oldest&lang=th',hash:'',assign:url=>{target=new URL(url);}},document:{addEventListener(){}},$:()=>sort,$$:selector=>selector==='[data-request-filter]'?buttons:counters};
 vm.runInNewContext(source.slice(source.indexOf('function updateFilterCounts()')),context);assert.equal(renders,1);
 for(const button of buttons){button.onclick();assert.equal(target.searchParams.get('filter'),button.dataset.requestFilter);assert.equal(target.searchParams.has('page'),false);assert.equal(target.searchParams.get('lang'),'th');assert.equal(target.searchParams.get('sort'),'oldest');}
 sort.value='newest';sortHandler();assert.equal(target.searchParams.get('sort'),'newest');assert.equal(target.searchParams.has('page'),false);
});

export const PAGE_SIZE = 5;
export function pageNumbers(current, total) {
 const count=Math.min(3,Math.max(0,total)), start=Math.max(1,Math.min(current-1,total-count+1));
 return Array.from({length:count},(_,i)=>start+i);
}
export function pageItems(items,page=1){const pages=Math.max(1,Math.ceil(items.length/PAGE_SIZE));page=Math.max(1,Math.min(page,pages));return {items:items.slice((page-1)*PAGE_SIZE,page*PAGE_SIZE),page,pages,total:items.length};}
export function renderNumbers(nav,total,page,onChange){
 nav.classList.add('ae-pagination');nav.hidden=total<=PAGE_SIZE;nav.replaceChildren();
 for(const number of pageNumbers(page,Math.ceil(total/PAGE_SIZE))){const button=document.createElement('button');button.type='button';button.textContent=number;button.setAttribute('aria-label',`Page ${number}`);if(number===page)button.setAttribute('aria-current','page');button.onclick=()=>onChange(number);nav.append(button);}
}
export function createListPager(list){
 const nav=document.createElement('nav');nav.setAttribute('aria-label','Pages');list.after(nav);let page=1;
 return {reset(){page=1;},slice(items,render){const result=pageItems(items,page);page=result.page;renderNumbers(nav,items.length,page,next=>{page=next;render();});return result.items;}};
}
// Explicit record containers only: message histories, navigation, and form fields are excluded.
const selectors='[data-paginate],.request-hospital-grid,.ap-mini-list,.ap-activity,.ap-approval-list,.ap-rankings,.settings-session-list,.partner-all-doctors,.partner-doctor-grid,.partner-review-grid,.partner-gallery,.pr-photo-gallery,.ap-conversation-list:not(#support-list):not(#booking-chat-list),.gj-quotation-options,.gj-hospital-responses,.hp-profile-card .hp-gallery,.gj-request-rows,.gj-notification-list,.gj-dashboard-notifications';
const attached=new WeakSet();
export function initListPagination(scope=document){
 for(const list of scope.querySelectorAll(selectors)){
  if(list.matches?.('.gj-hospital-responses,.gj-dashboard-notifications'))list.dataset.paginate='article';
  if(attached.has(list)||list.closest('form,template'))continue;attached.add(list);
  const nav=document.createElement('nav');nav.setAttribute('aria-label','Pages');
  const table=list.closest('table');(list.tagName==='TBODY'?table:(list.closest('.hospital-shell,.procedure-shell')||list)).after(nav);let page=1;
  const render=()=>{const rows=[...list.children].filter(node=>!node.hidden&&node.style.display!=='none'&&!node.matches('nav,.ae-pagination')&&(!list.dataset.paginate||node.matches(list.dataset.paginate)));const result=pageItems(rows,page);page=result.page;for(const [index,row] of rows.entries())row.toggleAttribute('data-pagination-hidden',index<(page-1)*5||index>=page*5);renderNumbers(nav,rows.length,page,next=>{page=next;render();});};
  new MutationObserver(records=>{if(records.some(r=>r.type==='childList'||r.attributeName==='hidden'||r.attributeName==='style')){page=1;render();}}).observe(list,{childList:true,subtree:false,attributes:false});
  // Only visibility changes to records represent filtering. Controls inside a
  // record may change visibility without changing the current result page.
  new MutationObserver(records=>{if(records.some(record=>record.target.parentElement===list)){page=1;render();}}).observe(list,{subtree:true,attributes:true,attributeFilter:['hidden','style']});
  render();
 }
}
if(typeof document!=='undefined'){
 const start=()=>{initListPagination();new MutationObserver(records=>{if(records.some(r=>[...r.addedNodes].some(n=>n.nodeType===1&&!n.closest?.('.ae-pagination'))))initListPagination();}).observe(document.body,{childList:true,subtree:true});};
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
}

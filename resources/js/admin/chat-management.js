import {initSupportInbox} from './support-inbox.js';
import {initBookingInbox} from '../booking-inbox.js';
import {initPortalIcons} from './icons.js';

// This entry point uses only the live chat APIs, never the prototype portal state.
const config=JSON.parse(document.getElementById('ap-data').textContent);
const t=value=>config.catalog[value]??value;
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const root=document.getElementById('ap-content');
initPortalIcons();
document.body.classList.add('ap-chat-management');
const heading=document.querySelector('.ap-page-heading');
heading.classList.add('ap-welcome');
const eyebrow=document.createElement('p');
eyebrow.className='eyebrow';eyebrow.textContent='CUSTOMER CARE';
heading.firstElementChild.prepend(eyebrow);
const description=document.createElement('p');
description.textContent='Manage customer conversations and view hospital chats.';
heading.firstElementChild.append(description);
const hospital=config.page==='booking-chats'||new URLSearchParams(location.search).get('tab')==='hospital';
const tabs=document.createElement('nav');
tabs.className='ap-actions ap-chat-tabs';tabs.setAttribute('aria-label','Chat Management');
for(const [key,label] of [['support','Customer Support Chats'],['hospital','Hospital Chats (Read Only)']]){
    const link=document.createElement('a');
    link.className='ap-button'+((key==='hospital')===hospital?' gold':'');
    link.href=config.base+'/chat-management?tab='+key+'&lang='+encodeURIComponent(config.locale);
    link.textContent=label;
    if((key==='hospital')===hospital)link.setAttribute('aria-current','page');
    tabs.append(link);
}
root.before(tabs);
document.getElementById('ap-menu').addEventListener('click',event=>{
    const sidebar=document.getElementById('ap-sidebar');
    const open=event.currentTarget.getAttribute('aria-expanded')!=='true';
    event.currentTarget.setAttribute('aria-expanded',String(open));sidebar.classList.toggle('open',open);
});
document.getElementById('ap-language').addEventListener('change',event=>{
    const url=new URL(location.href);url.searchParams.set('lang',event.target.value);location.assign(url);
});
if(hospital)initBookingInbox({root,config,t,esc});else initSupportInbox({root,config,t,esc});

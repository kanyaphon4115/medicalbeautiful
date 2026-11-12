import {initBookingInbox} from './booking-inbox.js';
const paths = {
 home:'M3 10 12 3l9 7v11h-6v-7H9v7H3Z', document:'M14 2H4v20h16V8l-6-6Zm0 0v6h6M8 12h8m-8 4h6',
 calendar:'M4 5h16v16H4ZM8 2v6m8-6v6M4 11h16', chat:'M3 4h18v13H9l-6 5V4Z', hospital:'M5 3h14v19H5ZM9 7h6m-3-3v6M8 14h2m4 0h2m-8 4h2m4 0h2',
 bell:'M5 17h14l-2-4V9a5 5 0 0 0-10 0v4l-2 4Zm5 4h4', settings:'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM10 2h4l1 3 3 1 3 3-1 3 1 3-3 3-3 1-1 3h-4l-1-3-3-1-3-3 1-3-1-3 3-3 3-1Z'
};
document.querySelectorAll('[data-hd-icon]').forEach(el => {el.innerHTML = `<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${paths[el.dataset.hdIcon] || paths.document}"/></svg>`;});

const sidebar=document.getElementById('hospital-sidebar'), toggle=document.querySelector('.hd-menu');
toggle.addEventListener('click',()=>{toggle.setAttribute('aria-expanded',String(sidebar.classList.toggle('is-open')));});
const dashboard=document.getElementById('hd-dashboard-panel'),panel=document.getElementById('hd-section-panel'),content=document.getElementById('hd-section-content');
let disposeBookingChat;
function section(name,room){
 disposeBookingChat?.();disposeBookingChat=null;
 dashboard.hidden=name!=='Dashboard';panel.hidden=name==='Dashboard';document.getElementById('hd-section-title').textContent=name;content.replaceChildren();
 document.querySelectorAll('.hd-sidebar [data-hd-section]').forEach(button=>{if(button.dataset.hdSection===name)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');});
 if(name==='Patient Requests')content.append(document.getElementById('hd-request-table').cloneNode(true));
 else if(name==='My Quotations'||name==='Bookings')content.append(document.getElementById(name==='Bookings'?'hd-bookings':'hd-quotations').content.cloneNode(true));
 else if(name==='Booking Chat'){
  const host=document.createElement('div');content.append(host);
  const config=JSON.parse(document.getElementById('hospital-chat-config').textContent);config.initialRoom=room;
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  disposeBookingChat=initBookingInbox({root:host,config,esc});
 }
 else if(name!=='Dashboard'){const p=document.createElement('p');p.className='hd-empty';p.textContent=name==='Hospital Profile'?document.querySelector('.hd-clinic strong').textContent:'No records available. This feature is not connected yet.';content.append(p);}
 sidebar.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');
}
document.addEventListener('click',event=>{
 const nav=event.target.closest('[data-hd-section]');if(nav)section(nav.dataset.hdSection,nav.dataset.bookingRoom);
 const request=event.target.closest('[data-hd-request]');if(request)document.getElementById('request-'+request.dataset.hdRequest)?.showModal();
 if(event.target.closest('.hd-close'))event.target.closest('dialog')?.close();
 if(event.target.closest('[data-hd-support]'))location.href=document.querySelector('.hd-sidebar>.brand').href;
 if(!sidebar.contains(event.target)&&!toggle.contains(event.target)){sidebar.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');}
});
document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}}));

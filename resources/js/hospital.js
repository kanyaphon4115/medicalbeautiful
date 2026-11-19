import './pagination.js';
import {initHospitalIcons} from './hospital-workspace.js';
import {translateText} from './i18n.js';
import './language-switcher.js';
import {initBookingInbox} from './booking-inbox.js';
initHospitalIcons();

const sidebar=document.getElementById('hospital-sidebar'), toggle=document.querySelector('.hd-menu');
toggle.addEventListener('click',()=>{toggle.setAttribute('aria-expanded',String(sidebar.classList.toggle('is-open')));});
const dashboard=document.getElementById('hd-dashboard-panel'),panel=document.getElementById('hd-section-panel'),content=document.getElementById('hd-section-content');
let disposeBookingChat;
function section(name,room){
 const url=new URL(location.href);url.searchParams.set('section',name);history.replaceState(history.state,'',url);
 disposeBookingChat?.();disposeBookingChat=null;
 dashboard.hidden=name!=='Dashboard';panel.hidden=name==='Dashboard';document.getElementById('hd-section-title').textContent=translateText(name,document.documentElement.lang);content.replaceChildren();
 document.querySelectorAll('.hd-sidebar [data-hd-section]').forEach(button=>{if(button.dataset.hdSection===name)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');});
 if(name==='Patient Requests'){
  content.append(document.getElementById('hd-request-table').cloneNode(true));
  content.querySelectorAll('.ae-pagination a').forEach(link=>{const url=new URL(link.href);url.searchParams.set('section',name);link.href=url;});
 }
 else if(name==='My Quotations'||name==='Bookings')content.append(document.getElementById(name==='Bookings'?'hd-bookings':'hd-quotations').content.cloneNode(true));
 else if(name==='Booking Chat'){
  const host=document.createElement('div');content.append(host);
  const config=JSON.parse(document.getElementById('hospital-chat-config').textContent);config.initialRoom=room;
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  disposeBookingChat=initBookingInbox({root:host,config,esc,t:value=>translateText(value,document.documentElement.lang)});
 }
 else if(name!=='Dashboard'){const p=document.createElement('p');p.className='hd-empty';p.textContent='No records available. This feature is not connected yet.';content.append(p);}
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
const requestedSection=new URLSearchParams(location.search).get('section');
if(['Dashboard','Patient Requests','My Quotations','Bookings','Booking Chat','Notifications','Settings'].includes(requestedSection))section(requestedSection);

import '../pagination.js';
import '../i18n.js';
import {initPortalIcons} from './icons.js';
initPortalIcons();
document.querySelector('[data-real-customers]').addEventListener('click',event=>{
 const button=event.target.closest('[data-real-customer]');if(button)document.getElementById('real-customer-'+button.dataset.realCustomer)?.showModal();
});
document.querySelectorAll('[data-close-real-customer]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
document.querySelectorAll('dialog[id^="real-customer-"]').forEach(dialog=>dialog.addEventListener('click',event=>{const box=dialog.getBoundingClientRect();if(event.target===dialog&&(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom))dialog.close();}));
const sidebar=document.getElementById('ap-sidebar'),menu=document.getElementById('ap-menu');
menu.addEventListener('click',()=>menu.setAttribute('aria-expanded',String(sidebar.classList.toggle('open'))));
document.addEventListener('click',event=>{if(!sidebar.contains(event.target)&&!menu.contains(event.target)){sidebar.classList.remove('open');menu.setAttribute('aria-expanded','false');}});
document.getElementById('ap-language').addEventListener('change',event=>{const url=new URL(location.href);url.searchParams.set('lang',event.target.value);location.assign(url);});

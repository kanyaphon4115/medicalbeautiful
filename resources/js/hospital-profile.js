import './pagination.js';
import {translateText} from './i18n.js';
import './language-switcher.js';
import {initHospitalIcons} from './hospital-workspace.js';
initHospitalIcons();
const sidebar=document.getElementById('hospital-sidebar'),toggle=document.querySelector('.hd-menu');
toggle.addEventListener('click',()=>toggle.setAttribute('aria-expanded',String(sidebar.classList.toggle('is-open'))));
document.querySelectorAll('[data-hd-section]').forEach(button=>button.addEventListener('click',()=>{location.assign(document.body.dataset.dashboardUrl+'?section='+encodeURIComponent(button.dataset.hdSection));}));
document.querySelector('[data-hd-support]')?.addEventListener('click',()=>location.assign(document.querySelector('.hd-sidebar>.brand').href));
document.querySelector('[data-profile-edit]').addEventListener('click',event=>{event.preventDefault();document.querySelector('[data-profile-summary]').hidden=true;const form=document.querySelector('[data-profile-form]');form.hidden=false;form.querySelector('input[name=name]').focus();});
document.querySelectorAll('[data-profile-photo]').forEach(input=>{
 const host=input.closest('div').querySelector('[data-file-preview]');let urls=[];
 const refresh=()=>{
  urls.forEach(url=>URL.revokeObjectURL(url));urls=[];host.replaceChildren();const files=[...input.files];
  const setFiles=files=>{const transfer=new DataTransfer();files.forEach(file=>transfer.items.add(file));input.files=transfer.files;refresh();};
  for(const [index,file] of files.entries()){
   const figure=document.createElement('figure'),image=document.createElement('img'),remove=document.createElement('button'),replace=document.createElement('button'),picker=document.createElement('input');
   image.src=URL.createObjectURL(file);urls.push(image.src);image.alt=translateText('Selected hospital image',document.documentElement.lang);remove.type=replace.type='button';remove.textContent=translateText('Remove',document.documentElement.lang);replace.textContent=translateText('Replace',document.documentElement.lang);picker.type='file';picker.accept=input.accept;picker.hidden=true;
   remove.addEventListener('click',()=>setFiles(files.filter((_,i)=>i!==index)));replace.addEventListener('click',()=>picker.click());
   picker.addEventListener('change',()=>{if(picker.files[0]){const updated=[...files];updated[index]=picker.files[0];setFiles(updated);}});
   figure.append(image,replace,remove,picker);host.append(figure);
  }
 };
 input.addEventListener('change',refresh);
});

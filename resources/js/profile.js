import {translateText} from './i18n.js';
import {countryCodes,createDemoProfileState,validateDemoProfile} from './profile-state.js';
let state;
export function openProfile() {
 const modal=document.querySelector('#demo-modal'),content=document.querySelector('#modal-content');
 if(!state)state=createDemoProfileState(JSON.parse(document.querySelector('#account-profile-data').textContent));
 content.innerHTML=document.querySelector('#account-profile-content').innerHTML;
 modal.classList.add('profile-dialog');let editing=false;let locale=state.get().language;
 const $=selector=>content.querySelector(selector);const fields={name:$('#profile-full-name'),email:$('#profile-email'),phone:$('#profile-phone'),country:$('#profile-country'),language:$('#profile-language')};
 const t=key=>translateText(key,locale);
 function clearErrors(){$('.profile-error').hidden=true;Object.values(fields).forEach(field=>field.removeAttribute('aria-invalid'));}
 function countries(value){const names=new Intl.DisplayNames([locale],{type:'region'});const options=countryCodes.map(code=>({code,name:names.of(code)})).sort((a,b)=>a.name.localeCompare(b.name,locale));fields.country.replaceChildren();const empty=document.createElement('option');empty.value='';empty.textContent=t('Select your country or region');fields.country.append(empty);options.forEach(item=>{const option=document.createElement('option');option.value=item.code;option.textContent=item.name;fields.country.append(option);});fields.country.value=value;}
 function texts(){const selectedCountry=fields.country.value;content.querySelectorAll('[data-profile-i18n]').forEach(node=>node.textContent=t(node.dataset.profileI18n));Array.from(fields.language.options).forEach(option=>{option.textContent=t({en:'English',th:'Thai',ko:'Korean'}[option.value]);});countries(selectedCountry);$('[data-profile-avatar]').alt=t('Profile Picture');$('.profile-avatar').setAttribute('aria-label',t('Profile Picture'));$('.profile-modal').lang=locale;}
 function mode(value){editing=value;fields.name.readOnly=!value;fields.phone.readOnly=!value;fields.country.disabled=!value;fields.language.disabled=!value;fields.email.readOnly=true;$('[data-profile-edit]').hidden=value;$('[data-profile-save]').hidden=!value;$('[data-profile-cancel]').hidden=!value;}
 function fill(){const saved=state.get();fields.name.value=saved.name;fields.email.value=saved.email;fields.phone.value=saved.phone;locale=saved.language;fields.language.value=locale;countries(saved.country);$('[data-profile-display-name]').textContent=saved.name;$('[data-profile-initials]').textContent=String(saved.name).trim().split(/\s+/).slice(0,2).map(word=>Array.from(word)[0]||'').join('').toUpperCase()||'A';texts();clearErrors();}
 fill();mode(false);
 const saved=state.get();const initials=String(saved.name).trim().split(/\s+/).slice(0,2).map(word=>Array.from(word)[0]||'').join('').toUpperCase();$('[data-profile-initials]').textContent=initials||'A';
 const image=$('[data-profile-avatar]'),fallback=$('[data-profile-initials]');
 function noPicture(){image.hidden=true;fallback.hidden=false;$('.profile-avatar-note').hidden=false;}
 image.onload=()=>{image.hidden=false;fallback.hidden=true;$('.profile-avatar-note').hidden=true;};image.onerror=noPicture;
 if(saved.picture)image.src=saved.picture;else noPicture();
 $('[data-profile-edit]').onclick=()=>{mode(true);$('.profile-feedback').textContent='';fields.name.focus();};
 $('[data-profile-cancel]').onclick=()=>{fill();mode(false);$('.profile-feedback').textContent=t('Unsaved changes discarded.');$('[data-profile-edit]').focus();};
 fields.language.onchange=()=>{locale=fields.language.value;texts();clearErrors();};
 $('.profile-form').addEventListener('input',clearErrors);
 $('.profile-form').onsubmit=async event=>{
  event.preventDefault();if(!editing)return;
  const draft={name:fields.name.value,phone:fields.phone.value,country:fields.country.value,language:fields.language.value};const error=validateDemoProfile(draft);
  if(error){fields[error.field].setAttribute('aria-invalid','true');$('.profile-error').textContent=t(error.message);$('.profile-error').hidden=false;fields[error.field].focus();return;}
  const pane=$('.profile-modal'),save=$('[data-profile-save]'),cancel=$('[data-profile-cancel]');save.disabled=true;cancel.disabled=true;Object.values(fields).forEach(field=>field.disabled=true);$('.profile-feedback').textContent=t('Saving...');
  try {
   const response=await fetch(pane.dataset.profileUrl,{method:'PATCH',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(draft)});
   let result={};try{result=await response.json();}catch{}
   if(!response.ok)throw new Error(response.status===401||response.status===419?t('Your session expired. Refresh the page and sign in again.'):Object.values(result.errors||{}).flat().join(' ')||result.message||t('Profile could not be saved. Please try again.'));
   state.save(result.profile);
   document.querySelectorAll('.account-dropdown>summary>span,.account-dropdown-heading strong').forEach(node=>node.textContent=translateText('Hello, '+result.profile.name,document.documentElement.lang));
   if(!pane.isConnected)return;
   fill();mode(false);pane.querySelector('.profile-language-notice')?.remove();$('.profile-feedback').textContent=t('Profile updated successfully.');
   if(document.documentElement.lang!==result.profile.language){const notice=document.createElement('p');notice.className='profile-language-notice';notice.textContent=t('Reload the page to apply your saved language across the website.');const reload=document.createElement('button');reload.type='button';reload.className='button button-outline';reload.textContent=t('Reload Website');reload.onclick=()=>window.location.reload();notice.append(document.createElement('br'),reload);pane.append(notice);}
   $('[data-profile-edit]').focus();
  }catch(failure){if(pane.isConnected){$('.profile-feedback').textContent='';$('.profile-error').textContent=t(failure.message);$('.profile-error').hidden=false;}}
  finally{save.disabled=false;cancel.disabled=false;fields.name.disabled=false;fields.email.disabled=false;fields.phone.disabled=false;fields.country.disabled=!editing;fields.language.disabled=!editing;}
 };

 modal.addEventListener('close',()=>{modal.classList.remove('profile-dialog');},{once:true});
 if(!modal.open)modal.showModal();document.body.style.overflow='hidden';
}

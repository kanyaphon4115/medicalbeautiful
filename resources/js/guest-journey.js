import {GuestFormState} from './guest-form-state.js';
import {protectedJourneyPages} from './customer-access.js';
import {ageLabel} from './birth-age.js';
﻿import {Journey,emptyState,STORAGE_KEY,depositFor,strongPassword} from './guest-journey-state.js';
import {hospitals} from './partner-hospitals-data.js';
const host=document.querySelector('[data-guest-journey]');
const notificationDemo=host?.dataset?.notificationDemo==='true';
const realRequestStore=notificationDemo?null:host?.dataset?.realRequestStore;
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money=v=>'THB '+Number(v).toLocaleString('en-US',{maximumFractionDigits:2});
const months=Array.from({length:12},(_,i)=>new Intl.DateTimeFormat('en',{month:'long'}).format(new Date(2026,i,1)));
const today=()=>new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Bangkok',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
const periodLabel=v=>v?months[Number(v.slice(5,7))-1]+' '+v.slice(0,4):'Not specified';
const hById=id=>hospitals.find(h=>h.id===id);
const assets=host?.dataset?.assets||'images';
const image=(file,alt,cls='')=>`<img class="${cls}" src="${esc(assets+'/'+file)}" alt="${esc(alt)}">`;
const path=(page,token='',extra='')=>'#journey/'+page+(token?'/'+encodeURIComponent(token):'')+(extra?'/'+encodeURIComponent(extra):'');
const link=(text,page,token='',extra='',cls='gj-button gj-outline')=>`<a class="${cls}" href="${path(page,token,extra)}">${esc(text)}</a>`;
const button=(text,action,value='',cls='gj-button gj-outline')=>`<button type="button" class="${cls}" data-action="${action}" data-value="${esc(value)}">${text}</button>`;
const field=(label,name,type='text',value='',attrs='')=>`<label>${label}<input name="${name}" type="${type}" value="${esc(value)}" ${attrs}></label>`;
const icon=name=>{const p={check:'m5 12 4 4L19 6',mail:'M3 5h18v14H3z M3 5l9 8 9-8',shield:'M12 2 3 6v6c0 6 9 10 9 10s9-4 9-10V6z M8 12l3 3 5-6',arrow:'M4 12h16m-6-6 6 6-6 6',user:'M7 8a5 5 0 1 0 10 0 5 5 0 0 0-10 0 M3 22c0-9 18-9 18 0',chat:'M3 3h18v14H8l-5 5z',profile:'M4 3h16v18H4z M8 8h8 M8 12h8 M8 16h5',settings:'M12 3v3 M12 18v3 M3 12h3 M18 12h3 M8 12a4 4 0 1 0 8 0 4 4 0 0 0-8 0',logout:'M10 3H3v18h7 M8 12h13 M16 7l5 5-5 5',eye:'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12 M9 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0',heart:'M12 21 3 12C-3 3 8-1 12 6c4-7 15-3 9 6z',pin:'M12 22s8-9 8-14A8 8 0 0 0 4 8c0 5 8 14 8 14 M9 8a3 3 0 1 0 6 0 3 3 0 0 0-6 0'};return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="${p[name]||p.profile}"/></svg>`;};
let state;try{const saved=JSON.parse(sessionStorage.getItem(STORAGE_KEY));state=saved?.version===1?saved:emptyState();}catch{state=emptyState();}
if(notificationDemo)state.requests=state.requests.filter(r=>!r.emailRequest);
export const journey=new Journey(state);
let files=[],urls=[],notice='',timer=null,draft=null,dashboardFilter='All';
let draftStorage;try{draftStorage=globalThis.sessionStorage;}catch{}
let draftOwner='guest',formState=new GuestFormState({storage:draftStorage});
const accountDrafts=new Map([['guest',formState]]);
const newFormKey=()=> 'new:'+draftOwner;
function selectDraftOwner(){
 const index=journey.state.accounts.findIndex(a=>a.email===journey.state.current);
 const owner=index<0?'guest':'account-'+index;
 if(owner===draftOwner)return;
 formState.flush();draftOwner=owner;
 if(!accountDrafts.has(owner))accountDrafts.set(owner,new GuestFormState({storage:draftStorage,key:'aesthetic-request-draft-'+owner}));
 formState=accountDrafts.get(owner);draft=null;files=formState.files;
}
const formViews=new Map();let activeFormKey=null;
function captureRequestForm(){
 const form=host?.querySelector('form[data-form="request"]');
 if(!activeFormKey || form?.dataset?.form!=='request')return;
 if(activeFormKey===newFormKey()){formState.capture(form);formState.files=files;}
 if(host.childNodes)formViews.set(activeFormKey,{nodes:[...host.childNodes],files});
}
function clearGuestDraft(){
 activeFormKey=null;formViews.delete(newFormKey());formState.clear();draft=null;clearPhotos();
}
function rememberRequestInput(event){
 const form=event.target.closest?.('form[data-form="request"]');
 if(activeFormKey===newFormKey() && form?.dataset?.form==='request')formState.capture(form);
}

const photoMemory=new Map();
let resumeWrite=Promise.resolve(),resumeLink='';
const resumeStore=host?.dataset?.demoResumeStore;
const save=()=>{sessionStorage.setItem(STORAGE_KEY,JSON.stringify(journey.state));persistResume();document.dispatchEvent?.(new Event('guest-auth-changed'));};
function persistResume(){
 if(!resumeStore)return;
 const r=journey.state.requests.find(r=>r.token===currentToken())||journey.state.requests.at(-1);
 if(!r||r.emailRequest)return;
 const snapshot=JSON.parse(JSON.stringify(r));delete snapshot.otp;
 resumeWrite=resumeWrite.catch(()=>{}).then(async()=>{
  try{const result=await api(resumeStore,{request:snapshot});resumeLink=result.return_url||resumeLink;}
  catch{notice='Demo resume could not be saved. Keep this tab open and check again.';}
 });
}
async function loadResume(){
 if(!resumeStore || (document.body.dataset.signedIn==='true' && location.hash!=='#journey/resume'))return;
 try{const data=await api(resumeStore);if(!data.request)return;
  const r=data.request,index=journey.state.requests.findIndex(item=>item.token===r.token);
  if(index<0)journey.state.requests.push(r);else journey.state.requests[index]=r;
  resumeLink=data.return_url||'';sessionStorage.setItem(STORAGE_KEY,JSON.stringify(journey.state));
  if(!location.hash||['#journey/home','#journey/resume'].includes(location.hash)||(currentToken()===r.token&&/^#journey\/(review|checkout|payment-state|verify|account)\//.test(location.hash)))go(journey.resume(r.token),r.token,r.payment?.quote||r.selectedQuote||'');
 }catch{if(location.hash==='#journey/resume')wrap('Resume unavailable','<p>Your demo return link or session has expired. Open the latest private return link.</p>');}
}
const go=(page,token='',extra='')=>{const hash=path(page,token,extra);if(location.hash===hash)render();else location.hash=hash;};
const websiteSignIn=()=>`<a class="gj-button gj-outline" href="${esc(document.body.dataset.loginUrl||'login')}">Sign In</a>`;
const actions=(...items)=>`<div class="gj-actions">${items.join('')}</div>`;
const currentAccount=()=>journey.state.accounts.find(a=>a.email===journey.state.current);
const currentToken=()=>decodeURIComponent(location.hash.split('/')[2]?.split('?')[0]||'');
const mailStore=notificationDemo?null:host?.dataset?.mailStore;
async function api(url,data){const response=await fetch(url,{method:data?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(data?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}:{})},...(data?{body:JSON.stringify(data)}:{})});const result=await response.json().catch(()=>({}));if(!response.ok)throw Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Unable to complete this request.');return result;}
const mailUrl=(token,action='')=>mailStore+'/'+encodeURIComponent(token)+(action?'/'+action:'');
export function startGuestJourney(procedure=''){if(!host){location.assign(document.querySelector('.site-header').dataset.homeUrl+path('new')+(procedure?'?procedure='+encodeURIComponent(procedure):''));return;}selectDraftOwner();clearGuestDraft();if(procedure)formState.update({procedures:[procedure],step:'request'});const next=path('new')+(procedure?'?procedure='+encodeURIComponent(procedure):'');if(location.hash===next)render();else location.hash=next;}
const steps=['Submit Request','Select Hospital','Pay Deposit','Payment Confirmation','Receive Code','Verify Code','Create Account','Dashboard'];
function wrap(title,body,step=1,cls=''){
 clearInterval(timer);timer=null;
 if(!realRequestStore&&step===2)body=`<div class="gj-notification-banner"><strong>Hospital response notifications</strong><p>We will notify your request email when a hospital responds. You can compare quotations and select a hospital without an account.</p>${link('Demo email previews','notifications','','','gj-text-link')}</div>`+body;
 if(!realRequestStore&&cls==='gj-select-card'){
  const r=journey.find(currentToken()),responses=(r.responses||[]).filter(response=>response.kind==='response');
  if(responses.length){
   body=body.replace('No hospital has responded yet.','Hospital responses are available below. Quotations are pending.');
   body+=`<section class="gj-hospital-responses"><h2>Hospital Responses</h2>${responses.map(response=>`<article class="gj-notification-item"><h3>${esc(hById(response.hospital).name)}</h3><p>${esc(response.message)}</p></article>`).join('')}</section>`;
  }
 }
 if(notificationDemo&&cls==='gj-dashboard-card')body=dashboardNotifications()+body;
 if(notificationDemo)body=`<nav class="gj-notification-nav" aria-label="Demo navigation">${link('Demo email previews','notifications','','','gj-text-link')}${link('New Request','new','','','gj-text-link')}${currentAccount()?link('My Dashboard','dashboard','','','gj-text-link'):link('Demo Sign In','demo-sign-in','','','gj-text-link')}</nav>`+body;
 if(realRequestStore){body=body.replace('Demo submission only.','').replace('Local previews only; photos are not uploaded.','Photos are stored privately and accessible only to authorized users.').replace('I consent to sharing this request with partner hospitals in this demo.','I consent to sharing my personal information, photos and medical information with authorized partner hospitals for treatment consultations and quotations.').replace(/<details class="gj-demo-controls"><summary>Demo response timing<\/summary>[\s\S]*?<\/details>/,'');}
 host.innerHTML=`<div class="gj-container">${realRequestStore?'':`<div class="gj-toolbar">${link('← Back to website','home','','','gj-text-link')}<span>${realRequestStore?'YOUR PRIVATE TREATMENT REQUEST':'INTERACTIVE DEMO ? No real payments or account changes'}</span>${!realRequestStore&&currentAccount()?link('My Dashboard','dashboard','','','gj-text-link'):websiteSignIn()}</div>`}<ol class="gj-progress">${steps.map((s,i)=>`<li class="${i+1<step?'done':''}" ${i+1===step?'aria-current="step"':''}><span>${i+1}</span>${s}</li>`).join('')}</ol><section class="gj-card ${cls}"><p role="status" class="gj-notice">${esc(notice)}</p><p role="alert" id="gj-error" class="gj-error"></p>${title?`<h1 tabindex="-1">${title}</h1>`:''}${body}</section><p class="gj-disclaimer">${realRequestStore?'Your request and photos will be saved privately. You will receive a private access link by email.':'Frontend preview. Fictional hospitals and quotations. QR payments, email previews, accounts, and chats are simulated. Use fictional information and a demo-only password.'}</p></div>`;notice='';
}
function procedureImage(r){return {'Eye Surgery':'eye.jpg','Rhinoplasty':'nose.jpg','Lip Surgery':'lips.jpg','Facial Contouring':'contour.jpg','Breast Surgery':'breast.jpg','Liposuction':'body.jpg','Face Lifting':'lifting.jpg'}[r.procedures?.[0]||r.procedure]||'other.jpg';}
function clearPhotos(){urls.forEach(URL.revokeObjectURL);urls=[];files=[];}
function formView(edit=null){
 const key=edit?'edit:'+edit.token:newFormKey();
 if(!edit && formState.expire()){formViews.delete(newFormKey());draft=null;clearPhotos();}
 const cached=formViews.get(key);
 activeFormKey=key;
 if(cached && host.replaceChildren){host.replaceChildren(...cached.nodes);files=cached.files;previews();return;}
 files=edit?[]:formState.files;
 if(!edit && !draft && Object.keys(formState.values).length)draft=formState.values;

 const a=realRequestStore?null:currentAccount(),profile=a?.profile||a||{},d=edit||draft||{},personal=edit||(a?profile:draft)||profile;
 const selected=d.procedures||[new URLSearchParams(location.hash.split('?')[1]||'').get('procedure')||''];
 const options=JSON.parse(document.body.dataset.requestProcedures||'[]'),year=Number(today().slice(0,4));
 let prefix='+66',national=personal.phone_number||'';for(const code of ['+886','+852','+66','+82','+84','+44','+81','+86','+61','+65','+1'])if(national.startsWith(code)){prefix=code;national=national.slice(code.length);break;}
 wrap('',`<a class="gj-close" href="${a?path('dashboard'):path('home')}" aria-label="Close request form">×</a><p class="gj-eyebrow">YOUR NEXT CHAPTER</p><h1 tabindex="-1">Let’s begin your<br>beauty journey.</h1><p class="gj-subtitle">Tell us about your goals. Your request will be shared privately with authorized partner hospitals.<br><small>Fields marked <em>*</em> are required. Demo submission only.</small></p><form data-form="request" ${edit?`data-token="${edit.token}"`:''}>
 ${a&&!edit?`<section class="gj-saved-profile"><h2>Personal Information</h2><p>Your saved profile information will be used automatically.</p><dl class="gj-summary">${[['Full Name','name'],['Email Address','email'],['Phone Number','phone_number'],['LINE ID','line_id'],['Date of Birth','date_of_birth'],['Gender','gender']].map(([label,key])=>`<dt>${label}</dt><dd data-saved-profile="${key}">${esc(profile[key]||'Not provided')}</dd>`).join('')}</dl>${button('Edit Profile','edit-request-profile')}<p class="gj-help">Email changes require separate verification.</p></section>`:''}
 <div data-request-personal>
 ${field('Full Name <em>*</em>','name','text',personal.name||'','required maxlength="80" autocomplete="name" placeholder="Enter your full name"')}
 ${field('Email Address <em>*</em>','email','email',personal.email||'','required maxlength="254" autocomplete="email" placeholder="you@example.com" '+(a?'readonly':''))}
 <div class="gj-grid gj-phone-grid">${field('LINE ID','line_id','text',personal.line_id||'','maxlength="100"')}<label>Phone Number<div class="gj-phone"><select name="country_code" aria-label="Phone country code">${['+66','+82','+84','+1','+44','+81','+86','+61','+65','+852','+886'].map(c=>`<option ${c===prefix?'selected':''}>${c}</option>`).join('')}</select><input name="national_phone" type="tel" value="${esc(national)}" placeholder="81 234 5678" maxlength="25" autocomplete="tel-national"></div></label></div>
 <div class="gj-grid"><div>${field('Date of Birth <em>*</em>','date_of_birth','date',personal.date_of_birth||'',`required min="${year-120}-01-01" max="${today()}" autocomplete="bday" aria-describedby="gj-calculated-age"`)}<small id="gj-calculated-age" class="gj-help" data-calculated-age role="status">${esc(ageLabel(personal.date_of_birth))}</small></div><label>Gender <em>*</em><select name="gender" required><option value="">Select gender</option>${['Female','Male'].map(g=>`<option ${personal.gender===g?'selected':''}>${g}</option>`).join('')}</select></label></div>
 </div>
 ${a&&!edit?button('Save Profile','save-request-profile','','gj-button gj-gold'):''}
 <fieldset class="gj-procedures"><legend>Procedures of Interest <em>*</em></legend><div class="gj-grid">${options.map(p=>`<label class="gj-check"><input type="checkbox" name="procedures[]" value="${esc(p)}" ${selected.includes(p)?'checked':''}>${esc(p)}</label>`).join('')}</div></fieldset>
 <label>Your Beauty Goals <em>*</em><textarea name="goals" rows="3" required maxlength="1000" placeholder="Tell us what you have in mind...">${esc(d.goals||d.description||'')}</textarea></label>
 <label>Preferred Treatment Date <em>*</em><span class="gj-grid"><select name="preferred_surgery_month" required aria-label="Preferred Treatment Month"><option value="">Select month</option>${months.map((m,i)=>`<option value="${i+1}" ${Number(d.preferred_surgery_month||d.date?.slice(5,7))===i+1?'selected':''}>${m}</option>`).join('')}</select><select name="preferred_surgery_year" required aria-label="Preferred Treatment Year"><option value="">Select year</option>${Array.from({length:11},(_,i)=>`<option ${Number(d.preferred_surgery_year||d.date?.slice(0,4))===year+i?'selected':''}>${year+i}</option>`).join('')}</select></span></label>
 <label>Upload Photos</label><div class="gj-previews"></div><label class="gj-add-photo" tabindex="0">＋<small>Add Photos</small><input type="file" data-photos multiple accept="image/jpeg,image/png,image/webp" aria-label="Add photos"></label><small class="gj-help">Up to 10 JPG, PNG or WebP images, 5 MB each. Local previews only; photos are not uploaded. ${edit?'Reselect any photos you need.':''}</small>
 <label>${(realRequestStore||notificationDemo)?'Medical Information':'Additional Notes'}<textarea name="${(realRequestStore||notificationDemo)?'medical_information':'additional_notes'}" maxlength="3000" rows="3" placeholder="${(realRequestStore||notificationDemo)?'Medical conditions, allergies, current medications, or previous surgeries.':'Anything else you would like us to know...'}">${esc(((realRequestStore||notificationDemo)?d.medical_information:d.additional_notes)||'')}</textarea></label>
 <label class="gj-check gj-consent"><input type="checkbox" name="consent_to_share" value="1" required ${edit?'checked':''}>I consent to sharing this request with partner hospitals in this demo. <em>*</em></label><details class="gj-demo-controls"><summary>Demo response timing</summary><label class="gj-check"><input type="checkbox" name="wait_for_quotes" value="1">Start with a pending request, without sample quotations</label></details><button class="gj-button gj-gold gj-full">${edit?'Save Request':'Submit Request'}</button></form>`,1,'gj-form-card');
 const requestForm=host.querySelector('form[data-form="request"]');
 if(!edit){
  if(!a)formState.apply(requestForm);
  else {
   const safeOnly={...formState.values};
   for(const key of ['name','email','phone_number','line_id','date_of_birth','gender','country_code','national_phone'])delete safeOnly[key];
   const saved=formState.values;formState.values=safeOnly;formState.apply(requestForm);formState.values=saved;
   configureProfileFields(requestForm,profile);
  }
  if(requestForm?.insertAdjacentHTML)requestForm.insertAdjacentHTML('afterbegin',`<p class="gj-help" role="status">${formState.restored?'Your saved selections have been restored. ':''}Selections are saved in this tab for up to 24 hours. Personal details and photos stay on this page only; after refreshing or leaving the site, enter them and upload photos again.</p>${button('Start New Request','start-new-request','','gj-button gj-outline')}`);
 }
 if(host.childNodes)formViews.set(key,{nodes:[...host.childNodes],files});
 previews();
}
function configureProfileFields(form,profile,editing=false){
 if(!form?.querySelectorAll)return;
 const missing=['name','date_of_birth','gender'].some(key=>!profile[key]);
 const container=form.querySelector('[data-request-personal]');if(container)container.hidden=!editing&&!missing;
 for(const field of form.querySelectorAll('[data-request-personal] input,[data-request-personal] select')){
  if(field.name==='email')field.readOnly=true;
  const requiredKey=field.name==='name'||field.name==='date_of_birth'||field.name==='gender';
  const label=field.closest('label')||field.parentElement;
  if(label)label.hidden=!editing && !(requiredKey&&!profile[field.name]);
 }
 const saveButton=host.querySelector('[data-action="save-request-profile"]');
 if(saveButton)saveButton.hidden=!editing&&!['name','date_of_birth','gender'].some(key=>!profile[key]);
}
function quoteCard(r,q,selected){const h=hById(q.hospital);return `<article data-request-token="${r.token}" class="gj-hospital-card ${selected?'selected':''}"><div class="gj-hospital-image">${image(h.cover,'Illustrative '+h.name)}<span class="gj-selected">${selected?'Selected':'DEMO'}</span>${button('<span class="gj-sr-only">Save hospital</span>'+icon('heart'),'favorite',q.hospital,'gj-heart'+(r.favorites?.includes(q.hospital)?' saved':''))}</div><div class="gj-hospital-body"><strong>${esc(h.name)}</strong><p class="gj-rating">★ <span>${h.rating}</span> <small>(${h.reviewCount} demo reviews)</small></p><div class="gj-hospital-location"><span>${icon('pin')}${esc(h.location)}</span>${button('View Details '+icon('arrow'),'quote-details',q.id,'gj-text-link')}</div></div></article>`;}
function selection(r){const q=r.quotes.find(q=>q.id===r.selectedQuote)||r.quotes[0];
 wrap('Select a Hospital',`<p class="gj-subtitle">Choose a hospital to proceed with your request.</p><div class="gj-quote-tabs">${r.quotes.map(quote=>button(esc(hById(quote.hospital).name),'select',quote.id,'gj-button '+(q?.id===quote.id?'gj-gold':'gj-outline'))).join('')}</div><div class="gj-quotation-options">${r.quotes.length?r.quotes.map(quote=>`<div>${quoteCard(r,quote,q?.id===quote.id)}${r.quotes.length>1?button(q?.id===quote.id?'Selected':'Select Hospital','select',quote.id,'gj-button '+(q?.id===quote.id?'gj-gold':'gj-outline')):''}</div>`).join(''):'<div class="gj-empty">No hospital has responded yet.</div>'}</div>${q?`<article class="gj-treatment"><h2>Quotation for ${esc(r.procedure)}</h2><p>${esc(q.details)}</p><strong class="gj-price gj-align-right">${money(q.total)}</strong><div class="gj-line"><span>Estimated Total Price</span><span>${money(q.total)}</span></div><div class="gj-deposit-box"><div class="gj-line"><strong>Deposit Amount</strong><strong class="gj-price">${money(journey.deposit(q))}</strong></div><small>5% of the quotation, between THB 2,000–5,000, capped at the total price.</small></div><div class="gj-line"><span>Remaining Balance</span><span>${money(q.total-journey.deposit(q))}</span></div><h3>What’s included?</h3><ul class="gj-included">${(q.included||['Consultation fee','Surgery fee','Standard medication','Follow-up care']).map(i=>`<li><span>✓</span>${esc(i)}</li>`).join('')}</ul>${adminOverride(r,q)}${r.booking?link('View Payment','success',r.token,'','gj-button gj-gold gj-full'):button('Proceed to Payment','checkout',q.id,'gj-button gj-gold gj-full')}</article>`:''}<details class="gj-demo-controls"><summary>Demo quotation controls</summary><p>These fictional quotations are for comparison only.</p>${r.hospitals.filter(id=>!r.quotes.some(q=>q.hospital===id)).map(id=>button('Simulate '+hById(id).name,'respond',id)).join('')||'<p>All selected hospitals have responded.</p>'}</details>${actions(link('View Request','details',r.token),link('Customer Support','support',r.token))}`,2,'gj-select-card');
}
function adminOverride(r,q){return host?.dataset?.adminId?`<details class="gj-demo-controls"><summary>Admin: override demo deposit</summary><form data-form="override" data-token="${r.token}" data-quote="${q.id}">${field('Deposit Amount','amount','number',journey.deposit(q),`min="${Math.min(2000,q.total)}" max="${Math.min(5000,q.total)}" step="0.01" required`)}<button class="gj-button gj-outline">Save Demo Override</button></form></details>`:'';}
function details(r,id=''){const q=r.quotes.find(q=>q.id===id);wrap(q?'Quotation Details':'Request Details',`<p class="gj-eyebrow">${esc(r.id)}</p><h2>${esc(r.procedure)}</h2><span class="gj-badge">${journey.status(r)}</span><dl class="gj-summary"><dt>Customer</dt><dd>${esc(r.name)}</dd><dt>Preferred Treatment</dt><dd>${esc(periodLabel(r.date))}</dd><dt>Beauty Goals</dt><dd>${esc(r.description)}</dd><dt>Additional Notes</dt><dd>${esc(r.additional_notes||'Not provided')}</dd></dl>${q?`${quoteCard(r,q,true)}<p>${esc(q.details)}</p><dl class="gj-summary"><dt>Quotation Price</dt><dd>${money(q.total)}</dd><dt>Deposit Amount</dt><dd>${money(journey.deposit(q))}</dd><dt>Remaining Balance</dt><dd>${money(q.total-journey.deposit(q))}</dd></dl>`:''}${actions(link('Back to Hospital Selection','status',r.token),journey.editable(r)?link('Edit Request','edit',r.token):button('Duplicate Request','duplicate',r.token))}`,2,'gj-detail-card');}
function qrPlaceholder(){return `<div class="gj-qr"><div class="gj-qr-brand">⊞ THAI QR<br><small>PAYMENT · DEMO</small></div><div class="gj-promptpay">Prompt<span>Pay</span></div><div class="gj-qr-placeholder" role="img" aria-label="Non-payable demo QR placeholder, cannot be scanned"><span>DEMO</span><small>NOT SCANNABLE</small></div><small>No payment account connected</small></div>`;}
function checkout(r,id){if(journey.paymentState(r.token)==='pending_verification'){paymentReview(r);return;}if(['failed','expired','refunded'].includes(journey.paymentState(r.token))){paymentStateView(r);return;}if(r.booking){go('success',r.token);return;}const q=r.quotes.find(q=>q.id===id);if(!q)throw Error('Select an available quotation.');const p=journey.beginPayment(r.token,q.id);save();
 wrap('Complete Your Deposit Payment',`<p class="gj-subtitle">Please scan the QR code below to pay the deposit amount.</p><div class="gj-payment-panel">${qrPlaceholder()}<p class="gj-center">Deposit Amount<strong class="gj-payment-amount">${money(p.deposit)}</strong></p><div class="gj-line"><span>Reference No.</span><strong>${esc(p.reference)}</strong>${button('⧉','copy',p.reference,'gj-copy')}</div></div><dl class="gj-summary gj-small"><dt>Hospital</dt><dd>${esc(hById(p.hospital).name)}</dd><dt>Request ID</dt><dd>${r.id}</dd><dt>Total Price</dt><dd>${money(p.total)}</dd><dt>Remaining Balance</dt><dd>${money(p.total-p.deposit)}</dd></dl><div class="gj-payment-warning"><span>ⓘ</span><p>Please pay within <strong data-countdown>30:00</strong>.<br><span data-payment-status>Waiting for Payment · DEMO</span></p></div><p class="gj-demo-label">NON-PAYABLE DEMO QR · No money is collected or transferred.</p>${button('I have completed the payment','payment-claim',q.id,'gj-button gj-outline gj-full')}<p class="gj-help gj-center">This action does not verify payment. Use the separate simulated provider confirmation below.</p><details class="gj-demo-controls"><summary>Demo payment provider</summary><p>Simulate a verified provider event. Production requires a server-side webhook, not a customer click.</p>${button('Simulate verified payment','provider-confirm',q.id,'gj-button gj-gold gj-full')}</details>${actions(link('Back','status',r.token),button('Generate new reference','restart-payment',q.id))}`,3,'gj-payment-card');updateCountdown(r);timer=setInterval(()=>updateCountdown(r),1000);
}
function demoStatusControls(r){return `<details class="gj-demo-controls"><summary>Demo payment status - simulation only</summary><p>No bank verification or real funds. This control simulates a provider response.</p>${button('Simulate verified payment','provider-confirm')}${button('Simulate failed','demo-payment-state','Failed')}${button('Simulate expired','demo-payment-state','Expired')}${button('Simulate refunded','demo-payment-state','Refunded')}</details>`;}
function paymentReview(r){
 if(journey.paymentState(r.token)!=='pending_verification'){go(journey.resume(r.token),r.token,r.payment?.quote||'');return;}
 const p=r.payment;
 wrap('Payment Under Review',`<div class="gj-review-icon">${icon('shield')}</div><p class="gj-subtitle">Your payment report is awaiting verification. Please do not pay again.</p><dl class="gj-summary"><dt>Booking ID</dt><dd>${esc(r.booking?.id||r.pendingBookingId||'BK-DEMO-'+r.id.replace('REQ-DEMO-',''))}</dd><dt>Selected Hospital</dt><dd>${esc(hById(p.hospital)?.name||'Demo hospital')}</dd><dt>Procedure</dt><dd>${esc(r.procedure)}</dd><dt>Deposit Amount</dt><dd>${money(p.deposit)}</dd><dt>Payment Status</dt><dd>Pending Verification - DEMO</dd></dl><p>You can safely return later using your private demo return link. Clicking I Have Paid is not proof of payment.</p>${actions(button('Check Payment Status','check-payment'),link('Contact Support','support',r.token),button('Mock return email','return-email'))}${demoStatusControls(r)}<p class="gj-demo-label">SIMULATED STATUS - No bank has verified this payment.</p>`,4,'gj-review-card');
}
function paymentStateView(r){const status=journey.paymentState(r.token);wrap('Payment '+status,`<p>${status==='refunded'?'This demo payment is marked refunded. Contact support for help.':'This demo payment cannot be used. Check the status before starting a new reference.'}</p>${status==='refunded'?'':button('Restart demo payment safely','restart-payment',r.payment?.quote)}${link('Contact Support','support',r.token)}${demoStatusControls(r)}`,3,'gj-payment-card');}
async function returnEmail(r){await resumeWrite;wrap('Mock Guest Return Email',`<p>This is a demo preview. No email has been sent. Keep this private link; it expires in 7 days.</p>${resumeLink?`<a class="gj-button gj-gold" href="${esc(resumeLink)}">Resume your existing booking</a>`:'<p>Cross-device preview is available only in the local/testing demo with its resume store enabled.</p>'}`,4);}
function updateCountdown(r){const target=host.querySelector('[data-countdown]');if(!target)return;const remaining=Math.max(0,Math.ceil((r.payment.expires-Date.now())/1000));target.textContent=String(Math.floor(remaining/60)).padStart(2,'0')+':'+String(remaining%60).padStart(2,'0');if(!remaining){host.querySelector('[data-payment-status]').textContent='Payment Expired';host.querySelector('[data-action="payment-claim"]').disabled=true;host.querySelector('[data-action="provider-confirm"]').disabled=true;clearInterval(timer);r.payment.status='Expired';save();}}
function success(r){if(!r.booking)throw Error('Confirm the simulated provider payment first.');const b=r.booking;
 wrap('',`<div class="gj-success-icon">${icon('check')}</div><h1 tabindex="-1">Payment Successful!</h1><p class="gj-center gj-subtitle">We have confirmed your deposit payment<br>of <strong>${money(b.deposit)}</strong> in this simulation.</p><div class="gj-receipt"><div class="gj-line"><span>Amount Paid</span><strong class="gj-price">${money(b.deposit)}</strong></div><div class="gj-line"><span>Payment Method</span><span>PromptPay (Thai QR)</span></div><div class="gj-line"><span>Reference No.</span><span>${esc(b.reference||b.id)}</span></div><div class="gj-line"><span>Request ID</span><span>${r.id}</span></div><div class="gj-line"><span>Payment Date</span><span>${new Date(b.paidAt).toLocaleString('en-GB',{timeZone:'Asia/Bangkok',dateStyle:'medium',timeStyle:'short'})}</span></div></div><div class="gj-mail-icon">${icon('mail')}</div><p class="gj-center gj-subtitle">${r.owner?'Your booking is linked to your account.':r.emailRequest?'Check your email for the verification code.':'We’ve sent a 6-digit verification code to your email address (simulated).'}<br><strong>${esc(r.email)}</strong></p>${link('Continue',r.owner?'dashboard':'inbox',r.owner?'':r.token,'','gj-button gj-gold gj-full')}<p class="gj-help gj-center">${r.owner?'You do not need to activate your account again.':'Please check the email preview for the verification code.'}</p><p class="gj-demo-label">SIMULATED PAYMENT · No real funds received</p>`,4,'gj-success-card');
}
function inbox(r){if(!r.booking)throw Error('Complete the simulated deposit first.');if(r.owner){go('dashboard');return;}
 wrap('',`<div class="gj-email-toolbar"><span>←</span><span>▣　♧　✉　⋮</span></div><h2 class="gj-email-subject">Your MedicalBeautiful<br>Verification Code</h2><div class="gj-email-sender"><span class="gj-avatar">M</span><div><strong>MedicalBeautiful</strong><small>${r.emailRequest?'Check your real inbox':'Demo inbox · no real email sent'}</small><small>to ${esc(r.email)}</small></div></div><article class="gj-email-letter"><div class="gj-lotus">♧</div><p class="gj-wordmark">MEDICALBEAUTIFUL</p><h1 tabindex="-1">Your Verification Code</h1><p>Thank you for your deposit payment simulation. Please enter the verification code below to confirm your email and complete your registration.</p>${r.emailRequest?'<p>The real email code is not displayed here.</p>':`<div class="gj-email-code">${esc(r.otp?.code||'Expired')}</div><p class="gj-center gj-help">This code will expire in 10 minutes.</p>`}<div class="gj-payment-warning">ⓘ　If you didn’t make this request, please ignore this email.</div><p>Best regards,<br>MedicalBeautiful Team</p></article>${link('Enter Verification Code','verify',r.token,'','gj-button gj-gold gj-full')}`,5,'gj-email-card');
}
function digitInputs(){return `<div class="gj-digit-inputs">${Array.from({length:6},(_,i)=>`<input type="text" name="digit${i}" data-digit="${i}" inputmode="numeric" autocomplete="${i===0?'one-time-code':'off'}" pattern="[0-9]" maxlength="1" aria-label="Digit ${i+1}" required>`).join('')}</div>`;}
function verifyView(r){if(!r.emailRequest&&r.booking&&!journey.verified(r)&&(!r.otp||r.otp.expires<=Date.now())){journey.issue(r.token);save();}if(r.owner){go('dashboard');return;}if(!r.booking)throw Error('Complete the demo payment first.');
 wrap('',`<div class="gj-mail-icon">${icon('shield')}</div><h1 tabindex="-1">Enter Verification Code</h1><p class="gj-center gj-subtitle">Please enter the 6-digit code sent to<br><strong>${esc(r.email)}</strong></p><form data-form="verify" data-token="${r.token}">${digitInputs()}<button class="gj-button gj-gold gj-full">Verify</button></form><p class="gj-help gj-center">Didn’t receive the code? ${button('Resend Code','resend','','gj-text-link')} <span data-resend-countdown></span></p><p class="gj-help gj-center">${r.emailRequest?'Code expires in 5 minutes.':'Demo code expires in 10 minutes; maximum 5 attempts.'}</p>${actions(link('Back to Email','inbox',r.token))}`,6,'gj-verify-card');
 const resend=host.querySelector('[data-action="resend"]'),update=()=>{const s=Math.max(0,Math.ceil(((r.otp?.sent||0)+30000-Date.now())/1000));resend.disabled=!!s;host.querySelector('[data-resend-countdown]').textContent=s?'('+s+'s)':'';};update();timer=setInterval(update,1000);
}
function account(r){if(!journey.verified(r))throw Error('Verify your booking first.');if(journey.state.accounts.some(a=>a.email===r.email)){go('sign-in',r.token);return;}
 wrap('',`<div class="gj-account-intro"><h1 tabindex="-1">Create Your Account</h1><p class="gj-subtitle">Set up your account to access your quotations, and chat with hospitals.</p><p class="gj-help">Verified email: <strong>${esc(r.email)}</strong><br>Your original request and paid demo booking will be linked automatically.</p></div><form data-form="account" data-token="${r.token}"><label>Username <em>*</em><span class="gj-input-status"><input name="username" required minlength="3" maxlength="30" autocomplete="username" value="${esc(r.email.split('@')[0].replace(/[^a-zA-Z0-9_.-]/g,''))}"><span data-username-status aria-live="polite"></span></span></label><p class="gj-help" data-username-help>3–30 characters, starting with a letter.</p>${passwordInput('Password','password')}<div class="gj-strength"><span></span><span></span><span></span><span></span><span></span><small data-strength-label>Use 8+ characters with upper/lowercase letters and a number.</small></div>${passwordInput('Confirm Password','confirm')}<label class="gj-check gj-consent"><input type="checkbox" name="terms" value="1" required><span>I agree to the <button type="button" class="gj-text-link" data-action="policy" data-value="terms">Terms of Service</button> and <button type="button" class="gj-text-link" data-action="policy" data-value="privacy">Privacy Policy</button>.</span></label><button class="gj-button gj-gold gj-full">Create Account</button></form>`,7,'gj-account-card');
}
function passwordInput(label,name){return `<label>${label} <em>*</em><span class="gj-password"><input type="password" name="${name}" required minlength="8" autocomplete="new-password"><button type="button" data-action="password" data-value="${name}" aria-label="Show ${label.toLowerCase()}" aria-pressed="false">${icon('eye')}</button></span></label>`;}
async function digest(password,salt){const buffer=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(salt+password));return [...new Uint8Array(buffer)].map(v=>v.toString(16).padStart(2,'0')).join('');}
function signIn(r){wrap('Welcome Back',`<p class="gj-subtitle">${r?'This verified email already has an account. Sign in to link your guest booking.':'Sign in to your demo customer account.'}</p><form data-form="sign-in">${field('Email or Username','identity','text',r?.email||'','required autocomplete="username"')}${passwordInput('Password','password')}<button class="gj-button gj-gold gj-full">Sign In</button></form>${actions(link('Forgot Password','forgot'),link('New Guest Request','new'))}<p class="gj-help">Existing website account? <a href="${esc(document.body.dataset.loginUrl||'login')}">Sign in to the existing website</a>.</p>`,8,'gj-signin-card');}
const tabs=[['My Requests','profile'],['My Quotations','profile'],['My Chats','chat'],['My Profile','user'],['Settings','settings']];
function dashboard(tab='My Requests'){
 const a=currentAccount();if(!a){go('sign-in');return;}const rs=journey.state.requests.filter(r=>r.owner===a.email),paid=rs.filter(r=>r.booking);let body='';
 if(['Dashboard','My Requests'].includes(tab)){
  const filters=['All','Pending','Quotation','Confirmed','Cancelled'];
  const matches=(r,f)=>f==='All'||f==='Quotation'&&r.quotes.length&&!r.booking||f==='Pending'&&!r.quotes.length&&!r.booking||f==='Confirmed'&&!!r.booking||f==='Cancelled'&&r.status==='Cancelled';
  body=`<div class="gj-dashboard-heading"><h1 tabindex="-1">My Requests</h1>${link('＋ New Request','new','','','gj-button gj-gold')}</div><nav class="gj-filters" aria-label="Request status filters">${filters.map(f=>button(f+' ('+rs.filter(r=>matches(r,f)).length+')','filter',f,dashboardFilter===f?'active':'')).join('')}</nav><div class="gj-request-rows">${rs.filter(r=>matches(r,dashboardFilter)).map(r=>`<article class="gj-request-row">${image(procedureImage(r),r.procedure)}<div><strong>${r.id}</strong><span class="gj-badge ${r.booking?'paid':r.quotes.length?'quoted':'pending'}">${journey.status(r)}</span><h3>${esc(r.procedure)}</h3><p>${esc(r.booking?hById(r.booking.hospital)?.name:'No hospital selected')}</p><small>Submitted: ${new Date(r.created).toLocaleDateString('en-GB',{dateStyle:'medium'})}</small><small>Payment: ${r.booking?'Deposit Paid':r.payment?.status==='Pending'?'Pending Payment':'Not Started'}</small></div><div class="gj-row-actions">${link('→ View','details',r.token)}${journey.editable(r)?link('♧ Edit','edit',r.token):'<button class="gj-button gj-outline" disabled title="A quotation has been issued. Create a new request for revised pricing.">Edit locked</button>'}${button('Duplicate','duplicate',r.token)}</div></article>`).join('')||'<p class="gj-empty">No requests in this category.</p>'}</div>`;
 }else if(tab==='My Quotations')body=`<h1 tabindex="-1">My Quotations</h1>${rs.flatMap(r=>r.quotes.map(q=>`<article class="gj-dashboard-quote"><div>${quoteCard(r,q,r.booking?.quote===q.id)}</div><div><h2>${esc(r.procedure)}</h2><strong class="gj-price">${money(q.total)}</strong><p>Deposit: ${money(journey.deposit(q))}</p>${link('View Quotation','details',r.token,q.id)}</div></article>`)).join('')||'<p>No quotations received yet.</p>'}`;
 else if(['My Chats','Booking Chat','Customer Support'].includes(tab))body=`<h1 tabindex="-1">My Chats</h1>${link('Customer Support','support')}<h2>Hospital Booking Chats</h2>${paid.map(r=>`<div class="gj-chat-row"><strong>${esc(hById(r.booking.hospital)?.name)}</strong>${link('Open Chat','chat',r.token)}</div>`).join('')||'<p>Hospital chats unlock after simulated deposit confirmation.</p>'}`;
 else if(tab==='My Profile')body=`<h1 tabindex="-1">My Profile</h1><dl class="gj-summary">${Object.entries(a.profile||{name:a.name,email:a.email}).map(([k,v])=>`<dt>${esc({name:'Full Name',email:'Email',line_id:'LINE ID',phone_number:'Phone',date_of_birth:'Date of Birth',gender:'Gender'}[k]||k)}</dt><dd>${esc(v||'Not provided')}</dd>`).join('')}</dl><p>These details are pre-filled on every new request. Treatment choices and photos are independent.</p>`;
 else if(['My Bookings','Payments'].includes(tab))body=`<h1 tabindex="-1">${tab}</h1>${paid.map(r=>`<article class="gj-chat-row"><div><strong>${r.booking.id}</strong><p>Deposit Paid · ${money(r.booking.deposit)} · DEMO</p></div>${link('View Payment','success',r.token)}</article>`).join('')||'<p>No simulated payments yet.</p>'}`;
 else body=`<h1 tabindex="-1">Settings</h1><p>This demo account and request history are stored in this browser tab session. Sign out preserves them for returning-customer testing.</p>${button('Logout','signout')}`;
 wrap('',`<div class="gj-dashboard"><aside><div class="gj-dashboard-brand">MEDICALBEAUTIFUL <span>☰</span></div><div class="gj-dashboard-user"><span class="gj-avatar">${esc(a.name[0])}</span><div><strong>${esc(a.name)}</strong><small>${esc(a.email)}</small></div></div><nav>${tabs.map(([t,i])=>`<a class="gj-side-link ${tab===t||tab==='Dashboard'&&t==='My Requests'?'active':''}" href="${path('dashboard',t)}">${icon(i)}${t}</a>`).join('')}${button(icon('logout')+' Logout','signout','','gj-side-link')}</nav></aside><div class="gj-dashboard-content">${body}</div></div>`,8,'gj-dashboard-card');
}
const supportMessages=[];
function chat(r,channel){if(channel==='booking'&&!r?.booking){wrap('Booking Chat',`<p>Select a hospital and complete the demo deposit first.</p>${r?link('Back to Request','status',r.token):link('New Request','new')}`,8);return;}const messages=r?.messages.filter(m=>m.channel===channel)||supportMessages;wrap(channel==='booking'?'Booking Chat':'Customer Support',`<p class="gj-subtitle">${channel==='booking'?'Participants: Customer, '+esc(hById(r.booking.hospital).name)+', and MedicalBeautiful Support. Other hospitals are excluded.':'Available before and after booking. No real messages are sent.'}</p><div class="gj-messages">${messages.map(m=>`<article><strong>${esc(m.role)}</strong><p>${esc(m.text)}</p></article>`).join('')||'<p>No messages yet.</p>'}</div><form data-form="chat" data-channel="${channel}" ${r?`data-token="${r.token}"`:''}><label>Message<textarea name="text" required maxlength="2000" rows="3"></textarea></label><button class="gj-button gj-gold">Send Demo Message</button></form>${link('Back to Dashboard','dashboard')}`,8,'gj-chat-card');}
function previews(){const list=host.querySelector('.gj-previews');if(!list)return;urls.forEach(URL.revokeObjectURL);urls=files.map(f=>URL.createObjectURL(f));list.innerHTML=files.map((f,i)=>`<figure><img src="${urls[i]}" alt="Local selected photo preview ${i+1}">${button('×','remove-photo',i,'gj-remove-photo')}<figcaption>${esc(f.name)}</figcaption></figure>`).join('');}
async function hydrate(token){try{const r=await api(mailUrl(token));const existing=journey.state.requests.find(item=>item.token===token);if(existing)Object.assign(existing,{accessUntil:r.accessUntil,emailRequest:true});else journey.state.requests.push(r);save();go('status',token);}catch(error){wrap('Request Access',`<p>${esc(error.message)}</p>${link('New Request','new')}`);}}
function notificationEmails(){
 const emails=(journey.state.notifications||[]).filter(n=>n.emailSent).slice().reverse();
 const pending=journey.state.requests.filter(r=>!r.booking&&!r.emailRequest);
 wrap('Demo Email Notifications',`<p class="gj-subtitle">Simulated mailbox for testing all recipients in this browser tab. No real emails are sent.</p><div class="gj-notification-list">${emails.map(n=>`<article class="gj-notification-item"><strong>${n.kind==='replacement'?'Your replacement access link':'A hospital has responded'}</strong><p>Request ID: ${esc(n.requestId)}</p><small>To: ${esc(n.recipient)}</small>${link('Preview Email','notification-email',n.id)}</article>`).join('')||'<p>No email notifications yet. Submit a request or simulate a hospital response below.</p>'}</div><details class="gj-demo-controls"><summary>Simulate hospital responses</summary><p>Each response creates one notification. Replaying the same response does not send another email.</p>${pending.map(r=>`<article data-request-token="${esc(r.token)}"><h3>${esc(r.id)}</h3>${r.hospitals.map(id=>`<p>${button((r.quotes.some(q=>q.hospital===id)?'Replay response: ':'New quotation: ')+hById(id).name,'respond',id)} ${button('New hospital message','demo-reply',id)}</p>`).join('')}</article>`).join('')||'<p>No pending demo requests.</p>'}</details>${link('Request a replacement email link','replacement','','','gj-text-link')}`,2,'gj-notifications-card');
}
function notificationEmail(id){
 const email=journey.notificationPreview(id);
 wrap('Your MedicalBeautiful Notification',`<div class="gj-email-sender"><span class="gj-avatar">M</span><div><strong>MedicalBeautiful</strong><small>Demo inbox · no real email sent</small><small>To: ${esc(email.recipient)}</small></div></div><article class="gj-email-letter"><p class="gj-wordmark">MEDICALBEAUTIFUL</p><h2>${email.kind==='replacement'?'Your replacement access link':'A hospital has responded to your request'}</h2><p>Request ID: <strong>${esc(email.requestId)}</strong></p><p>View the latest hospital responses and quotations securely. You can choose a hospital without registering an account.</p>${link('View Quotation','guest-link',email.requestToken,email.linkToken,'gj-button gj-gold gj-full')}<p class="gj-help">This request-specific link expires on ${new Date(email.expires).toLocaleString('en-GB',{timeZone:'Asia/Bangkok'})} (Bangkok time). Keep it private. If you did not make this request, ignore this email.</p></article>${actions(link('Back to Demo Emails','notifications'),link('Request replacement link','replacement'))}`,2,'gj-email-card');
}
function replacementLink(message=''){
 wrap('Request a Replacement Email Link',`<p class="gj-subtitle">Enter your Request ID and the email used for that request. No account is required.</p>${message?`<p class="gj-notification-banner" role="status">${esc(message)}</p>`:''}<form data-form="replacement">${field('Request ID','reference','text','','required maxlength="80" autocomplete="off"')}${field('Request Email','email','email','','required maxlength="254" autocomplete="email"')}<button class="gj-button gj-gold gj-full">Request Email Link</button></form>${link('Demo email previews','notifications','','','gj-text-link')}`,2);
}
function dashboardNotifications(){
 const account=currentAccount();if(!account)return '';
 const notifications=(journey.state.notifications||[]).filter(n=>n.dashboard&&n.recipient===account.email).slice().reverse();
 return `<section class="gj-dashboard-notifications"><h2>Quotation Notifications (${notifications.length})</h2>${button(account.emailAlerts===false?'Enable email alerts':'Disable email alerts','email-alerts','','gj-text-link')}<p class="gj-help">Dashboard notifications are always enabled. Email alerts are simulated.</p>${notifications.map(n=>`<article class="gj-notification-item"><strong>A hospital has responded</strong><p>Request ID: ${esc(n.requestId)}</p>${link('View Quotation',n.linkToken?'guest-link':'status',n.requestToken,n.linkToken||'')}</article>`).join('')||'<p>No hospital response notifications yet.</p>'}</section>`;
}
function render(){
 captureRequestForm();activeFormKey=null;selectDraftOwner();
 if(!host)return;clearInterval(timer);const match=location.hash.match(/^#journey\/([^/?]+)(?:\/([^/?]+))?(?:\/([^/?]+))?/);[...host.closest('main').children].filter(el=>el!==host).forEach(el=>el.classList.toggle('guest-journey-hidden',!!match&&match[1]!=='home'));host.hidden=!match||match[1]==='home';if(host.hidden)return;
 const [,page,a,b]=match,token=decodeURIComponent(a||''),extra=decodeURIComponent(b||'');
 if(realRequestStore&&!['new','home','how'].includes(page)){location.replace(document.body.dataset.signedIn==='true'?host.dataset.customerRequestsUrl:document.body.dataset.loginUrl);return;}
 if(notificationDemo&&['access','email'].includes(page)){replacementLink();return;}
 if(!realRequestStore&&['notifications','notification-email','replacement','guest-link'].includes(page)){
  try{
   if(page==='notifications')notificationEmails();
   else if(page==='notification-email')notificationEmail(token);
   else if(page==='replacement')replacementLink();
   else {const r=journey.openGuestLink(token,extra);save();go(journey.resume(r.token),r.token,r.payment?.quote||r.selectedQuote||'');}
  }catch(error){replacementLink('This guest access link is invalid or has expired. Request a replacement email link below.');}
  return;
 }
 if(protectedJourneyPages.has(page)&&!currentAccount()){wrap('Sign In Required','<p>Please sign in to access your requests, quotations, and messages.</p>'+actions(websiteSignIn(),link('How It Works','how'),link('Cancel','home')));document.dispatchEvent?.(new Event('customer-signin-required'));return;}
 try{let r;if(token&&!['dashboard','email'].includes(page))r=journey.find(token);if(['review','payment-state','status','details','checkout','success','inbox','verify','account','edit','chat'].includes(page))r=journey.guard(token);
 switch(page){case'my-requests':dashboard('My Requests');break;case'my-quotations':dashboard('My Quotations');break;case'my-chats':dashboard('My Chats');break;case'my-profile':case'profile':dashboard('My Profile');break;case'account-settings':case'settings':dashboard('Settings');break;case'how':wrap('How It Works','<p>Open the six-step guide from the How It Works button in the header.</p>');break;case'new':formView();break;case'edit':if(!journey.editable(r))throw Error('A quotation has been issued. Duplicate the request for revised pricing.');formView(r);break;case'email':hydrate(token);return;case'confirmation':wrap('Your Request Has Been Submitted',`<p>Open the private access link in your email to continue.</p>${link('Customer Support','support')}`);break;case'access':if(r.emailRequest){wrap('Check Your Email','<p>Open the private link in your email to access this request.</p>');break;}wrap('Access Your Demo Request',`<form data-form="access" data-token="${r.token}">${field('Email Address','email','email','','required')}<button class="gj-button gj-gold">Continue</button></form>`);break;case'resume':wrap('Resuming your demo booking','<p>Checking saved status?</p>');break;case'review':paymentReview(r);break;case'payment-state':paymentStateView(r);break;case'status':selection(r);break;case'details':details(r,extra);break;case'checkout':checkout(r,extra);break;case'success':success(r);break;case'inbox':inbox(r);break;case'verify':verifyView(r);break;case'account':account(r);break;case'sign-in':if(!r){location.replace(document.body.dataset.loginUrl||'login');return;}signIn(r);break;case'demo-sign-in':signIn(r);break;case'forgot':wrap('Account Recovery',`<p>For this demo, use the password created in this tab. Real account recovery remains on the existing website Sign In page.</p>${link('Back to Demo Sign In','demo-sign-in')}`);break;case'dashboard':dashboard(extra||token||'My Requests');break;case'support':if(r)journey.guard(token);chat(r,'support');break;case'chat':chat(r,'booking');break;default:throw Error('This page is unavailable.');}
 }catch(error){wrap('Continue Your Journey',`<p>${esc(error.message)}</p>${token?link('Access Request','access',token):''}${actions(link('New Request','new'),link('Sign In','sign-in'))}`);}host.querySelector('h1')?.focus({preventScroll:true});
}
function errorMessage(error){host.querySelector('#gj-error').textContent=error.message;}
if(host){
 document.addEventListener?.('guest-auth-changed',()=>{try{journey.state.current=JSON.parse(sessionStorage.getItem(STORAGE_KEY))?.current||null;}catch{journey.state.current=null;}});
 window.addEventListener('pagehide',()=>{captureRequestForm();formState.flush();});
 window.addEventListener('hashchange',()=>{render();host.scrollIntoView({block:'start'});});if(resumeStore)loadResume().finally(()=>render());else render();
 host.addEventListener('submit',async event=>{
  const form=event.target.closest('form');if(!form)return;event.preventDefault();const data=Object.fromEntries(new FormData(form)),token=form.dataset.token,submit=form.querySelector('button[type="submit"],button:not([type])');if(submit){submit.disabled=true;submit.dataset.previous=submit.textContent;submit.textContent='Please wait…';}
  try{switch(form.dataset.form){
   case'replacement':{const message=journey.requestReplacement(data.reference,data.email);save();replacementLink(message);break;}
   case'request':{
    const procedures=new FormData(form).getAll('procedures[]');if(!procedures.length)throw Error('Select at least one procedure.');
    if(!data.gender||!data.date_of_birth||data.date_of_birth>today()||Number(data.date_of_birth.slice(0,4))<Number(today().slice(0,4))-120)throw Error('Enter a valid date of birth and select gender.');
    const month=data.preferred_surgery_month,year=data.preferred_surgery_year;if(!month||!year)throw Error('Select your preferred treatment month and year.');const date=year+'-'+String(month).padStart(2,'0');if(date<today().slice(0,7))throw Error('Select the current month or a future month.');
    const national=(data.national_phone||'').replace(/[\s().-]/g,''),phone=national?(national.startsWith('+')?national:data.country_code+national):'';if(phone&&!/^\+[1-9][0-9]{6,14}$/.test(phone))throw Error('Enter a valid international phone number.');
    if(!data.goals?.trim()||!data.consent_to_share)throw Error('Enter your beauty goals and accept the request consent.');
    if(realRequestStore){
 const payload=new FormData(form);payload.set('phone_number',phone);form.dataset.submissionReference??=crypto.randomUUID();payload.set('submission_reference',form.dataset.submissionReference);files.forEach(file=>payload.append('photos[]',file));
 const response=await fetch(realRequestStore,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:payload});const result=await response.json().catch(()=>({}));
 if(!response.ok)throw Error(response.status===413?'The photos exceed the server upload limit. Please select smaller files.':Object.values(result.errors||{}).flat().join(' ')||result.message||'Your request could not be saved. Please try again.');
 clearGuestDraft();location.assign(result.redirect);return;
 }
 if(currentAccount()){const account=currentAccount();data.email=account.email;}
 const payload={...data,phone_number:phone,procedures,procedure:procedures.join(', '),description:data.goals,date,hospitals:hospitals.map(h=>h.id),photos:files.map(f=>f.name)};
    const live=!notificationDemo&&new URLSearchParams(location.hash.split('?')[1]||'').get('email')==='real';
    const sent=live&&mailStore?await api(mailStore,payload):null;
    const r=token?journey.edit(token,payload):journey.request(payload);if(sent){r.token=sent.token;r.id=sent.reference;r.emailRequest=true;}
    photoMemory.set(r.token,[...files]);clearGuestDraft();
    if(!r.emailRequest){journey.access(r.token,r.email);if(!token&&!data.wait_for_quotes){journey.respond(r.token,hospitals[0].id);journey.respond(r.token,hospitals[1].id);}}
    save();go(r.emailRequest?'confirmation':'status',r.token);break;
   }
   case'access':journey.access(token,data.email);save();go('status',token);break;
   case'verify':{const r=journey.guard(token),code=Array.from({length:6},(_,i)=>data['digit'+i]||'').join('');if(!/^\d{6}$/.test(code))throw Error('Enter all six digits.');let next;
    if(r.emailRequest){await api(mailUrl(token,'verify'),{code});r.verified=true;r.verifiedUntil=Date.now()+300000;next=journey.state.accounts.some(a=>a.email===r.email)?'sign-in':'account';}else next=journey.verify(token,code);save();notice='Email verified successfully.';go(next,token);break;}
   case'account':{if(data.password!==data.confirm)throw Error('Passwords do not match.');if(!strongPassword(data.password))throw Error('Use at least 8 characters with uppercase, lowercase and a number.');if(!data.terms)throw Error('Accept the Terms of Service and Privacy Policy.');const r=journey.guard(token),salt=crypto.randomUUID();journey.activate(token,r.name,await digest(data.password,salt),salt,{username:data.username,terms:true});save();go('dashboard');break;}
   case'sign-in':{const identity=data.identity.trim().toLowerCase(),a=journey.state.accounts.find(a=>a.email===identity||a.username?.toLowerCase()===identity);if(!a)throw Error('No matching demo account exists in this tab.');journey.signIn(identity,await digest(data.password,a.salt));save();go('dashboard');break;}
   case'override':journey.overrideDeposit(token,form.dataset.quote,Number(data.amount),{authorized:!!host.dataset.adminId,id:host.dataset.adminId});save();render();break;
   case'chat':if(token)journey.chat(token,form.dataset.channel,data.text);else supportMessages.push({role:'customer',text:data.text},{role:'support',text:'DEMO response: no real message was sent.'});save();render();break;
  }}catch(error){save();errorMessage(error);}finally{if(submit){submit.disabled=false;submit.textContent=submit.dataset.previous;}}
 });
 host.addEventListener('click',async event=>{
  const target=event.target.closest('[data-action]');if(!target)return;const token=target.closest?.('[data-request-token]')?.dataset.requestToken||currentToken(),value=target.dataset.value;
  try{switch(target.dataset.action){
   case'edit-request-profile':configureProfileFields(host.querySelector('form[data-form="request"]'),currentAccount()?.profile||{},true);break;
   case'save-request-profile':{
    const form=host.querySelector('form[data-form="request"]'),data=Object.fromEntries(new FormData(form));
    for(const field of form.querySelectorAll('[data-request-personal] input,[data-request-personal] select'))if(!field.checkValidity()){field.reportValidity();return;}
    const national=(data.national_phone||'').replace(/[\s().-]/g,'');
    const profile=journey.saveProfile({...data,phone_number:national?(national.startsWith('+')?national:data.country_code+national):''});save();
    host.querySelectorAll('[data-saved-profile]').forEach(node=>node.textContent=profile[node.dataset.savedProfile]||'Not provided');
    configureProfileFields(form,profile);rememberRequestInput({target:form});break;
   }
   case'email-alerts':{const account=currentAccount();if(!account)throw Error('Sign in to change notification preferences.');account.emailAlerts=account.emailAlerts===false;save();render();break;}
   case'demo-reply':journey.respond(token,value,'message-'+value,'response');save();render();break;
   case'select':{const r=journey.guard(token);if(r.booking)throw Error('This request is already booked.');r.selectedQuote=value;save();render();break;}
   case'favorite':{const r=journey.guard(token);r.favorites??=[];r.favorites=r.favorites.includes(value)?r.favorites.filter(id=>id!==value):[...r.favorites,value];save();render();break;}
   case'quote-details':go('details',token,value);break;
   case'checkout':go('checkout',token,value);break;
   case'payment-claim':journey.claimPayment(token);save();await resumeWrite;go('review',token);break;
   case'check-payment':await resumeWrite;await loadResume();go(journey.resume(token),token,journey.guard(token).payment?.quote||'');break;
   case'return-email':await returnEmail(journey.guard(token));break;
   case'demo-payment-state':{const r=journey.guard(token);if(!['Failed','Expired','Refunded'].includes(value))throw Error('Invalid demo status.');if(r.payment){r.payment.status=value;save();go('payment-state',token);}break;}
   case'provider-confirm':{target.disabled=true;const r=journey.guard(token);journey.confirmPayment(token,!r.emailRequest);save();if(r.emailRequest&&!r.owner&&!r.emailCodeSent){try{await api(mailUrl(token,'code'),{});r.emailCodeSent=true;save();}catch(error){notice=error.message;}}await resumeWrite;go('success',token);break;}
   case'restart-payment':{const r=journey.guard(token);if(r.booking||['Under Review','Paid','Refunded'].includes(r.payment?.status))throw Error('This payment cannot be restarted.');if(r.payment)r.payment.expires=0;journey.beginPayment(token,value);save();go('checkout',token,value);break;}
   case'copy':await navigator.clipboard.writeText(value);notice='Payment reference copied.';render();break;
   case'respond':journey.respond(token,value);save();render();break;
   case'resend':{const r=journey.guard(token);target.disabled=true;if(r.emailRequest){await api(mailUrl(token,'code'),{});r.emailCodeSent=true;}else journey.issue(token);save();notice=r.emailRequest?'A new code has been emailed.':'A new code is ready in your demo email preview.';render();break;}
   case'duplicate':clearGuestDraft();draft=journey.duplicate(value);formState.update({...draft,step:'request'});go('new');break;
   case'start-new-request':clearGuestDraft();go('new');break;
   case'filter':dashboardFilter=value;render();break;
   case'signout':journey.state.current=null;save();go('demo-sign-in');break;
   case'remove-photo':files.splice(Number(value),1);previews();break;
   case'password':{const input=host.querySelector('[name="'+value+'"]');input.type=input.type==='password'?'text':'password';target.setAttribute('aria-pressed',String(input.type==='text'));target.setAttribute('aria-label',(input.type==='text'?'Hide ':'Show ')+value);break;}
   case'policy':{const box=host.querySelector('.gj-policy');if(box)box.remove();else{const panel=document.createElement('div');panel.className='gj-policy';panel.innerHTML='<strong>'+esc(value==='terms'?'Demo Terms of Service':'Demo Privacy Policy')+'</strong><p>This is a frontend simulation. No real treatment, payment or account is created. Information remains in this tab session; use fictional data and a unique demo-only password. Photos are local previews only.</p>';target.closest('form').append(panel);}break;}
  }}catch(error){target.disabled=false;errorMessage(error);}
 });
 host.addEventListener('change',event=>{rememberRequestInput(event);if(!event.target.matches('[data-photos]'))return;const incoming=[...event.target.files];event.target.value='';if(files.length+incoming.length>10||incoming.some(f=>!['image/jpeg','image/png','image/webp'].includes(f.type)||!f.size||f.size>5*1024*1024)){errorMessage(Error('Choose up to 10 JPG, PNG or WebP images, each between 1 byte and 5 MB.'));return;}files.push(...incoming);if(activeFormKey===newFormKey())formState.files=files;previews();});
 host.addEventListener('input',event=>{
  rememberRequestInput(event);
  if(event.target.name==='date_of_birth')host.querySelector('[data-calculated-age]').textContent=ageLabel(event.target.value);
  const input=event.target;if(input.matches('[data-digit]')){input.value=input.value.replace(/\D/g,'').slice(-1);if(input.value)host.querySelector('[data-digit="'+(Number(input.dataset.digit)+1)+'"]')?.focus();}
  if(input.name==='username'){const valid=journey.usernameAvailable(input.value);host.querySelector('[data-username-status]').textContent=valid?'✓':'!';host.querySelector('[data-username-help]').textContent=valid?'Username available in this demo.':'Unavailable or invalid username.';}
  if(input.name==='password'&&host.querySelector('[data-strength-label]')){const score=[input.value.length>=8,/[a-z]/.test(input.value),/[A-Z]/.test(input.value),/\d/.test(input.value),/[^a-zA-Z0-9]/.test(input.value)].filter(Boolean).length;host.querySelectorAll('.gj-strength>span').forEach((s,i)=>s.classList.toggle('filled',i<score));host.querySelector('[data-strength-label]').textContent=strongPassword(input.value)?'Strong password':'Use 8+ characters, uppercase, lowercase and a number.';}
 });
 host.addEventListener('keydown',event=>{const i=event.target;if(i.matches('[data-digit]')&&event.key==='Backspace'&&!i.value)host.querySelector('[data-digit="'+(Number(i.dataset.digit)-1)+'"]')?.focus();if(i.matches('.gj-add-photo')&&['Enter',' '].includes(event.key)){event.preventDefault();i.querySelector('input').click();}});
 host.addEventListener('paste',event=>{if(!event.target.matches('[data-digit]'))return;event.preventDefault();const digits=event.clipboardData.getData('text').replace(/\D/g,'').slice(0,6),start=digits.length===6?0:Number(event.target.dataset.digit);[...digits].forEach((d,i)=>{const t=host.querySelector('[data-digit="'+(start+i)+'"]');if(t)t.value=d;});host.querySelector('[data-digit="'+Math.min(5,start+digits.length)+'"]')?.focus();});
}

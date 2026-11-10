import {GuestFormState} from './guest-form-state.js';
const requestDrafts=new Map();
import {ageLabel} from './birth-age.js';

export async function openCustomerRequestForm({procedure='', procedures, openModal, content, modal}) {
    const scope=document.body.dataset.requestDraftOwner||'current';
    let entry=requestDrafts.get(scope);
    if(entry?.state.expire()){entry.release?.();requestDrafts.delete(scope);entry=null;}
    if(entry?.root){openModal('');content.replaceChildren(entry.root);entry.resume();return;}
    if(!entry){let storage;try{storage=globalThis.sessionStorage;}catch{}
        entry={state:new GuestFormState({storage,key:'aesthetic-customer-request-'+scope})};requestDrafts.set(scope,entry);
    }
    const draftState=entry.state;
    const escape = value => String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let profile={},profileLoaded=false;
    openModal('<p role="status">Loading your profile...</p>');
    const loading=content.firstElementChild;
    try {
        const response=await fetch(document.body.dataset.requestProfileUrl,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
        if(!response.ok)throw new Error('Profile unavailable');
        profile=(await response.json()).profile;profileLoaded=true;
    } catch {}
    if(!modal.open || !loading.isConnected)return;
    if(!profileLoaded){openModal('<p role="alert">Your profile could not be loaded. Close this dialog and try again. Your current draft has been kept.</p>');return;}
    const today = new Date(), year = today.getFullYear();
    const months = Array.from({length:12},(_,i)=>new Intl.DateTimeFormat('en',{month:'long'}).format(new Date(2026,i,1)));
    openModal(`<div data-no-translate lang="en"><p class="modal-eyebrow">YOUR NEXT CHAPTER</p><div class="quote-profile-heading"><h2>New Beauty Request</h2>${profileLoaded?'<span class="quote-profile-badge">✓ Saved from your profile</span>':''}</div><p>Tell us about your current goals.</p><p class="quote-field-help">Fields marked * are required.</p>
    <form class="quote-form" id="quote-form">
    <section class="quote-personal"><div class="quote-personal-heading"><h3>Personal Information <small>${profileLoaded?'(Auto-filled)':''}</small></h3><button type="button" class="quote-profile-edit" aria-expanded="${!profileLoaded}" aria-controls="quote-personal-fields">${profileLoaded?'Edit Profile':'Enter details'}</button></div>
    <p>Your saved profile information will be used automatically.</p><dl class="quote-profile-summary" ${profileLoaded?'':'hidden'}>${[['Name','name'],['Email','email'],['LINE ID','line_id'],['Phone','phone_number'],['Date of Birth','date_of_birth'],['Gender','gender']].map(([label,key])=>`<div><dt>${label}</dt><dd data-profile-value="${key}">${escape(profile[key]||'Not provided')}</dd></div>`).join('')}</dl>
    <div id="quote-personal-fields" ${profileLoaded?'hidden':''}>
    <label>Full Name *<input name="name" autocomplete="name" placeholder="Enter your full name" required maxlength="80"></label>
    <label>Email Address *<input readonly name="email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="254"></label>
    <div class="quote-contact-grid"><label>LINE ID<input name="line_id" maxlength="100" autocomplete="off"></label><label>Phone Number<input name="phone_number" type="tel" autocomplete="tel" placeholder="+66 81 234 5678" maxlength="30" aria-describedby="quote-phone-help"></label></div>
    <small class="quote-field-help" id="quote-phone-help">Include the international country code, for example +66 or +82.</small>
    <div class="quote-contact-grid"><label>Date of Birth<input name="date_of_birth" type="date" max="${new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Bangkok'}).format(today)}" autocomplete="bday" aria-describedby="quote-calculated-age"><small id="quote-calculated-age" class="quote-field-help" role="status">${escape(ageLabel(profile.date_of_birth))}</small></label><label>Gender<select name="gender"><option value="">Select gender</option>${['Female','Male'].map(v=>`<option>${v}</option>`).join('')}</select></label></div>
    <small class="quote-field-help">Email changes require a separate verification flow.</small></div></section>
    <fieldset class="quote-procedures"><legend>Procedures of Interest *</legend><div>${procedures.map(p=>`<label><input type="checkbox" name="procedures[]" value="${escape(p)}" ${p===procedure?'checked':''}><span>${escape(p)}</span></label>`).join('')}</div><p id="quote-selected" class="quote-field-help" aria-live="polite"></p></fieldset>
    ${profileLoaded?'<p class="quote-profile-note">Your personal information is automatically filled from your profile.<br>You only need to enter treatment details for this request.</p>':'<p class="quote-field-help">Your profile could not be loaded. Please enter your personal details.</p>'}
    <label>Your Beauty Goals<textarea name="goals" placeholder="Tell us what you have in mind..." maxlength="1000"></textarea></label>
    <label for="quote-photos">Upload Photos<input id="quote-photos" type="file" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="quote-photo-help quote-photo-status"></label>
    <small id="quote-photo-help" class="quote-field-help">Up to 10 JPG, PNG, or WebP images, 5 MB per image. Photos are private and only accessible to authorized users.</small><p id="quote-photo-status" class="quote-file-status" role="status"></p><div id="quote-photo-previews" class="quote-photo-previews"></div>
    <div class="quote-contact-grid"><label>Preferred Surgery Month<select name="preferred_surgery_month"><option value="">Select month</option>${months.map((m,i)=>`<option value="${i+1}">${m}</option>`).join('')}</select></label><label>Preferred Surgery Year<select name="preferred_surgery_year"><option value="">Select year</option>${Array.from({length:11},(_,i)=>`<option>${year+i}</option>`).join('')}</select></label></div>
    <label>Medical Information<textarea name="medical_information" placeholder="Medical conditions, allergies, current medications, or previous surgeries." maxlength="3000"></textarea></label>
    <label class="consent"><input type="checkbox" name="consent_to_share" value="1" required> I consent to sharing my personal information, photos, and medical information with partner hospitals to receive treatment consultations and quotations. *</label>
    <p id="quote-submit-error" class="auth-form-error" role="alert" hidden></p><button class="button button-gold" type="submit">Submit My Request</button></form></div>`);
    const form = content.querySelector('form'), photos=form.querySelector('#quote-photos'), status=form.querySelector('#quote-photo-status');
    const personalFields=form.querySelector('#quote-personal-fields'),summary=form.querySelector('.quote-profile-summary'),edit=form.querySelector('.quote-profile-edit');
    for(const key of ['name','email','line_id','phone_number','date_of_birth','gender'])form.elements[key].value=profile[key]||'';
    function updateAge(){
        const birthDate=form.elements.date_of_birth.value;
        form.querySelector('#quote-calculated-age').textContent=ageLabel(birthDate);
        summary.querySelector('[data-profile-value="date_of_birth"]').textContent=birthDate?birthDate+' ('+ageLabel(birthDate)+')':'Not provided';
    }
    form.elements.date_of_birth.addEventListener('input',updateAge);
    updateAge();
    function expandPersonal(){personalFields.querySelectorAll('label').forEach(label=>label.hidden=false);personalFields.hidden=false;summary.hidden=true;edit.textContent='Save Profile';edit.setAttribute('aria-expanded','true');}
    edit.onclick=async()=>{
        if(personalFields.hidden){expandPersonal();form.elements.name.focus();return;}
        const fields=[...personalFields.querySelectorAll('input,select')];
        if(fields.some(field=>!field.checkValidity())){fields.find(field=>!field.checkValidity()).reportValidity();return;}
        try {
            edit.disabled=true;
            const account=JSON.parse(document.querySelector('#account-profile-data')?.textContent||'{}');
            const response=await fetch(document.body.dataset.requestProfileUpdateUrl,{method:'PATCH',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({name:form.elements.name.value,phone:form.elements.phone_number.value,line_id:form.elements.line_id.value,date_of_birth:form.elements.date_of_birth.value,gender:form.elements.gender.value,language:account.language||'en',country:account.country||null})});
            const result=await response.json();
            if(!response.ok)throw Error(Object.values(result.errors||{}).flat().join(' ')||'Profile could not be saved.');
        } catch(error){const message=form.querySelector('#quote-submit-error');message.textContent=error.message;message.hidden=false;return;}
        finally{edit.disabled=false;}
        summary.querySelectorAll('[data-profile-value]').forEach(node=>{node.textContent=form.elements[node.dataset.profileValue].value||'Not provided';});
        updateAge();
        personalFields.hidden=true;summary.hidden=false;edit.textContent='Edit Profile';edit.setAttribute('aria-expanded','false');
    };
    form.addEventListener('invalid',event=>{if(personalFields.contains(event.target))expandPersonal();},true);
    const missing=[...personalFields.querySelectorAll('input,select')].filter(field=>field.required&&!field.value);
    if(missing.length){personalFields.hidden=false;summary.hidden=false;edit.textContent='Save Profile';personalFields.querySelectorAll('label').forEach(label=>label.hidden=!missing.some(field=>label.contains(field)));}
    draftState.apply({elements:[...form.elements].filter(field=>!['name','email','phone_number','line_id','date_of_birth','gender'].includes(field.name))});
    let files=draftState.files, urls=[];
    const capture=()=>{if(!entry.root||requestDrafts.get(scope)!==entry)return;draftState.capture(form);draftState.files=files;};
    form.addEventListener('input',capture);form.addEventListener('change',capture);
    const onClose=()=>{capture();draftState.flush();release();};
    const onPageHide=()=>{capture();draftState.flush();};
    modal.addEventListener('close',onClose);window.addEventListener('pagehide',onPageHide);
    entry.cleanup=()=>{modal.removeEventListener('close',onClose);window.removeEventListener('pagehide',onPageHide);};
    function release(){urls.forEach(URL.revokeObjectURL);urls=[];}

    function previews(){
        release();const list=form.querySelector('#quote-photo-previews');list.replaceChildren();
        files.forEach((file,i)=>{const figure=document.createElement('figure'),img=document.createElement('img'),button=document.createElement('button'),caption=document.createElement('figcaption');
            const url=URL.createObjectURL(file);urls.push(url);img.src=url;img.alt='Selected photo '+(i+1);caption.textContent=file.name;button.type='button';button.textContent='Remove';button.setAttribute('aria-label','Remove '+file.name);button.onclick=()=>{files.splice(i,1);previews();};figure.append(img,caption,button);list.append(figure);});
        status.textContent=files.length?`${files.length} of 10 photos selected.`:'No photos selected.';
    }
    photos.addEventListener('change',()=>{
        const incoming=[...photos.files];photos.value='';
        let error=files.length+incoming.length>10?'Please select no more than 10 photos.':incoming.some(f=>!['image/jpeg','image/png','image/webp'].includes(f.type))?'Please select JPG, PNG, or WebP photos only.':incoming.some(f=>!f.size||f.size>5*1024*1024)?'Each photo must be between 1 byte and 5 MB.':'';
        if(error){status.textContent=error;return;} files.push(...incoming);previews();
    });
    const checks=[...form.querySelectorAll('[name="procedures[]"]')];
    function selected(){const choices=checks.filter(c=>c.checked).map(c=>c.value);checks[0].setCustomValidity(choices.length?'':'Select at least one procedure.');form.querySelector('#quote-selected').textContent=choices.length?'Selected: '+choices.join(', '):'Select at least one procedure.';}
    checks.forEach(c=>c.addEventListener('change',selected));selected();
    entry.root=content.firstElementChild;entry.resume=()=>{previews();selected();};entry.release=release;
    previews();
    const startNew=document.createElement('button');startNew.type='button';startNew.className='button button-outline';startNew.textContent='Start New Request';
    startNew.onclick=()=>{entry.cleanup();release();draftState.clear();requestDrafts.delete(scope);openCustomerRequestForm({procedures,openModal,content,modal});};form.prepend(startNew);
    form.addEventListener('submit',async event=>{
        event.preventDefault();const error=form.querySelector('#quote-submit-error'),button=form.querySelector('[type="submit"]');error.hidden=true;
        const month=form.elements.preferred_surgery_month.value, chosenYear=form.elements.preferred_surgery_year.value;
        if(Boolean(month)!==Boolean(chosenYear) || (month && `${chosenYear}-${month.padStart(2,'0')}`<`${year}-${String(today.getMonth()+1).padStart(2,'0')}`)){error.textContent='Select both month and year, using the current month or a future month.';error.hidden=false;return;}
        const payload=new FormData(form);files.forEach(f=>payload.append('photos[]',f));button.disabled=true;button.textContent='Submitting...';
        try{const response=await fetch(document.body.dataset.requestStore,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept-Language':'en'},body:payload});let result={};try{result=await response.json();}catch{}
            if(!response.ok)throw new Error(response.status===413?'The selected photos exceed the server upload limit.':Object.values(result.errors||{}).flat().join(' ')||result.message||'Your request could not be saved. Please try again.');
            entry.cleanup();release();draftState.clear();entry.root=null;requestDrafts.delete(scope);openModal('<div data-no-translate lang="en"><div class="success-icon">OK</div><p class="modal-eyebrow">REQUEST SAVED</p><h2>A beautiful first step.</h2><p>Your request has been saved and is available to authorized partner hospitals.</p></div>');const link=document.createElement('a');link.className='button button-gold';link.href=result.redirect;link.textContent='View My Requests';content.firstElementChild.append(link);
        }catch(failure){error.textContent=failure.message;error.hidden=false;}finally{button.disabled=false;button.textContent='Submit My Request';}
    });
}

import {createReviewPhotoQueue} from './review-photo-queue.js';
const form=document.querySelector('[data-review-filters]');
if(form){
 form.classList.add('pr-enhanced');
 const submit=()=>{for(const field of form.elements)if(field.name&&field.value==='')field.disabled=true;form.requestSubmit();};
 form.querySelectorAll('select,input[type=checkbox]').forEach(field=>field.addEventListener('change',submit));
 // Search submits on Enter; retaining explicit submission avoids losing focus while typing.
 form.addEventListener('submit',()=>{for(const field of form.elements)if(field.name&&field.value==='')field.disabled=true;});
}

const dialog=document.querySelector('[data-review-dialog]');
if(dialog){
 const reviewForm=dialog.querySelector('[data-review-form]'),feedback=dialog.querySelector('[data-review-feedback]');
 let opener,previousOverflow='',sending=false;
 const show=event=>{event?.preventDefault();if(dialog.open)return;opener=event?.currentTarget;previousOverflow=document.body.style.overflow;dialog.showModal();document.body.style.overflow='hidden';};
 document.querySelectorAll('a.pr-write[href], [data-open-review]').forEach(link=>{if(link.matches('[data-open-review]')||new URL(link.href,location.href).pathname.endsWith('/patient-reviews/write'))link.addEventListener('click',show);});
 dialog.querySelectorAll('[data-review-close]').forEach(button=>button.addEventListener('click',()=>{if(!sending)dialog.close();}));
 dialog.addEventListener('cancel',event=>{if(sending)event.preventDefault();});
 dialog.addEventListener('close',()=>{document.body.style.overflow=previousOverflow;opener?.focus();});
 dialog.addEventListener('click',event=>{const rect=dialog.getBoundingClientRect();if(!sending&&event.target===dialog&&(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom))dialog.close();});
 const stars=[...dialog.querySelectorAll('input[name=rating]')];
 const updateRating=()=>{const value=Number(stars.find(input=>input.checked)?.value||5);stars.forEach(input=>input.closest('label').classList.toggle('is-empty',Number(input.value)>value));dialog.querySelector('[data-review-rating]').value=value.toFixed(1)+' / 5';};
 stars.forEach(input=>input.addEventListener('change',updateRating));updateRating();
 const photoInput=dialog.querySelector('[data-review-photos]'),gallery=dialog.querySelector('[data-review-photo-gallery]');
 const queue=createReviewPhotoQueue();let previewUrls=[];
 const renderPhotos=()=>{
  previewUrls.forEach(url=>URL.revokeObjectURL(url));previewUrls=[];gallery.replaceChildren();
  for(const entry of queue.entries){
   const figure=document.createElement('figure'),image=document.createElement('img'),caption=document.createElement('figcaption'),actions=document.createElement('div');
   image.src=URL.createObjectURL(entry.file);previewUrls.push(image.src);image.alt='Selected review photo';caption.textContent=entry.file.name;
   actions.className='pr-upload-actions';const replace=document.createElement('button'),remove=document.createElement('button'),replacement=document.createElement('input');
   replace.type=remove.type='button';replace.textContent='Replace';remove.textContent='Remove';replacement.type='file';replacement.accept='image/jpeg,image/png';replacement.hidden=true;
   replace.addEventListener('click',()=>replacement.click());remove.addEventListener('click',()=>{queue.remove(entry.key);renderPhotos();});
   replacement.addEventListener('change',()=>{try{if(replacement.files[0]){queue.replace(entry.key,replacement.files[0]);renderPhotos();}}catch(error){feedback.textContent=error.message;feedback.focus();}});
   actions.append(replace,remove);figure.append(image,caption,actions,replacement);gallery.append(figure);
  }
 };
 photoInput.addEventListener('change',()=>{try{queue.add([...photoInput.files]);feedback.textContent='';renderPhotos();}catch(error){feedback.textContent=error.message;feedback.focus();}photoInput.value='';});
 reviewForm.addEventListener('submit',async event=>{
  event.preventDefault();if(sending||!reviewForm.reportValidity())return;
  const payload=new FormData(reviewForm),submit=reviewForm.querySelector('[type=submit]');sending=true;submit.disabled=true;photoInput.disabled=true;gallery.querySelectorAll('button,input').forEach(field=>field.disabled=true);feedback.classList.remove('is-success');feedback.textContent='Submitting your review…';
  try{
   // Upload one file per request, avoiding PHP's per-request file-count limit.
   for(const entry of queue.entries){
    if(entry.uploadedId)continue;
    const filePayload=new FormData();filePayload.append('photo',entry.file);
    const uploaded=await fetch(photoInput.dataset.uploadUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:filePayload});
    const result=await uploaded.json();if(!uploaded.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'A photo could not be uploaded.');
    entry.uploadedId=result.id;
   }
   queue.entries.forEach(entry=>payload.append('photo_ids[]',entry.uploadedId));
   if(queue.entries.length)payload.append('photo_consent','1');
   const response=await fetch(reviewForm.action,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:payload});
   const result=await response.json();
   if(!response.ok){if(response.status===419)throw new Error('Your session expired. Reload this page before submitting again.');throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Your review could not be saved.');}
   feedback.classList.add('is-success');feedback.textContent=result.message;feedback.focus();reviewForm.querySelectorAll('input,textarea,select').forEach(field=>field.disabled=true);submit.textContent='Review posted';
   if(result.url)location.assign(result.url);
  }catch(error){feedback.textContent=error.message;feedback.focus();submit.disabled=false;photoInput.disabled=false;gallery.querySelectorAll('button,input').forEach(field=>field.disabled=false);}finally{sending=false;}
 });
 if(dialog.hasAttribute('data-auto-open'))show();
}

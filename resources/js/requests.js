import {pageNumbers} from './pagination.js';
import {openDepositPayment} from './deposit-payment.js';
import {quotationStats, selectRequests, matchesRequestStatus} from './requests-data';
const root = document.querySelector('[data-requests-page]');
const $ = (selector, parent = document) => parent.querySelector(selector);
const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];
const images = root.dataset.assets;
const savedRequests = JSON.parse(document.getElementById('saved-requests').textContent);
const labels = {account_activated:'Account Activated', pending_booking_deposit:'Pending Booking Deposit', pending:'Pending', quotes_received:'Quotes Received', cancelled:'Cancelled', pending_payment:'Pending Payment', confirmed:'Confirmed', completed:'Completed', quote_received:'Quote Received', declined:'Declined'};
const symbols = {account_activated:'?', pending_booking_deposit:'!', pending:'\u25f7', quotes_received:'\u2713', cancelled:'\u00d7', pending_payment:'\u25f7', confirmed:'\u2713', completed:'\u2713', quote_received:'', declined:'\u00d7'};
const serverPage=JSON.parse(document.getElementById('request-pagination').textContent);
let activeFilter = serverPage.filter;
let currentPage = serverPage.page;
const pageSize = 5;
function date(value, monthOnly = false) { if(!value)return 'Not specified';const period=value.length===7;return new Date(value+(period?'-01':'')+'T12:00:00').toLocaleDateString(document.documentElement.lang,monthOnly||period?{month:'long',year:'numeric'}:{month:'short',day:'numeric',year:'numeric'}); }
function text(selector, value, parent) { $(selector,parent).textContent = value; }
function badge(node, status) { node.dataset.status=status; text('.request-badge-symbol',symbols[status],node); text('.request-badge-label',labels[status],node); }
function escape(value) { const span=document.createElement('span');span.textContent=String(value);return span.innerHTML; }
function showModal(title, html) {
 const modal=$('#demo-modal');modal.classList.remove('booking-payment-dialog');delete $('#modal-content').dataset.noTranslate;$('#modal-content').lang=document.documentElement.lang;$('#modal-content').innerHTML='<p class="modal-eyebrow">YOUR PRIVATE REQUEST</p><h2 id="modal-title">'+escape(title)+'</h2>'+html;
 modal.classList.add('request-dialog'); if(!modal.open) modal.showModal();document.body.style.overflow='hidden';
 modal.addEventListener('close',()=>modal.classList.remove('request-dialog'),{once:true});
}
const quotationPrice=value=>'THB '+Number(value).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
function hospitalDetails(response) {
 const profile=response.hospital_profile||{};
 let html='<section class="request-hospital-profile"><h3 data-user-content>'+escape(response.hospital_name||'Selected Hospital')+'</h3>';
 if(profile.short_description)html+='<p data-user-content>'+escape(profile.short_description)+'</p>';
 const rows=[['Hospital Address',[profile.address,profile.city,profile.country].filter(Boolean).join(', ')],['Phone Number',profile.phone],['Email Address',profile.email],['Website',profile.website],['Operating Hours',profile.operating_hours]];
 html+='<dl>';for(const [label,value] of rows)if(value)html+='<dt>'+label+'</dt><dd data-user-content>'+escape(value)+'</dd>';
 return html+'</dl></section>';
}
function quoteDetails(request, response) {
 showModal('Quotation Details',hospitalDetails(response)+'<p>'+escape(request.procedure_name)+' &middot; '+escape(request.id)+'</p><span class="request-badge" data-status="quote_received">Quote Received</span><div class="request-amount">'+quotationPrice(response.quotation_amount)+'</div><p class="request-sensitive-text">'+escape(response.details||'No quotation details provided.')+'</p>'+(response.valid_until?'<p>Valid Until: '+date(response.valid_until)+'</p>':''));
 if(response.accept_url||response.payment_url){const button=document.createElement('button');button.type='button';button.className='button button-gold';button.textContent=response.payment_url?'View deposit payment':'Accept quotation and confirm booking';button.onclick=()=>openDepositPayment({request,response,showModal,back:()=>quoteDetails(request,response),onAccepted:()=>{request.status='pending';updateFilterCounts();render();}});document.querySelector('#modal-content').append(button);}

}
function requestDetails(request) {
 const stats=quotationStats(request);
 showModal(request.procedure_name,'<p>'+escape(request.id)+'</p><div class="request-detail-line"><span>Request status</span><strong>'+labels[request.status]+'</strong></div><div class="request-detail-line"><span>Submitted</span><strong>'+date(request.submitted_at)+'</strong></div><div class="request-detail-line"><span>Preferred date</span><strong>'+date(request.preferred_date)+'</strong></div><div class="request-detail-line"><span>Contact name</span><strong data-no-translate>'+escape(request.name)+'</strong></div><div class="request-detail-line"><span>Email</span><strong data-no-translate>'+escape(request.email)+'</strong></div><h3 class="request-dialog-subtitle">Your beauty goals</h3><p class="request-sensitive-text">'+escape(request.goals || 'Not provided')+'</p><h3 class="request-dialog-subtitle">Medical Information</h3><p class="request-sensitive-text">'+escape(request.medical_information || 'Not provided')+'</p><h3 class="request-dialog-subtitle">Uploaded Photos</h3><div class="request-private-photos" id="request-private-photos"></div><p class="modal-note">Your request is shared with eligible partner hospitals under your consent. Quotations are shown on your request card.</p>');
 const details=document.createElement('dl');details.className='request-contact-details';
 [['Date of Birth',request.date_of_birth],['Additional Notes',request.additional_notes],['LINE ID',request.line_id],['Phone Number',request.phone_number],['Age',request.age],['Gender',request.gender]].forEach(([label,value])=>{const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;dd.textContent=value??'Not provided';if(value!=null)dd.dataset.userContent='';details.append(dt,dd);});$('#modal-content').insertBefore(details,$('#request-private-photos'));
 const container=$('#request-private-photos');
 if(!request.photos.length)container.textContent='No photos uploaded.';
 request.photos.forEach(photo=>{const link=document.createElement('a');link.href=photo.url;link.target='_blank';link.rel='noopener';const image=document.createElement('img');image.src=photo.url;image.alt=photo.name;image.loading='lazy';link.append(image);container.append(link);});
}

function render() {
 const list=$('#requests-list');list.replaceChildren();
 const requests=savedRequests;
 const totalPages=serverPage.pages;
 currentPage=Math.min(Math.max(1,currentPage),totalPages);
 const start=(currentPage-1)*pageSize;
 requests.forEach(request=>{
  const card=$('#request-card-template').content.firstElementChild.cloneNode(true);
  card.dataset.requestId=request.id;
  const image=$('[data-procedure-image]',card);const uploadedPhoto=request.photos?.[0];image.src=uploadedPhoto ? uploadedPhoto.url : images+'/'+request.procedure_image;image.alt=uploadedPhoto ? 'Your uploaded photo for '+request.procedure_name : request.procedure_name+' inspiration';
  text('[data-request-id]',request.id,card);text('[data-procedure-name]',request.procedure_name,card);badge($('[data-request-status]',card),request.status);
  text('[data-submitted]','Submitted on '+date(request.submitted_at),card);text('[data-contacted]',request.hospitals_contacted+' hospital(s) have been contacted',card);text('[data-preferred]','Preferred Date: '+date(request.preferred_date,true),card);
  const stats=quotationStats(request);text('[data-quotation-count]',stats.received+' / '+stats.total,card);
  const progress=$('.request-progress',card);progress.setAttribute('aria-valuenow',String(Math.round(stats.percent*10)/10));progress.setAttribute('aria-valuetext',stats.received+' of '+stats.total+' hospital quotations received');$('span',progress).style.width=stats.percent+'%';
  if(request.payment_is_simulated){const note=document.createElement('small');note.textContent='Simulated test payment ? no real funds received.';$('.request-summary',card).append(note);}
  const deposit=request.treatment_deposit;
  if(deposit){
   const section=$('[data-treatment-deposit]',card);section.hidden=false;section.id=deposit.section_id;
   text('[data-treatment-total]',quotationPrice(deposit.total_satang/100),card);
   text('[data-treatment-percentage]',deposit.percentage,card);
   text('[data-treatment-required]',quotationPrice(deposit.required_satang/100),card);
   text('[data-treatment-initial]','- '+quotationPrice(deposit.initial_satang/100),card);
   text('[data-treatment-additional]',quotationPrice(deposit.outstanding_satang/100),card);
   text('[data-treatment-booking-paid]',quotationPrice(deposit.booking_paid_satang/100),card);
   text('[data-treatment-total-paid]',quotationPrice(deposit.total_paid_satang/100),card);
   text('[data-treatment-remaining]',quotationPrice(deposit.remaining_satang/100),card);
   const pay=$('[data-pay-treatment]',card);
   const paid=deposit.phase==='treatment'&&deposit.payment_status==='Paid';
   const reviewing=deposit.phase==='treatment'&&deposit.payment_status==='Pending Verification';
   pay.disabled=deposit.additional_satang===0||paid||reviewing||request.status==='cancelled'||request.status==='completed';
   if(deposit.additional_satang===0)pay.textContent='No Additional Deposit Required';
   if(paid)pay.textContent='Booking Deposit Verified';
   if(reviewing)pay.textContent='Pending Verification';
   pay.onclick=async()=>{
    pay.disabled=true;
    showModal('Pay Booking Deposit','<p role="status">Loading payment details...</p>');
    try{
     const response=await fetch(deposit.start_url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name="csrf-token"]').content},body:JSON.stringify({confirm_treatment:true})});
     const data=await response.json();if(!response.ok)throw new Error(data.message||'Payment could not be started.');
     request.status='pending_booking_deposit';deposit.phase='treatment';updateFilterCounts();render();
     if($('#demo-modal').open)openDepositPayment({request,response:{payment_url:deposit.payment_url},showModal,back:()=>requestDetails(request)});
    }catch(error){const message=document.createElement('p');message.setAttribute('role','alert');message.textContent=error.message;$('#modal-content').append(message);pay.disabled=false;}
   };
  }
  const responses=request.selected_quotation_id?request.hospital_responses.filter(r=>r.quotation_id===request.selected_quotation_id):request.hospital_responses;
  if(request.selected_quotation_id)$('.request-response-content h3',card).firstChild.textContent='Selected Hospital (';
  $('.request-no-responses',card).hidden=responses.length>0;$('.request-response-content',card).hidden=responses.length===0;
  text('[data-response-count]',responses.length,card);
  responses.forEach(response=>{
   const hospital=$('#hospital-response-template').content.firstElementChild.cloneNode(true);
   text('[data-hospital-name]',response.hospital_name||'Selected Hospital',hospital);
   const profile=response.hospital_profile||{};
   for(const [selector,value] of [['[data-hospital-description]',profile.short_description],['[data-hospital-address]',[profile.address,profile.city,profile.country].filter(Boolean).join(', ')]]){const node=$(selector,hospital);node.textContent=value||'';node.hidden=!value;}
   text('[data-quote-price]',quotationPrice(response.quotation_amount),hospital);
   text('[data-quote-details]',response.details||'No quotation details provided.',hospital);
   const validity=$('[data-quote-validity]',hospital);validity.hidden=!response.valid_until;if(response.valid_until)validity.textContent='Valid Until: '+date(response.valid_until);
   badge($('[data-response-status]',hospital),'quote_received');
   const button=$('[data-view-quote]',hospital);button.setAttribute('aria-label','View quotation details');button.onclick=()=>quoteDetails(request,response);
   $('.request-hospital-grid',card).append(hospital);
  });
  const detailButton=$('[data-view-request]',card);detailButton.setAttribute('aria-label','View details for '+request.id);detailButton.onclick=()=>requestDetails(request);
  list.append(card);
 });
 $('#request-list-empty').hidden=requests.length>0;
 $('#requests-results').textContent=serverPage.total ? 'Showing '+(start+1)+'\u2013'+Math.min(start+pageSize,serverPage.total)+' of '+serverPage.total+' '+(requests.length===1?'request':'requests') : 'No requests to show';
 const pagination=$('#requests-pagination');pagination.replaceChildren();pagination.hidden=serverPage.total<=pageSize;
 pagination.parentElement.classList.toggle('has-pages',!pagination.hidden);
 if(!pagination.hidden) {
  const goTo=page=>{const url=new URL(location.href);url.searchParams.set('page',page);location.assign(url);};
  const addButton=(label,page,disabled=false,active=false)=>{const button=document.createElement('button');button.type='button';button.textContent=label;button.disabled=disabled;button.className=active?'is-current':'';button.setAttribute('aria-label',typeof label==='number'?'Go to page '+label:label+' page');if(active)button.setAttribute('aria-current','page');button.onclick=()=>goTo(page);pagination.append(button);};

  pageNumbers(currentPage,totalPages).forEach(page=>addButton(page,page,false,page===currentPage));
 }
}
function updateFilterCounts() { $$('[data-filter-count]').forEach(node=>{node.textContent=serverPage.counts[node.dataset.filterCount]||0;}); }
document.addEventListener('booking-payment-submitted',()=>{updateFilterCounts();render();});
updateFilterCounts();
$$('[data-request-filter]').forEach(button=>{button.setAttribute('aria-pressed',String(button.dataset.requestFilter===activeFilter));button.onclick=()=>{const url=new URL(location.href);url.searchParams.set('filter',button.dataset.requestFilter);url.searchParams.delete('page');location.assign(url);};});
$('#request-sort').value=serverPage.sort;
$('#request-sort').addEventListener('change',()=>{const url=new URL(location.href);url.searchParams.set('sort',$('#request-sort').value);url.searchParams.delete('page');location.assign(url);});
render();

if(location.hash)document.getElementById(location.hash.slice(1))?.scrollIntoView();

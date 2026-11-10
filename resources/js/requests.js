import {openDepositPayment} from './deposit-payment.js';
import {quotationStats, selectRequests} from './requests-data';
const root = document.querySelector('[data-requests-page]');
const $ = (selector, parent = document) => parent.querySelector(selector);
const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];
const images = root.dataset.assets;
const savedRequests = JSON.parse(document.getElementById('saved-requests').textContent);
const labels = {pending:'Pending', quotes_received:'Quotes Received', cancelled:'Cancelled', pending_payment:'Pending Payment', confirmed:'Confirmed', completed:'Completed', quote_received:'Quote Received', declined:'Declined'};
const symbols = {pending:'\u25f7', quotes_received:'\u2713', cancelled:'\u00d7', pending_payment:'\u25f7', confirmed:'\u2713', completed:'\u2713', quote_received:'', declined:'\u00d7'};
let activeFilter = 'all';
let currentPage = 1;
const pageSize = 5;
function date(value, monthOnly = false) { if(!value)return 'Not specified';const period=value.length===7;return new Date(value+(period?'-01':'')+'T12:00:00').toLocaleDateString('en-US',monthOnly||period?{month:'long',year:'numeric'}:{month:'short',day:'numeric',year:'numeric'}); }
function text(selector, value, parent) { $(selector,parent).textContent = value; }
function badge(node, status) { node.dataset.status=status; text('.request-badge-symbol',symbols[status],node); text('.request-badge-label',labels[status],node); }
function escape(value) { const span=document.createElement('span');span.textContent=String(value);return span.innerHTML; }
function showModal(title, html) {
 const modal=$('#demo-modal');$('#modal-content').dataset.noTranslate='';$('#modal-content').lang='en';$('#modal-content').innerHTML='<p class="modal-eyebrow">YOUR PRIVATE REQUEST</p><h2 id="modal-title">'+escape(title)+'</h2>'+html;
 modal.classList.add('request-dialog'); if(!modal.open) modal.showModal();document.body.style.overflow='hidden';
 modal.addEventListener('close',()=>modal.classList.remove('request-dialog'),{once:true});
}
const quotationPrice=value=>'THB '+Number(value).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
function quoteDetails(request, response) {
 showModal('Quotation Details','<p>'+escape(request.procedure_name)+' &middot; '+escape(request.id)+'</p><span class="request-badge" data-status="quote_received">Quote Received</span><div class="request-amount">'+quotationPrice(response.quotation_amount)+'</div><p class="request-sensitive-text">'+escape(response.details||'No quotation details provided.')+'</p>'+(response.valid_until?'<p>Valid Until: '+date(response.valid_until)+'</p>':''));
 if(response.accept_url||response.payment_url){const button=document.createElement('button');button.type='button';button.className='button button-gold';button.textContent=response.payment_url?'View deposit payment':'Accept quotation and confirm booking';button.onclick=()=>openDepositPayment({request,response,showModal,back:()=>quoteDetails(request,response),onAccepted:()=>{request.status='pending_payment';updateFilterCounts();render();}});document.querySelector('#modal-content').append(button);}

}
function requestDetails(request) {
 const stats=quotationStats(request);
 showModal(request.procedure_name,'<p>'+escape(request.id)+'</p><div class="request-detail-line"><span>Request status</span><strong>'+labels[request.status]+'</strong></div><div class="request-detail-line"><span>Submitted</span><strong>'+date(request.submitted_at)+'</strong></div><div class="request-detail-line"><span>Preferred date</span><strong>'+date(request.preferred_date)+'</strong></div><div class="request-detail-line"><span>Contact name</span><strong data-no-translate>'+escape(request.name)+'</strong></div><div class="request-detail-line"><span>Email</span><strong data-no-translate>'+escape(request.email)+'</strong></div><h3 class="request-dialog-subtitle">Your beauty goals</h3><p class="request-sensitive-text">'+escape(request.goals || 'Not provided')+'</p><h3 class="request-dialog-subtitle">Medical Information</h3><p class="request-sensitive-text">'+escape(request.medical_information || 'Not provided')+'</p><h3 class="request-dialog-subtitle">Uploaded Photos</h3><div class="request-private-photos" id="request-private-photos"></div><p class="modal-note">Your request is shared with eligible partner hospitals under your consent. Quotations are shown on your request card.</p>');
 const details=document.createElement('dl');details.className='request-contact-details';details.dataset.noTranslate='';
 [['Date of Birth',request.date_of_birth],['Additional Notes',request.additional_notes],['LINE ID',request.line_id],['Phone Number',request.phone_number],['Age',request.age],['Gender',request.gender]].forEach(([label,value])=>{const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;dd.textContent=value??'Not provided';details.append(dt,dd);});$('#modal-content').insertBefore(details,$('#request-private-photos'));
 const container=$('#request-private-photos');
 if(!request.photos.length)container.textContent='No photos uploaded.';
 request.photos.forEach(photo=>{const link=document.createElement('a');link.href=photo.url;link.target='_blank';link.rel='noopener';const image=document.createElement('img');image.src=photo.url;image.alt=photo.name;image.loading='lazy';link.append(image);container.append(link);});
}

function render() {
 const list=$('#requests-list');list.replaceChildren();
 const requests=selectRequests(savedRequests,activeFilter,$('#request-sort').value);
 const totalPages=Math.max(1,Math.ceil(requests.length/pageSize));
 currentPage=Math.min(Math.max(1,currentPage),totalPages);
 const start=(currentPage-1)*pageSize;
 requests.slice(start,start+pageSize).forEach(request=>{
  const card=$('#request-card-template').content.firstElementChild.cloneNode(true);
  card.dataset.requestId=request.id;
  const image=$('[data-procedure-image]',card);const uploadedPhoto=request.photos?.[0];image.src=uploadedPhoto ? uploadedPhoto.url : images+'/'+request.procedure_image;image.alt=uploadedPhoto ? 'Your uploaded photo for '+request.procedure_name : request.procedure_name+' inspiration';
  text('[data-request-id]',request.id,card);text('[data-procedure-name]',request.procedure_name,card);badge($('[data-request-status]',card),request.status);
  text('[data-submitted]','Submitted on '+date(request.submitted_at),card);text('[data-contacted]',request.hospitals_contacted+' hospital(s) have been contacted',card);text('[data-preferred]','Preferred Date: '+date(request.preferred_date,true),card);
  const stats=quotationStats(request);text('[data-quotation-count]',stats.received+' / '+stats.total,card);
  const progress=$('.request-progress',card);progress.setAttribute('aria-valuenow',String(Math.round(stats.percent*10)/10));progress.setAttribute('aria-valuetext',stats.received+' of '+stats.total+' hospital quotations received');$('span',progress).style.width=stats.percent+'%';
  if(request.payment_is_simulated){const note=document.createElement('small');note.textContent='Simulated test payment ? no real funds received.';note.dataset.noTranslate='';$('.request-summary',card).append(note);}
  const responses=request.hospital_responses;
  $('.request-no-responses',card).hidden=responses.length>0;$('.request-response-content',card).hidden=responses.length===0;
  text('[data-response-count]',responses.length,card);
  responses.forEach(response=>{
   const hospital=$('#hospital-response-template').content.firstElementChild.cloneNode(true);
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
 $('#requests-results').textContent=requests.length ? 'Showing '+(start+1)+'\u2013'+Math.min(start+pageSize,requests.length)+' of '+requests.length+' '+(requests.length===1?'request':'requests') : 'No requests to show';
 const pagination=$('#requests-pagination');pagination.replaceChildren();pagination.hidden=requests.length<=pageSize;
 pagination.parentElement.classList.toggle('has-pages',!pagination.hidden);
 if(!pagination.hidden) {
  const goTo=page=>{currentPage=page;render();root.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'start'});};
  const addButton=(label,page,disabled=false,active=false)=>{const button=document.createElement('button');button.type='button';button.textContent=label;button.disabled=disabled;button.className=active?'is-current':'';button.setAttribute('aria-label',typeof label==='number'?'Go to page '+label:label+' page');if(active)button.setAttribute('aria-current','page');button.onclick=()=>goTo(page);pagination.append(button);};
  addButton('Previous',currentPage-1,currentPage===1);
  const pages=new Set([1,totalPages,currentPage-1,currentPage,currentPage+1]);let previous=0;
  [...pages].filter(page=>page>=1&&page<=totalPages).sort((a,b)=>a-b).forEach(page=>{if(previous&&page-previous>1){const dots=document.createElement('span');dots.textContent='...';dots.setAttribute('aria-hidden','true');pagination.append(dots);}addButton(page,page,false,page===currentPage);previous=page;});
  addButton('Next',currentPage+1,currentPage===totalPages);
 }
}
function updateFilterCounts() { $$('[data-filter-count]').forEach(node=>{node.textContent=node.dataset.filterCount==='all'?savedRequests.length:savedRequests.filter(request=>request.status===node.dataset.filterCount).length;}); }
updateFilterCounts();
$$('[data-request-filter]').forEach(button=>button.onclick=()=>{activeFilter=button.dataset.requestFilter;currentPage=1;$$('[data-request-filter]').forEach(tab=>tab.setAttribute('aria-pressed',String(tab===button)));render();});
$('#request-sort').addEventListener('change',()=>{currentPage=1;render();});
render();

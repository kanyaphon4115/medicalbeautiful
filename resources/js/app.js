import {initializeCustomerAccess} from './customer-access.js';
import {openCustomerRequestForm} from './customer-request-form.js';
import {translateText} from './i18n';
const uiText=value=>translateText(value,document.documentElement.lang);
const $ = (selector, parent = document) => parent.querySelector(selector);
const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];
const modal = $('#demo-modal');
const content = $('#modal-content');
let toastTimer;
function toast(message) { $('.toast').textContent = message; $('.toast').classList.add('show'); clearTimeout(toastTimer); toastTimer = setTimeout(() => $('.toast').classList.remove('show'), 3500); }
function openModal(html) { content.innerHTML = html; if (!modal.open) modal.showModal(); document.body.style.overflow = 'hidden'; }
$('.modal-close').addEventListener('click', () => modal.close());
modal.addEventListener('close', () => { document.body.style.overflow = ''; });
modal.addEventListener('click', event => { const box = modal.getBoundingClientRect(); if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) modal.close(); });
const procedureCards = $$('.procedure-card').map(card => card.dataset.procedure);
const procedures = procedureCards.length ? procedureCards : ['Eye Surgery', 'Rhinoplasty', 'Lip Surgery', 'Facial Contouring', 'Face Lifting', 'Liposuction', 'Breast Surgery', 'Other Procedures'];
function quote(procedure = '') {
 if (document.body.dataset.signedIn !== 'true') {
  import('./guest-journey.js').then(module=>module.startGuestJourney(procedure));return;
 }
 openCustomerRequestForm({procedure,procedures:JSON.parse(document.body.dataset.requestProcedures),openModal,content,modal});
}
$$('[data-quote]').forEach(button => button.addEventListener('click', () => quote()));
$$('[data-procedure]').forEach(card => card.addEventListener('click', event => {
 if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
 event.preventDefault();
 quote(card.dataset.procedure);
}));
initializeCustomerAccess({openModal,content,modal,quote});
const information = {
 how: ['Your journey, made simple.', '<ol><li><strong>01 · Discover your possibilities</strong>Explore procedures and find an option that aligns with your goals.</li><li><strong>02 · Compare your options</strong>Browse hospital information and sample treatment offers.</li><li><strong>03 · Take the next step</strong>Request a personalized consultation when you feel ready.</li></ol><p class="modal-note">This homepage is a frontend demonstration. Quotes, consultations, and bookings are not currently processed.</p>'],
 reviews: ['Patient perspectives.', '<p>Preview how patient reviews could appear on AESTHETIC.</p><div class="review-example"><span class="review-rating">Rated 5 out of 5</span><strong>“A clearer way to explore my options.”</strong><p>The comparison experience helped me understand what to ask about during a consultation.</p><p><em>Fictional sample review · Demo content</em></p></div>'],
 articles: ['A little knowledge. More confidence.', '<p>Explore these sample article topics for a future AESTHETIC journal.</p><ol><li>Questions to bring to your first aesthetic consultation</li><li>Understanding a treatment quotation</li><li>Planning time for your beauty journey</li></ol><p class="modal-note">Article previews only. Full articles are not available in this demo.</p>'],
 contact: ['We are here for your journey.', '<p>In the full experience, our support team would help you navigate procedures, quotations, and hospital information.</p><p class="modal-note">Contact and support services are unavailable in this frontend demonstration. No message will be sent.</p>'],
 privacy: ['Your privacy matters.', '<p>Account registration stores your name, email address, and a securely hashed password. Session cookies keep you signed in. Treatment requests, medical information, and private photos are stored when you submit a request. Search and favorites are temporary and reset when you reload.</p><p class="modal-note">Images and fonts may load from external providers. This is a demo privacy notice, not a finalized policy.</p>'],
 terms: ['A thoughtful introduction.', '<p>This website is a frontend demonstration. All hospital listings, ratings, and reviews are fictional. Email registration and sign-in are available. No medical advice, real quotations, payments, or bookings are provided.</p><p class="modal-note">These are demo terms for preview purposes.</p>'],
 line: ['Connect with AESTHETIC on LINE.', '<p>Our LINE contact channel is a preview feature. A live LINE account has not been connected to this demonstration.</p>'],
 instagram: ['Follow the inspiration.', '<p>Our Instagram presence is part of the future AESTHETIC experience. This demo does not link to a live social profile.</p>'],
 facebook: ['Stay connected.', '<p>Our Facebook community is part of the future AESTHETIC experience. This demo does not link to a live social profile.</p>'],
 youtube: ['Discover beautiful stories.', '<p>Our video stories are part of the future AESTHETIC experience. This demo does not link to a live YouTube channel.</p>']
};
$$('[data-info]:not([data-info="how"])').forEach(button => button.addEventListener('click', () => { const [title, body] = information[button.dataset.info]; openModal(`<p class="modal-eyebrow">THE AESTHETIC EXPERIENCE</p><h2 id="modal-title">${title}</h2>${body}`); }));
$$('[data-auth]').forEach(button => button.addEventListener('click', () => openModal(`<p class="modal-eyebrow">WELCOME TO AESTHETIC</p><h2>${button.dataset.auth === 'Sign In' ? 'Your journey awaits.' : 'A beautiful beginning.'}</h2><p>${button.dataset.auth} is a preview feature. Accounts are not available in this frontend demonstration.</p><p class="modal-note">You can freely explore procedures, save favorites for this visit, and preview a quote request.</p><button class="button button-gold" id="auth-explore">Explore Procedures</button>`)));
content.addEventListener('click', event => { if (event.target.closest('#auth-explore')) { modal.close(); if ($('#procedures')) $('#procedures').scrollIntoView({behavior:'smooth'}); else window.location.assign($('.site-header').dataset.homeUrl + '#procedures'); } });
$$('.favorite').forEach(button => button.addEventListener('click', () => { const saved = button.getAttribute('aria-pressed') !== 'true'; button.setAttribute('aria-pressed', String(saved)); button.setAttribute('aria-label', `${saved ? 'Remove' : 'Save'} ${button.dataset.name}${saved ? ' from favorites' : ''}`); toast(saved ? `${button.dataset.name} saved for this visit.` : 'Hospital removed from your favorites.'); }));
$$('[data-slide]').forEach(button => button.addEventListener('click', () => { const track = document.getElementById(button.dataset.slide); const direction = Number(button.dataset.direction); const max = track.scrollWidth - track.clientWidth; if (max < 2) { toast(button.dataset.slide === 'procedure-track' ? 'All eight procedures are shown. Select one to explore.' : button.dataset.slide === 'doctor-track' ? 'All six demo doctors are shown. Select View Details to explore.' : 'All six demo hospital partners are shown.'); return; } const atEnd = direction > 0 && track.scrollLeft >= max - 5; const atStart = direction < 0 && track.scrollLeft < 5; track.scrollTo({left: atEnd ? 0 : atStart ? max : track.scrollLeft + track.clientWidth * .75 * direction, behavior:'smooth'}); }));
$('#view-all')?.addEventListener('click', () => { $('#site-search').value = ''; filter(''); $('#hospital-track').scrollTo({left:0,behavior:'smooth'}); toast('Showing all 6 demo hospital partners. Swipe or use the arrows to explore.'); });
$('.menu-toggle').addEventListener('click', () => { const expanded = $('.menu-toggle').getAttribute('aria-expanded') !== 'true'; $('.menu-toggle').setAttribute('aria-expanded', String(expanded)); $('.menu-toggle').setAttribute('aria-label', expanded ? 'Close navigation' : 'Open navigation'); $('.mobile-nav').hidden = !expanded; $('.menu-toggle').textContent = expanded ? '\u00d7' : '\u2630'; $('.search-panel').hidden = true; });
$$('.mobile-nav a, .mobile-nav button').forEach(link => link.addEventListener('click', () => { $('.mobile-nav').hidden = true; $('.menu-toggle').setAttribute('aria-expanded','false'); $('.menu-toggle').textContent = '\u2630'; }));
$('.search-toggle').setAttribute('aria-expanded','false');
$('.search-toggle').addEventListener('click', () => { const panel = $('.search-panel'); panel.hidden = !panel.hidden; $('.search-toggle').setAttribute('aria-expanded',String(!panel.hidden)); if (!panel.hidden) { $('.mobile-nav').hidden = true; $('.menu-toggle').setAttribute('aria-expanded','false'); $('.menu-toggle').textContent = '\u2630'; $('#site-search').focus(); } });
function filter(query) { if ($('[data-auth-page], [data-requests-page], [data-real-guest-page]')) return; query = query.trim().toLowerCase(); let total = 0; [['.procedure-card','#procedure-empty'],['.hospital-card','#hospital-empty']].forEach(([selector,empty]) => { let count = 0; $$(selector).forEach(card => { card.hidden = !card.dataset.search.includes(query) && !card.textContent.toLowerCase().includes(query); if (!card.hidden) count++; }); $(empty).hidden = count > 0; total += count; }); $('#search-status').textContent = query ? `${total} matching procedures and demo hospitals. Results are filtered below.` : 'Explore our procedures and demo hospital partners below.'; }
$('#site-search').addEventListener('input', event => filter(event.target.value));
$('#site-search').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); $('.search-panel').hidden = true; $('.search-toggle').setAttribute('aria-expanded','false'); if ($('#procedures')) $('#procedures').scrollIntoView({behavior:'smooth'}); else window.location.assign($('.site-header').dataset.homeUrl + '#procedures'); } });
$('.clear-search').addEventListener('click', () => { $('#site-search').value = ''; filter(''); $('#site-search').focus(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') { $('.search-panel').hidden = true; $('.search-toggle').setAttribute('aria-expanded','false'); $('.mobile-nav').hidden = true; $('.menu-toggle').setAttribute('aria-expanded','false'); $('.menu-toggle').textContent = '\u2630'; } });
// Every dialog receives a consistent accessible name, including dynamically rendered demos.
new MutationObserver(() => { const title = $('h2',content); if (title) { title.id = 'modal-title'; } }).observe(content,{childList:true});

// Keep validation feedback in English regardless of browser interface language.
content.addEventListener('invalid', event => {
 const field = event.target;
 if (!field.matches('input,select,textarea')) return;
 const validationText=field.closest('[data-no-translate]')?value=>value:uiText;
 if (field.validity.valueMissing) field.setCustomValidity(validationText(field.type === 'checkbox' ? 'Please consent to sharing your information with partner hospitals to continue.' : field.tagName === 'SELECT' ? 'Please select a procedure.' : 'Please complete this field.'));
 else if (field.validity.typeMismatch) field.setCustomValidity(validationText('Please enter a valid email address.'));
}, true);
content.addEventListener('input', event => { if (typeof event.target.setCustomValidity === 'function' && !['file', 'date'].includes(event.target.type) && event.target.name !== 'procedures[]') event.target.setCustomValidity(''); });
content.addEventListener('change', event => { if (typeof event.target.setCustomValidity === 'function' && !['file', 'date'].includes(event.target.type) && event.target.name !== 'procedures[]') event.target.setCustomValidity(''); });

// On auth pages, shared search offers a direct path back to homepage discovery.
if ($('[data-auth-page], [data-requests-page]')) {
 const searchStatus = $('#search-status');
 const showDiscoveryLink = () => {
  searchStatus.replaceChildren(document.createTextNode('Explore treatments and demo hospitals on our '));
  const link = document.createElement('a');
  link.href = $('.site-header').dataset.homeUrl + '#procedures';
  link.textContent = 'homepage';
  searchStatus.append(link, document.createTextNode('. Press Enter to continue.'));
 };
 showDiscoveryLink();
 $('#site-search').addEventListener('input', showDiscoveryLink);
 $('.clear-search').addEventListener('click', showDiscoveryLink);
}

// Validate auth fields before submitting to Laravel; server validation remains authoritative.
$$('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
 const input = document.getElementById(button.dataset.passwordToggle);
 const visible = input.type === 'password';
 input.type = visible ? 'text' : 'password';
 button.setAttribute('aria-pressed', String(visible));
 button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
}));
$$('[data-auth-form]').forEach(form => {
 const error = $('#auth-form-error', form);
 const clearError = () => { error.hidden = true; $$('[aria-invalid]',form).forEach(field => field.removeAttribute('aria-invalid')); };
 form.addEventListener('input',clearError);
 form.addEventListener('change',clearError);
 form.addEventListener('submit', event => {
  event.preventDefault(); clearError();
  let field; let message;
  const name = $('#auth-name', form); const email = $('#auth-email', form); const password = $('#auth-password', form); const confirm = $('#auth-confirm', form); const terms = $('#auth-terms', form);
  if(name && !name.value.trim()) { field=name; message='Please enter your full name.'; }
  else if(!email.value.trim() || !email.validity.valid) { field=email; message='Please enter a valid email address.'; }
  else if(!password.value || (form.dataset.mode === 'register' && password.value.length < 8)) { field=password; message=form.dataset.mode === 'register' ? 'Please enter a password with at least 8 characters.' : 'Please enter your password.'; }
  else if(confirm && confirm.value !== password.value) { field=confirm; message='Your passwords do not match.'; }
  else if(terms && !terms.checked) { field=terms; message='Please agree to the Terms & Conditions and Privacy Policy to continue.'; }
  if(field) { field.setAttribute('aria-invalid','true'); error.textContent=message; error.hidden=false; field.focus(); return; }
  HTMLFormElement.prototype.submit.call(form);
 });
});

if (document.querySelector('[data-requests-page]')) import('./requests.js');

if (document.querySelector('[data-chat-widget]')) import('./chat.js');

// Account menu: real request navigation and clearly labeled account previews.
$$('[data-account-panel]').forEach(button=>button.addEventListener('click',()=>{
 button.closest('details').open=false;
 if(button.dataset.accountPanel==='profile')import('./profile.js').then(module=>module.openProfile()).catch(()=>toast(uiText('Profile could not be loaded. Please try again.')));
 else if(button.dataset.accountPanel==='bookings') {
  openModal('<p class="modal-eyebrow">YOUR JOURNEY</p><h2 id="modal-title">My Bookings</h2><p>No bookings are available yet. Hospital selection, approval, and payments are not connected.</p>');
  const link=document.createElement('a');link.href=$('.desktop-nav a[href*="my-requests"]').href;link.className='button button-gold';link.textContent='View My Requests';content.append(link);
 } else import('./account-settings.js').then(module=>module.openAccountSettings(button.dataset.settingsUrl)).catch(()=>toast('Account settings could not be loaded. Please refresh and try again.'));
}));
document.addEventListener('click',event=>{if(!event.target.closest('.account-dropdown'))$$('.account-dropdown[open]').forEach(menu=>menu.open=false);});
document.addEventListener('keydown',event=>{if(event.key==='Escape')$$('.account-dropdown[open]').forEach(menu=>{menu.open=false;menu.querySelector('summary').focus();});});

if(document.querySelector('[data-partner-directory]')) import('./partner-hospitals.js');

if(document.querySelector('[data-guest-journey]')) import('./guest-journey.js');

document.querySelectorAll('[data-guest-support]').forEach(button=>button.addEventListener('click',()=>{const panel=document.querySelector('.chat-panel');if(panel?.hidden)document.querySelector('.chat-launcher')?.click();document.querySelector('[data-chat-channel="support"]')?.click();}));

document.querySelectorAll('[data-customer-booking-chat]').forEach(button=>button.addEventListener('click',()=>{if(document.querySelector('.chat-panel')?.hidden)document.querySelector('.chat-launcher')?.click();document.querySelector('[data-chat-channel="hospital"]')?.click();}));

if(document.querySelector('[data-guest-otp-dialog]'))import('./guest-verification.js').then(module=>module.initializeGuestVerification(document.querySelector('[data-guest-otp-dialog]')));

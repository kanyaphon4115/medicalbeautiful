// Frontend demo state only. These checks are not a production authorization boundary.
export const STORAGE_KEY='aesthetic-guest-journey-v1';
export const emptyState=()=>({version:1,requests:[],accounts:[],current:null});
const email=value=>String(value).trim().toLowerCase();
const round=value=>Math.round((Number(value)+Number.EPSILON)*100)/100;
export function depositFor(total){
 if(!Number.isFinite(Number(total))||Number(total)<=0)throw Error('A positive quotation price is required.');
 return round(Math.min(Number(total),5000,Math.max(2000,Number(total)*.05)));
}
export const strongPassword=value=>value.length>=8&&/[a-z]/.test(value)&&/[A-Z]/.test(value)&&/[0-9]/.test(value);
export const REPLACEMENT_LINK_MESSAGE='If the request details match our records, a replacement access link will be emailed. Please check your inbox.';
export class Journey{
 constructor(state=emptyState(),now=()=>Date.now(),id=()=>crypto.randomUUID()){this.state=state;this.now=now;this.id=id;}
 request(data){
  if(!data.name?.trim()||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email(data.email))||!(data.procedures?.length||data.procedure)||!data.hospitals?.length)throw Error('Complete your name, email, and at least one procedure.');
  const r={...data,email:email(data.email),id:`REQ-DEMO-${String(this.state.requests.length+1).padStart(6,'0')}`,token:this.id(),created:this.now(),accessUntil:0,quotes:[],booking:null,payment:null,verified:false,owner:this.state.current===email(data.email)?this.state.current:null,messages:[]};
  this.state.requests.push(r);return r;
 }
 profile(r){return {name:r.name,email:r.email,line_id:r.line_id||'',phone_number:r.phone_number||r.phone||'',date_of_birth:r.date_of_birth||'',gender:r.gender||''};}
 updateProfile(r){const a=this.state.accounts.find(a=>a.email===r.owner);if(a){a.profile=this.profile(r);a.name=r.name;}}
 saveProfile(data){
  const a=this.state.accounts.find(a=>a.email===this.state.current);if(!a)throw Error('Sign in to edit your profile.');
  const profile={...(a.profile||{}),email:a.email};
  for(const key of ['name','phone_number','line_id','date_of_birth','gender'])if(Object.hasOwn(data,key))profile[key]=String(data[key]||'').trim();
  if(!profile.name||profile.name.length>80)throw Error('Enter your full name (up to 80 characters).');
  if(profile.phone_number&&!/^\+[1-9][0-9]{6,14}$/.test(profile.phone_number))throw Error('Enter a valid international phone number.');
  if(profile.line_id?.length>100)throw Error('LINE ID must be at most 100 characters.');
  if(profile.gender&&!['Female','Male'].includes(profile.gender))throw Error('Select a valid gender.');
  if(profile.date_of_birth && (!/^\d{4}-\d{2}-\d{2}$/.test(profile.date_of_birth)||!Number.isFinite(Date.parse(profile.date_of_birth))||Date.parse(profile.date_of_birth)>this.now()))throw Error('Enter a valid date of birth.');
  a.profile=profile;a.name=profile.name;return profile;
 }
 find(token){const r=this.state.requests.find(r=>r.token===token);if(!r)throw Error('This request access link is invalid.');return r;}
 allowed(r){return (r.owner&&r.owner===this.state.current)||r.accessUntil>this.now();}
 access(token,address){const r=this.find(token);if(r.email!==email(address))throw Error('The email address does not match this request.');r.accessUntil=this.now()+7*86400000;return r;}
 guard(token){const r=this.find(token);if(!this.allowed(r))throw Error('Please verify your email using your demo access link.');return r;}
 guestLink(r){
  const link={token:this.id(),expires:this.now()+7*86400000};
  (r.guestLinks??=[]).push(link);return link;
 }
 openGuestLink(requestToken,linkToken){
  const r=this.find(requestToken),link=r.guestLinks?.find(link=>link.token===linkToken);
  if(!link||link.revoked||link.expires<=this.now())throw Error('This guest access link is invalid or has expired. Request a replacement email link below.');
  r.accessUntil=link.expires;return r;
 }
 notifyResponse(r,response){
  const notifications=this.state.notifications??=[];
  const key=JSON.stringify([r.token,response.hospital,response.id]);
  if(notifications.some(n=>n.key===key))return;
  const account=this.state.accounts.find(a=>a.email===r.email);
  const emailSent=!account||account.emailAlerts!==false;
  const link=emailSent||!r.owner?this.guestLink(r):null;
  notifications.push({id:this.id(),key,requestToken:r.token,requestId:r.id,recipient:r.email,hospital:response.hospital,responseId:response.id,kind:response.kind||'quotation',created:this.now(),dashboard:!!account,emailSent,linkToken:link?.token,expires:link?.expires,read:false});
 }
 notificationPreview(id){
  const n=this.state.notifications?.find(n=>n.id===id&&n.emailSent);
  if(!n)throw Error('This simulated email is unavailable.');
  return {id:n.id,requestId:n.requestId,recipient:n.recipient,requestToken:n.requestToken,linkToken:n.linkToken,expires:n.expires,kind:n.kind};
 }
 requestReplacement(reference,address){
  const r=this.state.requests.find(r=>r.id===String(reference).trim()&&r.email===email(address));
  if(r&&(!r.replacementSent||this.now()-r.replacementSent>=60000)){
   r.replacementSent=this.now();r.accessUntil=0;
   (r.guestLinks||[]).forEach(link=>{link.revoked=true;});
   const link=this.guestLink(r);
   (this.state.notifications??=[]).push({id:this.id(),requestToken:r.token,requestId:r.id,recipient:r.email,kind:'replacement',emailSent:true,linkToken:link.token,expires:link.expires,created:this.now(),read:false});
  }
  return REPLACEMENT_LINK_MESSAGE;
 }
 editable(r){return !r.booking&&!r.quotes.length&&!r.emailRequest;}
 edit(token,data){const r=this.guard(token);if(!this.editable(r))throw Error('A quotation has already been issued. Create a new request to obtain revised quotations.');Object.assign(r,{...data,photos:data.photos||[],updated:this.now()});return r;}
 duplicate(token){const r=this.guard(token);return {...this.profile(r),procedures:[...(r.procedures||[r.procedure])],goals:r.goals||r.description||'',description:r.description||'',date:r.date,preferred_surgery_month:r.preferred_surgery_month||r.date?.slice(5,7)||'',preferred_surgery_year:r.preferred_surgery_year||r.date?.slice(0,4)||'',additional_notes:r.additional_notes||'',photos:[]};}
 respond(token,hospital,responseId=null,kind='quotation'){
  const r=this.find(token);if(!r.hospitals.includes(hospital))throw Error('This hospital was not selected.');if(r.booking)throw Error('A hospital has already been selected.');
  const responses=r.responses??=[];
  if(!responses.some(response=>response.hospital===hospital&&response.id===(responseId||hospital)))responses.push({id:responseId||hospital,hospital,kind,created:this.now(),message:kind==='quotation'?'A hospital has submitted a quotation.':'The hospital has acknowledged your request. A quotation will follow.'});
  if(kind==='quotation'&&!r.quotes.some(q=>q.hospital===hospital)){const total=[65000,60000,80000,95000,150000,40000][r.quotes.length%6];r.quotes.push({id:this.id(),hospital,total,details:`Personalized ${r.procedure} consultation and treatment plan. Fictional quotation for demonstration only.`,included:['Consultation fee','Surgery fee','Standard medication','Follow-up care']});}
  this.notifyResponse(r,{id:responseId||hospital,hospital,kind});return r;
 }
 deposit(q){return q.depositOverride??depositFor(q.total);}
 overrideDeposit(token,quoteId,amount,admin){
  if(!admin?.authorized||!admin.id)throw Error('Only an authorized Admin may override the demo deposit.');
  const r=this.guard(token),q=r.quotes.find(q=>q.id===quoteId);if(!q||r.booking||r.payment?.status==='Pending')throw Error('The deposit cannot be changed after payment has started.');
  const minimum=Math.min(2000,q.total),maximum=Math.min(5000,q.total);
  if(!Number.isFinite(Number(amount))||amount<minimum||amount>maximum)throw Error(`Deposit must be between THB ${minimum} and THB ${maximum}.`);
  q.depositOverride=round(amount);q.overrideAudit={adminId:admin.id,at:this.now(),simulated:true};return q;
 }
 beginPayment(token,quoteId){
  const r=this.guard(token),q=r.quotes.find(q=>q.id===quoteId);if(!q)throw Error('Select an available quotation.');
  if(r.booking){if(r.booking.quote!==quoteId)throw Error('This request already has a selected hospital.');return r.payment||r.booking;}
  if(r.payment?.status==='Under Review')return r.payment;
  if(r.payment?.quote===quoteId&&r.payment.expires>this.now()&&r.payment.status==='Pending')return r.payment;
  if(r.payment){r.paymentHistory??=[];r.paymentHistory.push({...r.payment,status:r.payment.expires<=this.now()?'Expired':'Cancelled'});}
  r.pendingBookingId??='BK-DEMO-'+r.id.replace('REQ-DEMO-','');
  r.payment={reference:'PAY-DEMO-'+this.id().replace(/-/g,'').slice(-12),quote:quoteId,hospital:q.hospital,total:q.total,deposit:this.deposit(q),status:'Pending',expires:this.now()+1800000,created:this.now(),simulated:true};return r.payment;
 }
 claimPayment(token){
  const r=this.guard(token),p=r.payment;
  if(p?.status==='Under Review')return p;
  if(!p||p.status!=='Pending'||p.expires<=this.now())throw Error('This payment is not awaiting payment.');
  p.status='Under Review';p.claimedAt=this.now();return p;
 }
 paymentState(token){
  const r=this.guard(token),p=r.payment;
  if(p?.status==='Pending'&&p.expires<=this.now())p.status='Expired';
  return ({Pending:'pending_payment','Under Review':'pending_verification',Paid:'paid',Failed:'failed',Expired:'expired',Refunded:'refunded'})[p?.status]||(r.booking?'paid':'not_started');
 }
 resume(token){
  const r=this.guard(token),status=this.paymentState(token);
  if(status==='paid'){
   if(r.owner)return r.owner===this.state.current?'dashboard':'demo-sign-in';
   if(this.verified(r))return this.state.accounts.some(a=>a.email===r.email)?'sign-in':'account';
   return 'verify';
  }
  return status==='pending_verification'?'review':status==='not_started'?(r.selectedQuote?'checkout':'status'):status==='pending_payment'?'checkout':'payment-state';
 }
 confirmPayment(token,sendCode=true){
  const r=this.guard(token);if(r.booking)return r.booking;
  const p=r.payment;if(!p||!['Pending','Under Review'].includes(p.status))throw Error('Start the QR deposit payment first.');
  if(p.status==='Pending'&&p.expires<=this.now()){p.status='Expired';throw Error('This payment reference has expired. Generate a new demo payment reference.');}
  p.status='Paid';p.paidAt=this.now();
  r.booking={id:r.pendingBookingId||`BK-DEMO-${String(this.state.requests.filter(x=>x.booking).length+1).padStart(6,'0')}`,quote:p.quote,hospital:p.hospital,total:p.total,deposit:p.deposit,remaining:round(p.total-p.deposit),status:'Confirmed',paidAt:p.paidAt,reference:p.reference,method:'PromptPay (Thai QR) — DEMO',simulated:true};
  if(!r.owner&&sendCode)this.issue(token);return r.booking;
 }
 // Compatibility for pre-existing demo callers; new UI separates QR creation and confirmation.
 pay(token,quoteId,sendCode=true){this.beginPayment(token,quoteId);return this.confirmPayment(token,sendCode);}
 issue(token){const r=this.guard(token);if(!r.booking)throw Error('Complete the demo payment first.');if(r.otp&&this.now()<r.otp.sent+30000)throw Error('Please wait 30 seconds before requesting another code.');r.otp={code:String(parseInt(this.id().replace(/[^0-9a-f]/gi,'').slice(0,8)||'123456',16)%1000000).padStart(6,'0'),sent:this.now(),expires:this.now()+600000,attempts:0};r.verified=false;return r.otp.code;}
 verify(token,code){const r=this.guard(token);if(!r.booking||!r.otp)throw Error('Request a new verification code.');if(this.now()>=r.otp.expires)throw Error('This code has expired. Please resend the code.');if(r.otp.attempts>=5)throw Error('Too many attempts. Please resend the code.');r.otp.attempts++;if(code!==r.otp.code)throw Error('Invalid code. Please enter the six-digit demo code.');r.verified=true;r.verifiedUntil=this.now()+600000;r.otp=null;return this.state.accounts.some(a=>a.email===r.email)?'sign-in':'account';}
 verified(r){return r.verified&&r.verifiedUntil>this.now();}
 usernameAvailable(username){return /^[a-zA-Z][a-zA-Z0-9_.-]{2,29}$/.test(username)&&!this.state.accounts.some(a=>a.username?.toLowerCase()===username.toLowerCase());}
 activate(token,name,passwordHash,salt,options={}){
  const r=this.guard(token);if(!this.verified(r)||!r.booking)throw Error('Verify your booking before creating an account.');if(this.state.accounts.some(a=>a.email===r.email))throw Error('This email already has a demo account. Please sign in.');
  const username=options.username||r.email.split('@')[0];if(!this.usernameAvailable(username))throw Error('Choose an available username: 3–30 letters, numbers, dots, dashes or underscores, starting with a letter.');
  if(options.username&&!options.terms)throw Error('Accept the Terms of Service and Privacy Policy.');
  const account={name,email:r.email,username,passwordHash,salt,profile:this.profile({...r,name}),termsAcceptedAt:this.now()};this.state.accounts.push(account);this.state.current=r.email;r.owner=r.email;r.verified=false;
  (this.state.notifications||[]).filter(n=>n.requestToken===r.token&&n.kind!=='replacement').forEach(n=>{n.dashboard=true;});return r;
 }
 signIn(address,passwordHash){const identity=email(address);const a=this.state.accounts.find(a=>(a.email===identity||a.username?.toLowerCase()===identity)&&a.passwordHash===passwordHash);if(!a)throw Error('Email, username or password is incorrect for this demo session.');this.state.current=a.email;for(const r of this.state.requests){if(r.email===a.email&&this.verified(r)){r.owner=a.email;r.verified=false;}}return a;}
 canChat(r,role,hospital=null){return this.allowed(r)&&(role==='customer'||role==='support'||(role==='hospital'&&r.booking?.hospital===hospital))&&(role!=='hospital'||!!r.booking);}
 chat(token,channel,text){const r=this.guard(token);if(channel==='booking'&&!r.booking)throw Error('Booking Chat unlocks after hospital selection and demo deposit payment.');if(!text.trim())throw Error('Please enter a message.');r.messages.push({channel,role:'customer',text:text.trim(),at:this.now()},{channel,role:'support',text:'DEMO automated reply: your message is saved in this browser session only.',at:this.now()});}
 status(r){return r.booking?'Confirmed':r.payment?.status==='Pending'&&r.payment.expires>this.now()?'Pending Payment':r.quotes.length?'Quotation Received':'Pending';}
}

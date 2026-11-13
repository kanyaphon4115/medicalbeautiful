import {attachMessageTranslation} from './chat-translation.js';
import {postChatMessage, refreshChatSession} from './chat-request.js';
import {createChatPolling} from './chat-polling.js';

export function initBookingInbox({root, config, t=value=>value, esc}) {
    let rooms=[], selected=String(config.initialRoom||new URLSearchParams(location.search).get('room')||''), role='';
    let canSend=false, blocked=false, sending=false, disposed=false, loadVersion=0, pending, roomSignature='', messageSignature='';
    const drafts=new Map(), bubbles=new Map();
    root.innerHTML=`<section class="ap-inbox"><aside class="ap-conversations"><h2>${esc(t('Booking Chats'))}</h2><input id="booking-chat-search" type="search" placeholder="${esc(t('Search conversations...'))}" aria-label="Search conversations"><select id="booking-chat-status" aria-label="Status"><option value="">${esc(t('All Status'))}</option><option value="active">${esc(t('Active'))}</option><option value="locked">${esc(t('Locked'))}</option></select><div class="ap-conversation-list" id="booking-chat-list"></div></aside><div class="ap-chat-room"><header id="booking-chat-header"></header><div class="ap-participants" id="booking-chat-participants"></div><div class="ap-locked" id="booking-chat-locked"></div><div class="ap-chat-history" id="booking-chat-history" role="log" aria-live="polite"></div><form class="ap-chat-compose" id="booking-chat-compose"><label class="ap-sr" for="booking-chat-message">Message</label><textarea id="booking-chat-message" required maxlength="2000" placeholder="${esc(t('Type a message...'))}" disabled></textarea><button type="submit" class="ap-button gold" disabled>${esc(t('Send'))}</button></form><p id="booking-chat-readonly" class="ap-note" hidden>Read Only: Customer Support can view this conversation. Only the customer and selected hospital can send messages.</p><p id="booking-chat-feedback" role="status" class="ap-note"></p></div></section><dialog class="real-request-dialog" id="booking-chat-info"><button type="button" class="ap-button" data-booking-close>${esc(t('Close'))}</button><div id="booking-chat-info-content"></div></dialog>`;
    const find=selector=>root.querySelector(selector), input=find('#booking-chat-message'), submit=find('[type="submit"]');
    const feedback=message=>{find('#booking-chat-feedback').textContent=message;};
    const current=()=>rooms.find(room=>String(room.id)===selected);
    const active=()=>!disposed&&!blocked&&!document.hidden&&root.isConnected&&!root.closest('[hidden]');
    function controls(){const locked=blocked||!current()?.unlocked||!canSend;input.disabled=locked;submit.disabled=locked||sending;}
    function renderList(){
        const search=find('#booking-chat-search').value.trim().toLowerCase(), status=find('#booking-chat-status').value;
        find('#booking-chat-list').innerHTML=rooms.filter(room=>`${room.booking_reference} ${room.request_reference} ${room.customer_name} ${room.hospital_name} ${room.procedure}`.toLowerCase().includes(search)&&(!status||(status==='active')===room.unlocked))
            .map(room=>`<button type="button" data-booking-room="${room.id}" class="${String(room.id)===selected?'active':''}"><span class="ap-avatar">${esc((room.customer_name||'C')[0])}</span><span class="ap-booking-conversation-details"><strong>${esc(room.customer_name)}</strong><small class="ap-booking-conversation-id">${esc(room.booking_reference)}</small><small>${esc(room.hospital_name)}</small><small>${esc(room.procedure)}</small><small class="ap-booking-conversation-status">${esc(t(room.unlocked?'Active':'Locked'))}</small></span></button>`).join('')||`<p class="ap-empty">${esc(t('No records found.'))}</p>`;
    }
    function details(rows){return `<dl class="ap-details">${rows.map(([label,value])=>`<dt>${esc(t(label))}</dt><dd>${esc(value)}</dd>`).join('')}</dl>`;}
    function renderRoom(){
        const room=current(), signature=JSON.stringify([room,canSend]);
        if(signature===roomSignature){controls();return;}
        roomSignature=signature;
        find('#booking-chat-header').innerHTML=room?`<span class="ap-avatar">${esc((room.customer_name||'C')[0])}</span><div><strong>${esc(room.booking_reference)}</strong><small>${esc(room.request_reference)}</small></div><button type="button" class="ap-button" data-booking-customer>${esc(t('Customer information'))}</button><button type="button" class="ap-button" data-booking-details>${esc(t('Booking Details'))}</button>`:`<strong>${esc(t('Select a conversation'))}</strong>`;
        find('#booking-chat-participants').innerHTML=room?`<span>${esc(t('Customer'))}: ${esc(room.customer_name)}</span><span>${esc(t('Hospital'))}: ${esc(room.hospital_name)}</span>`:'';
        const unlocked=Boolean(room?.unlocked),canView=Boolean(room?.can_view??room?.unlocked);
        find('#booking-chat-compose').hidden=!unlocked||!canSend;find('#booking-chat-readonly').hidden=!canView||canSend;find('#booking-chat-history').hidden=!canView;find('#booking-chat-locked').hidden=unlocked;
        find('#booking-chat-locked').innerHTML=room?`<h2>${esc(t('Booking Chat is locked'))}</h2><p>Hospital chat will be available once your Initial Deposit and Booking Deposit payments have been verified and approved.</p>${details([['Hospital selection',room.hospital_selected?'Selected':'Pending'],['Approval',room.hospital_approved?'Approved':'Pending'],['Initial Deposit',room.step4_approved?'Approved':'Pending'],['Booking Deposit',room.step9_approved?'Approved':'Pending'],['Account',room.account_active?'Activated':'Pending'],['Booking Status',room.booking_status],['Deposit Status',room.deposit_status]])}`:`<p>${esc(t('No records found.'))}</p>`;
        controls();
    }
    function renderMessages(messages){
        const signature=selected+JSON.stringify(messages);if(signature===messageSignature)return;
        const history=find('#booking-chat-history'), nearBottom=history.scrollHeight-history.scrollTop-history.clientHeight<80, initial=!messageSignature;
        messageSignature=signature;const fragment=document.createDocumentFragment(), visible=new Set();
        for(const message of messages){
            const key=selected+':'+message.id;visible.add(key);let bubble=bubbles.get(key);
            if(!bubble){
                bubble=document.createElement('article');bubble.className='ap-chat-bubble'+(message.sender_type===role?' own':'');
                const name=message.sender_type==='hospital'?current()?.hospital_name:message.sender_type==='customer'?current()?.customer_name:'Booking Admin';
                bubble.innerHTML=`<strong>${esc(name)}</strong><p style="white-space:pre-wrap">${esc(message.body)}</p><time>${esc(new Date(message.sent_at).toLocaleString(config.locale||'en'))}</time>`;
                attachMessageTranslation(bubble,message);bubbles.set(key,bubble);
            }
            fragment.append(bubble);
        }
        if(!messages.length){const note=document.createElement('p');note.className='ap-empty';note.textContent='No messages yet. Start a conversation.';fragment.append(note);}
        history.replaceChildren(fragment);for(const key of bubbles.keys())if(!visible.has(key))bubbles.delete(key);
        if(nearBottom||initial)history.scrollTop=history.scrollHeight;
    }
    function block(){blocked=true;polling.stop();find('#booking-chat-history').replaceChildren();controls();}
    async function get(url,signal){
        const response=await fetch(url,{signal,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','X-Chat-User-Id':String(config.userId)}});
        if([401,409].includes(response.status)){block();throw new Error('Your account changed or session expired. Please reload and sign in again.');}
        const data=await response.json();if(!response.ok)throw new Error(data.message||'Conversation could not be loaded.');return data;
    }
    async function load(){
        if(!active())return;pending?.abort();pending=new AbortController();const signal=pending.signal, version=++loadVersion;
        try{
            const data=await get(config.bookingChatEndpoint,signal);if(disposed||version!==loadVersion)return;
            rooms=data.rooms||[];role=data.role;canSend=data.can_send===true&&role==='hospital';
            const previousSelection=selected;
            if(selected&&!current()){const match=rooms.find(room=>room.booking_reference===selected);selected=match?String(match.id):'';}
            if(!selected&&rooms.length)selected=String((rooms.find(room=>room.unlocked)||rooms[0]).id);
            if(previousSelection!==selected){drafts.set(previousSelection,input.value);input.value=drafts.get(selected)||'';messageSignature='';find('#booking-chat-history').replaceChildren();}
            renderList();renderRoom();const target=selected;
            if(current()?.can_view??current()?.unlocked){const result=await get(config.bookingChatEndpoint+'/'+encodeURIComponent(target)+'/messages',signal);if(!disposed&&version===loadVersion&&target===selected)renderMessages(result.messages||[]);}
        }catch(error){if(!disposed&&error.name!=='AbortError'&&version===loadVersion)feedback(error.message);}
    }
    function choose(id){drafts.set(selected,input.value);selected=String(id);input.value=drafts.get(selected)||'';roomSignature='';messageSignature='';feedback('');find('#booking-chat-history').replaceChildren();renderList();renderRoom();void load();}
    root.addEventListener('click',event=>{
        const button=event.target.closest('[data-booking-room]');if(button&&!blocked)choose(button.dataset.bookingRoom);
        const room=current(), customer=event.target.closest('[data-booking-customer]'), booking=event.target.closest('[data-booking-details]');
        if(room&&(customer||booking)){
            find('#booking-chat-info-content').innerHTML=`<h2>${customer?'Customer information':'Booking Details'}</h2>`+details(customer?[['Customer',room.customer_name],['Email',room.customer_email]]:[['Booking Reference',room.booking_reference],['Request ID',room.request_reference],['Hospital',room.hospital_name],['Procedure',room.procedure],['Booking Status',room.booking_status],['Deposit Status',room.deposit_status]]);
            find('#booking-chat-info').showModal();
        }
        if(event.target.closest('[data-booking-close]'))find('#booking-chat-info').close();
    });
    find('#booking-chat-search').addEventListener('input',renderList);find('#booking-chat-status').addEventListener('change',renderList);
    find('#booking-chat-compose').addEventListener('submit',async event=>{
        event.preventDefault();const body=input.value.trim(), target=selected;
        if(!body||sending||blocked||!canSend||!current()?.unlocked)return;sending=true;controls();feedback('');
        try{
            const session=await refreshChatSession({sessionEndpoint:config.chatSessionEndpoint,userId:config.userId});
            const response=await postChatMessage({endpoint:config.bookingChatEndpoint+'/'+encodeURIComponent(target)+'/messages',sessionEndpoint:config.chatSessionEndpoint,payload:{body},token:session.csrf_token,userId:config.userId,onToken:()=>{}});
            if(response.status===409){block();throw new Error('Your signed-in account changed. Please reload.');}
            const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Message could not be sent.');
            if(target===selected&&input.value.trim()===body){input.value='';drafts.delete(target);}else if((drafts.get(target)||'').trim()===body)drafts.delete(target);
            await load();
        }catch(error){if(error.sessionInvalid)block();if(!disposed)feedback(error.message);}
        finally{sending=false;if(!disposed)controls();}
    });
    const polling=createChatPolling({load,isActive:active});
    const visibility=()=>{if(document.hidden)polling.stop();else if(active())polling.start();};
    document.addEventListener('visibilitychange',visibility);polling.start();
    return ()=>{disposed=true;pending?.abort();polling.stop();document.removeEventListener('visibilitychange',visibility);};
}

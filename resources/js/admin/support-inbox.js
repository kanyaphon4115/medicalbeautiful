import {attachMessageTranslation} from '../chat-translation.js';
import {postChatMessage, refreshChatSession} from '../chat-request.js';
import {createChatPolling} from '../chat-polling.js';

export function initSupportInbox({root, config, t, esc}) {
    let conversations = [], selected = '', messages = [], signature = '', blocked = false, sending = false, writable = false;
    const drafts = new Map();
    const acknowledged = new Map();
    let markingRead = false;
    const bubbles = new Map();
    root.innerHTML = `<section class="ap-inbox"><aside class="ap-conversations"><h2>${esc(t('Customer Support'))}</h2><input id="support-search" type="search" placeholder="${esc(t('Search conversations…'))}"><div class="ap-conversation-list" id="support-list"></div></aside><div class="ap-chat-room"><header><strong id="support-name">${esc(t('Select a conversation'))}</strong></header><div class="ap-chat-history" id="support-history" role="log" aria-live="polite"></div><form class="ap-chat-compose" id="support-compose"><label class="ap-sr" for="support-message">${esc(t('Message'))}</label><textarea id="support-message" required maxlength="2000" placeholder="${esc(t('Type a message…'))}" disabled></textarea><button type="submit" class="ap-button gold" disabled>${esc(t('Send'))}</button></form><p id="support-feedback" role="status" class="ap-note"></p></div></section>`;
    const find = selector => root.querySelector(selector);
    const input = find('#support-message'), submit = find('[type="submit"]');
    const feedback = message => { find('#support-feedback').textContent = message; };
    function controls() { input.disabled = blocked || !selected || !writable; submit.disabled = blocked || !selected || !writable || sending; }
    function renderList() {
        const search = find('#support-search').value.toLowerCase();
        find('#support-list').innerHTML = conversations.filter(c => c.label.toLowerCase().includes(search)).map(c => `<button type="button" data-customer="${esc(c.id)}" class="${String(c.id) === selected ? 'active' : ''}"><span class="ap-avatar">${esc(c.label[0])}</span><span class="ap-booking-conversation-details"><strong>${esc(c.label)}</strong><small>${esc(c.latest_message||'')}</small><time>${esc(c.last_message_time?new Date(c.last_message_time).toLocaleString(config.locale):'')}</time><small>${esc(c.status||'Active')}</small></span>${c.unread_count?`<b class="support-unread" aria-label="${esc(t('Unread'))}: ${c.unread_count}">${c.unread_count}</b>`:''}</button>`).join('') || `<p class="ap-empty">${esc(t('No records found.'))}</p>`;
    }
    function renderMessages() {
        const history = find('#support-history');
        const next = selected + JSON.stringify(messages);
        if (next === signature) return;
        const nearBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 80;
        const initial = !signature;
        signature = next;
        const fragment = document.createDocumentFragment();
        const visible = new Set();
        for (const message of messages) {
            const key = selected + ':' + message.id;
            visible.add(key);
            let bubble = bubbles.get(key);
            if (!bubble) {
                bubble = document.createElement('article');
                bubble.className = 'ap-chat-bubble' + (message.sender_type === 'support' ? ' own' : '');
                bubble.innerHTML = `<strong>${esc(t(message.sender_type === 'support' ? 'Customer Support' : 'Customer'))}</strong><p style="white-space:pre-wrap">${esc(message.body)}</p><time>${esc(new Date(message.sent_at).toLocaleString(config.locale))}</time>`;
                attachMessageTranslation(bubble, message);
                bubbles.set(key, bubble);
            }
            fragment.append(bubble);
        }
        history.replaceChildren(fragment);
        for (const key of bubbles.keys()) if (!visible.has(key)) bubbles.delete(key);
        if (nearBottom || initial) history.scrollTop = history.scrollHeight;
    }
    async function load() {
        const target = selected;
        const query = new URLSearchParams({channel:'support'});
        if (target) query.set('customer_id', target);
        try {
            const response = await fetch(config.chatEndpoint + '?' + query, {cache:'no-store', headers:{Accept:'application/json', 'X-Chat-User-Id':String(config.userId)}});
            if ([401,409].includes(response.status)) { blocked = true; polling.stop(); controls(); throw new Error('Your signed-in account changed or session expired. Please reload and sign in again.'); }
            if (!response.ok) throw new Error('Conversation could not be loaded. Please try again.');
            const data = await response.json();
            if (target !== selected) return;
            writable=data.can_send===true;controls();
            conversations = data.conversations || [];
            renderList();
            if (!selected && conversations.length) { choose(String(conversations[0].id)); return; }
            messages = selected ? data.messages : [];
            find('#support-name').textContent = conversations.find(c => String(c.id) === selected)?.label || t('Select a conversation');
            renderMessages();
            await markRead();
        } catch (error) { feedback(error.message); }
    }
    async function markRead() {
        const history = find('#support-history');
        const target = selected, last = messages.at(-1)?.id;
        if (!target || !last || blocked || markingRead || document.hidden || history.scrollHeight-history.scrollTop-history.clientHeight>80 || (acknowledged.get(target)||0)>=last) return;
        markingRead = true;
        try {
            const session = await refreshChatSession({sessionEndpoint:config.chatSessionEndpoint, userId:config.userId});
            const response = await postChatMessage({endpoint:config.chatReadEndpoint,sessionEndpoint:config.chatSessionEndpoint,payload:{customer_id:Number(target),last_message_id:last},token:session.csrf_token,userId:config.userId,onToken:()=>{}});
            if (!response.ok) throw new Error('Read status could not be saved.');
            acknowledged.set(target,last);
            if (target===selected) { const conversation=conversations.find(c=>String(c.id)===target);if(conversation)conversation.unread_count=0;renderList(); }
        } catch(error) { if(error.sessionInvalid){blocked=true;polling.stop();controls();}feedback(error.message); }
        finally { markingRead=false; }
    }
    find('#support-history').addEventListener('scroll',()=>void markRead());
    function choose(id) {
        drafts.set(selected, input.value);
        selected = id; writable=false; input.value = drafts.get(id) || ''; messages = []; signature = '';
        feedback(''); renderList(); renderMessages(); controls(); void load();
    }
    find('#support-list').addEventListener('click', event => { const button = event.target.closest('[data-customer]'); if (button && !blocked) choose(button.dataset.customer); });
    find('#support-search').addEventListener('input', renderList);
    find('#support-compose').addEventListener('submit', async event => {
        event.preventDefault();
        const body = input.value.trim(), target = selected;
        if (!body || !target || sending || blocked || !writable) return;
        sending = true; controls(); feedback('');
        try {
            const session = await refreshChatSession({sessionEndpoint:config.chatSessionEndpoint, userId:config.userId});
            const response = await postChatMessage({endpoint:config.chatEndpoint, sessionEndpoint:config.chatSessionEndpoint, payload:{channel:'support', customer_id:Number(target), body}, token:session.csrf_token, userId:config.userId, onToken:()=>{}});
            if (response.status === 409) { blocked = true; polling.stop(); throw new Error('Your signed-in account changed. Please reload.'); }
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Message could not be sent.');
            if (selected === target && input.value.trim() === body) { input.value = ''; drafts.delete(target); signature = ''; }
            else if ((drafts.get(target) || '').trim() === body) drafts.delete(target);
            await load();
        } catch (error) { if (error.sessionInvalid) { blocked = true; polling.stop(); } feedback(error.message); }
        finally { sending = false; controls(); }
    });
    const polling = createChatPolling({load, isActive:()=>!document.hidden && !blocked});
    document.addEventListener('visibilitychange', () => { if (document.hidden) polling.stop(); else if (!blocked) polling.start(); });
    window.addEventListener('pagehide', () => polling.stop());
    polling.start();
}

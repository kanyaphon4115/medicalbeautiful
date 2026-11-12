import {chatSenderLabel} from './chat-sender-label.js';
import {attachMessageTranslation} from './chat-translation.js';
import {postChatMessage, refreshChatSession} from './chat-request.js';
import {createChatPolling} from './chat-polling.js';
const widget = document.querySelector('[data-chat-widget]');
const $ = selector => widget.querySelector(selector);
const $$ = selector => [...widget.querySelectorAll(selector)];
const launcher = $('.chat-launcher'), panel = $('.chat-panel');
const select = $('#chat-request-select'), customers = $('#chat-customer-select');
const authenticated = widget.dataset.authenticated === 'true';
const staff = widget.dataset.support === 'true';
let channel = 'support', eligible = [], loadVersion = 0, pendingLoad;
let renderedMessages = [], renderedSignature = null, sessionBlocked = false, sending = false, conversationWritable = false;
const feedback = message => { $('.chat-feedback').textContent = message; };
const csrf = document.querySelector('meta[name="csrf-token"]');
function context() { return channel + ':' + (channel === 'hospital' ? select.value : customers?.value || ''); }
function updateComposer() {
    const unavailable = sessionBlocked || !conversationWritable || (staff && channel === 'support' && !customers?.value);
    const button = $('button[type="submit"]');
    if (button) button.disabled = unavailable || sending;
    const textarea = $('#chat-message');
    if (textarea) textarea.disabled = unavailable;
}
function blockSession(message) {
    sessionBlocked = true;
    loadVersion++;
    pendingLoad?.abort();
    polling.stop();
    renderedMessages = [];
    renderedSignature = null;
    $('.chat-messages').replaceChildren();
    feedback(message + ' Your draft has been kept. Use a normal window and Incognito, or separate browser profiles, for two accounts.');
    updateComposer();
}
function gate() {
    const hospitalName=eligible.find(item=>String(item.id)===select.value)?.hospital_name||'Hospital';
    const heading=$('#chat-title'), eyebrow=$('[data-chat-heading]'), input=$('#chat-message');
    if(heading)heading.textContent=channel==='hospital'?'Hospital Chat':'How can we help?';
    if(eyebrow)eyebrow.textContent=channel==='hospital'?hospitalName:'AESTHETIC CONCIERGE';
    if(input)input.placeholder=channel==='hospital'?'Message your hospital...':'How can we help you?';
    const locked = channel === 'hospital' && !eligible.length;
    $('.chat-hospital-gate').hidden = !locked;
    $('.chat-conversation').hidden = locked;
    $('.chat-hospital-select').hidden = channel !== 'hospital' || locked;
    if (customers) customers.closest('label').hidden = channel !== 'support';
    $('[data-chat-lock]').hidden = eligible.length > 0;
    $$('[data-chat-channel]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.chatChannel === channel)));
    updateComposer();
    return locked;
}
function renderMessages(messages, forceScroll = false) {
    const list = $('.chat-messages'), signature = JSON.stringify(messages);
    if (signature === renderedSignature) return;
    const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
    const previousScroll = list.scrollTop;
    renderedMessages = messages;
    renderedSignature = signature;
    list.replaceChildren();
    if (!messages.length) {
        const note = document.createElement('p');
        note.className = 'chat-empty';
        note.textContent = staff && channel === 'support' && !customers?.value
            ? 'Select a customer conversation above. Customers appear after sending their first message.'
            : 'Start a conversation. Your messages will appear here.';
        list.append(note);
    }
    for (const message of messages) {
        const own = staff && channel === 'support' ? message.sender_type === 'support' : message.sender_type === 'customer';
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble ' + (own ? 'from-customer' : 'from-team');
        const text = document.createElement('p');
        text.textContent = message.body;
        const stamp = document.createElement('small');
        stamp.textContent = chatSenderLabel({channel,senderType:message.sender_type,own,hospitalName:eligible.find(item=>String(item.id)===select.value)?.hospital_name})
            + ' - ' + new Date(message.sent_at).toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
        bubble.append(text, stamp);
        attachMessageTranslation(bubble,message);
        list.append(bubble);
    }
    list.scrollTop = forceScroll || nearBottom ? list.scrollHeight : previousScroll;
}
function options(element, items, placeholder) {
    const previous = element.value;
    element.replaceChildren();
    if (placeholder) {
        const option = document.createElement('option');
        option.value = ''; option.textContent = placeholder; element.append(option);
    }
    items.forEach(item => {
        const option = document.createElement('option');
        option.value = String(item.id); option.textContent = item.label; element.append(option);
    });
    if (items.some(item => String(item.id) === previous)) element.value = previous;
}
async function load() {
    if (!authenticated || sessionBlocked || panel.hidden || gate()) return;
    pendingLoad?.abort();
    const controller = new AbortController();
    pendingLoad = controller;
    const version = ++loadVersion;
    const query = new URLSearchParams({channel});
    if (channel === 'hospital') query.set('treatment_request_id', select.value);
    if (staff && channel === 'support' && customers?.value) query.set('customer_id', customers.value);
    try {
        const response = await fetch(widget.dataset.endpoint + '?' + query, {
            headers:{Accept:'application/json', 'X-Chat-User-Id':widget.dataset.userId},
            credentials:'same-origin', cache:'no-store', signal:controller.signal
        });
        if (version !== loadVersion) return;
        if (response.status === 401 || response.status === 409) {
            blockSession(response.status === 401 ? 'Your session expired. Please sign in again.' : 'The signed-in account changed in another tab. Open this page again.');
            return;
        }
        if (!response.ok) {
            if (response.status === 403 || response.status === 404) { eligible = []; gate(); }
            throw new Error('Conversation could not be loaded. Please try again.');
        }
        const data = await response.json();
        if (version !== loadVersion) return;
        if (String(data.user_id) !== widget.dataset.userId) {
            blockSession('The signed-in account changed. Open this page again.'); return;
        }
        conversationWritable=data.can_send===true;
        eligible = data.eligible_requests;
        options(select, eligible);
        if (customers) options(customers, data.conversations || [], 'Select a customer conversation');
        gate();
        renderMessages(staff && channel === 'support' && !customers?.value ? [] : data.messages);
    } catch (error) {
        if (error.name !== 'AbortError' && version === loadVersion) feedback(error.message);
    }
}
function resetConversation() {
    conversationWritable=false;
    loadVersion++; pendingLoad?.abort();
    renderedMessages = []; renderedSignature = null;
    $('.chat-messages').replaceChildren();
    feedback(''); if (!gate()) void load();
}
function choose(value) { channel = value; resetConversation(); }
const polling = createChatPolling({load, isActive:()=>authenticated && !sessionBlocked && !panel.hidden && !document.hidden});
launcher.addEventListener('click', () => {
    if (!panel.hidden) { close(); return; }
    panel.hidden = false;
    launcher.setAttribute('aria-expanded', 'true'); launcher.setAttribute('aria-label', 'Close chat');
    polling.start(); $('[data-chat-close]').focus();
});
document.addEventListener('visibilitychange', () => {
    if (document.hidden) polling.stop(); else if (!panel.hidden) polling.start();
});
function close() {
    panel.hidden = true; loadVersion++;
    launcher.setAttribute('aria-expanded', 'false'); launcher.setAttribute('aria-label', 'Open chat');
    pendingLoad?.abort(); polling.stop(); launcher.focus();
}
$('[data-chat-close]').onclick = close;
document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) close(); });
$$('[data-chat-channel]').forEach(button => button.onclick = () => choose(button.dataset.chatChannel));
$('[data-chat-support]').onclick = () => choose('support');
select.addEventListener('change', resetConversation);
customers?.addEventListener('change', resetConversation);
$('.chat-compose')?.addEventListener('submit', async event => {
    event.preventDefault();
    if (sending || sessionBlocked || (staff && channel === 'support' && !customers?.value)) return;
    const textarea = $('#chat-message'), body = textarea.value.trim();
    if (!body) return;
    const target = context(), targetChannel = channel, requestId = select.value, customerId = customers?.value;
    sending = true; updateComposer(); feedback('Sending...');
    try {
        const session = await refreshChatSession({sessionEndpoint:widget.dataset.sessionEndpoint, userId:widget.dataset.userId});
        csrf.content = session.csrf_token;
        const response = await postChatMessage({
            endpoint:widget.dataset.endpoint, sessionEndpoint:widget.dataset.sessionEndpoint,
            payload:{channel:targetChannel, body, treatment_request_id:targetChannel === 'hospital' ? Number(requestId) : null,
                ...(staff && targetChannel === 'support' && customerId ? {customer_id:Number(customerId)} : {})},
            token:csrf.content, userId:widget.dataset.userId, onToken:token => { csrf.content = token; }
        });
        if (response.status === 409) { blockSession('The signed-in account changed. Open this page again.'); return; }
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Message could not be sent. Please try again.');
        if (context() === target) {
            loadVersion++; pendingLoad?.abort();
            if (textarea.value.trim() === body) textarea.value = '';
            renderMessages([...renderedMessages.filter(message => message.id !== result.message.id), result.message].slice(-50), true);
            feedback('Message saved.'); await load();
        }
    } catch (error) {
        if (error.sessionInvalid || /session expired|account changed/.test(error.message)) blockSession(error.message);
        else feedback(error.message);
    } finally { sending = false; updateComposer(); }
});
gate();
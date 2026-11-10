// Drafts contain only the explicitly allowed UI fields. Personal details and
// File objects stay in memory; never serialize a whole form or journey here.
export const GUEST_DRAFT_KEY = 'aesthetic-guest-form-v1';
export const DRAFT_LIFETIME = 24 * 60 * 60 * 1000;
const safeFields = new Set(['procedures', 'hospitals', 'preferred_surgery_month', 'preferred_surgery_year', 'step', 'wait_for_quotes']);
function safeValues(values) {
    const safe = {};
    for (const [key, value] of Object.entries(values || {})) {
        if (!safeFields.has(key)) continue;
        if (['procedures', 'hospitals'].includes(key)) {
            if (Array.isArray(value)) safe[key] = value.filter(v => typeof v === 'string' && v.length <= 100).slice(0, 50);
        } else if (typeof value === 'string' && value.length <= 80) safe[key] = value;
    }
    return safe;
}
export class GuestFormState {
    constructor({storage, key = GUEST_DRAFT_KEY, now = Date.now, delay = 250} = {}) {
        this.storage = storage; this.key = key; this.now = now; this.delay = delay;
        this.values = {}; this.files = []; this.timer = null; this.expiresAt = 0; this.restored = false;
        try {
            const record = JSON.parse(storage?.getItem(key) || 'null');
            if (record?.version === 1 && Number.isFinite(record.expiresAt) && record.expiresAt > now() && record.expiresAt <= now() + DRAFT_LIFETIME) {
                this.values = safeValues(record.values); this.expiresAt = record.expiresAt;
                this.restored = Object.keys(this.values).length > 0;
            } else if (record) storage?.removeItem(key);
        } catch { /* Storage can be blocked or contain an invalid draft. */ }
    }
    expire() {
        if (this.expiresAt && this.expiresAt <= this.now()) { this.clear(); return true; }
        return false;
    }
    update(values) {
        this.expire(); Object.assign(this.values, values);
        this.expiresAt = this.now() + DRAFT_LIFETIME;
        clearTimeout(this.timer); this.timer = setTimeout(() => this.flush(), this.delay);
    }
    capture(form) {
        if (!form?.elements) return;
        const values = {};
        for (const field of form.elements) {
            if (!field.name || ['file', 'password', 'submit', 'button'].includes(field.type)) continue;
            if (field.name.endsWith('[]')) {
                const key = field.name.slice(0, -2); values[key] ??= [];
                if (field.checked) values[key].push(field.value);
            } else values[field.name] = field.type === 'checkbox' ? (field.checked ? field.value : '') : field.value;
        }
        this.update(values);
    }
    apply(form) {
        this.expire();
        if (!form?.elements) return;
        for (const field of form.elements) {
            const key = field.name?.replace(/\[\]$/, '');
            if (!Object.hasOwn(this.values, key) || ['file', 'password'].includes(field.type)) continue;
            const value = this.values[key];
            if (field.type === 'checkbox' || field.type === 'radio') field.checked = Array.isArray(value) ? value.includes(field.value) : value === field.value;
            else if (typeof value === 'string') field.value = value;
        }
    }
    flush() {
        clearTimeout(this.timer); this.timer = null;
        if (this.expire() || !this.expiresAt) return;
        try { this.storage?.setItem(this.key, JSON.stringify({version: 1, expiresAt: this.expiresAt, values: safeValues(this.values)})); }
        catch { /* In-memory navigation still works when storage is unavailable. */ }
    }
    clear() {
        clearTimeout(this.timer); this.timer = null;
        this.values = {}; this.files = []; this.expiresAt = 0; this.restored = false;
        try { this.storage?.removeItem(this.key); } catch { /* Storage may be blocked. */ }
    }
}

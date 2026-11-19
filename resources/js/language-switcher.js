import {translateText} from './i18n.js';

export function languageUrl(href, locale) {
    const url = new URL(href);
    // Query-string changes invalidate Laravel's private signed access links.
    if (url.searchParams.has('signature')) return url.href;
    url.searchParams.set('lang', locale);
    return url.href;
}

export function initializeLanguageSwitcher(form) {
    const select = form.querySelector('select');
    const feedback = form.querySelector('[role=alert]');
    const currentLocale = document.documentElement.lang;
    let pending = false;
    const switchLanguage = async event => {
        event.preventDefault();
        if (pending || select.value === currentLocale) return;
        pending = true;
        const locale = select.value;
        const data = new FormData(form);
        select.disabled = true;
        feedback.hidden = true;
        form.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: {Accept: 'application/json'},
            });
            if (!response.ok) throw new Error('Language could not be changed. Please try again.');
            // Reload the same page so Blade, validation, and dynamically rendered
            // interfaces all use the same locale. Preserve filters and hash routes.
            location.assign(languageUrl(location.href, locale));
        } catch {
            select.value = currentLocale;
            select.disabled = false;
            pending = false;
            form.removeAttribute('aria-busy');
            feedback.textContent = translateText('Language could not be changed. Please try again.', currentLocale);
            feedback.hidden = false;
        }
    };
    select.addEventListener('change', switchLanguage);
    form.addEventListener('submit', switchLanguage);
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-language-switcher]').forEach(initializeLanguageSwitcher);
}

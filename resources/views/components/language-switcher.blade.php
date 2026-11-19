<form method="POST" action="{{ route('website.language') }}" class="customer-language" data-language-switcher data-no-translate>
    @csrf
    <label for="customer-language" class="customer-language-label">{{ __('Language') }}</label>
    <select id="customer-language" name="language" aria-label="{{ __('Language') }}">
        <option value="en" lang="en" @selected(app()->getLocale()==='en')>English</option>
        <option value="th" lang="th" @selected(app()->getLocale()==='th')>ไทย</option>
        <option value="ko" lang="ko" @selected(app()->getLocale()==='ko')>한국어</option>
    </select>
    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg>
    <noscript><button type="submit">{{ __('Apply language') }}</button></noscript>
    <span class="customer-language-error" role="alert" hidden></span>
</form>

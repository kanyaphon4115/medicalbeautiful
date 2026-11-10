<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## AESTHETIC / Medicalbeautiful

English aesthetic marketplace homepage and real email/password authentication, built with Laravel Blade, Tailwind CSS v4, and JavaScript.

### Local setup

1. Start Apache and MySQL in XAMPP.
2. Configure local database access in .env. The database is named medicalbeautiful; the checked-in .env.example uses the standard local XAMPP defaults. Use separate credentials when deploying elsewhere.
3. Create the empty medicalbeautiful database if setting up on another computer, then run:

```sh
php artisan migrate
npm install
npm run build
```

Open http://localhost/medicalbeautiful/public/ in XAMPP, or run php artisan serve and open its URL. On Windows PowerShell, use npm.cmd if script execution policy prevents npm.

### Authentication

- GET /login and /register: guest-only views.
- POST /register: validates name, normalized unique email, confirmed password, and consent; saves a hashed password and signs in.
- POST /login: authenticates using Laravel sessions, with failed-attempt rate limiting.
- POST /logout: signs out and invalidates the session.
- Standard web middleware provides CSRF protection. Session data is stored in MySQL.
- Passwords and password confirmation are never repopulated in failed forms. Passwords are limited to 72 bytes for bcrypt.
- Google Sign-In uses Laravel Socialite and requires the Google OAuth credentials described below. Password recovery remains a preview; no email delivery is configured.

The existing users, sessions, password_reset_tokens, cache, and queue migrations are reused. No existing SQLite data is deleted. Homepage hospitals, reviews, quotations, favorites, and booking content remain demonstrations.

### Source and checks

- Auth controller: app/Http/Controllers/Auth/AuthController.php
- Auth views and reusable components: resources/views/auth/ and resources/views/components/auth/
- Main styles and interactions: resources/css/app.css and resources/js/app.js
- Local image sources: public/images/SOURCES.md

Run php artisan test for the authentication and homepage checks. Tests use an isolated, in-memory SQLite database and do not change the real MySQL database. Run npm run build to compile assets.

### Google account sign-in setup

The Continue with Google button now uses GET /auth/google and the session-protected callback at /auth/google/callback. New Google identities create users with verified email addresses. Returning users are matched by the unique google_id. Existing password accounts are not automatically linked by matching email; sign in with the original password for those accounts. Provider access/refresh tokens are not stored.

1. In Google Cloud / Google Auth Platform, configure application branding and audience (add test users if the app is in testing).
2. Create an OAuth client with application type Web application.
3. Add this exact Authorized redirect URI for local XAMPP:

   http://localhost/medicalbeautiful/public/auth/google/callback

4. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your local .env, and keep GOOGLE_REDIRECT_URI equal to the registered URI. Never put the secret in frontend code or commit it.
5. Run php artisan config:clear, then visit /login and click Continue with Google.

For php artisan serve, register its callback URL separately (for example http://127.0.0.1:8000/auth/google/callback) and change GOOGLE_REDIRECT_URI to match. Deployment requires the real HTTPS callback URI to be registered in Google as well.

Without configured credentials, Google sign-in redirects back to the login page with an English setup message. Automated OAuth tests mock Google identity responses; completing a live Google sign-in requires your own configured Google Cloud OAuth client.

Official setup reference: https://developers.google.com/identity/protocols/oauth2/web-server

### Saved treatment requests and private photos

My Requests now requires sign-in and lists only the current account's saved requests. POST /requests validates and saves the form; photos use the request_photos private filesystem disk at storage/app/request-photos. No public storage symlink is used for these images. Authenticated GET /request-photos/{photo} checks ownership before serving a file.

Tables: treatment_requests (contact details, selected procedure, encrypted goals/medical information, preferred date, consent record, status) and request_photos (request relationship, random storage path, original filename, MIME type, size). Files are stored on disk; MySQL stores their metadata. Keep the application key and private photo directory with database backups so encrypted records remain readable.

Limits: five JPEG/PNG/WebP images, 5 MB each. Local XAMPP upload limits are configured in public/.htaccess (5 MB per file, 30 MB request). Other hosting environments must configure equivalent PHP upload/post limits. Server validation remains authoritative. New requests have pending status, zero contacted hospitals, and no fabricated quotations. No hospital delivery or notifications are implemented. Existing mock request data is no longer displayed.

### Customer Support and hospital chat

The floating widget saves encrypted customer messages in chat_messages. Read/send routes require authentication, enforce ownership, and rate-limit sends. Customer Support is available without a treatment booking. Hospital chat requires all conditions on the same non-cancelled request: a selected procedure, selected_hospital_name, hospital_approved_at, and payment_confirmed_at. Customer form submissions cannot populate these approval/payment fields. No payment, approval, or staff-reply dashboard is implemented; these states must be set by a future trusted operational workflow or verified payment integration. No fake support responses or automatic hospital messages are generated. The widget accurately states that live staff replies are not connected.

### Account settings

The Account Settings modal persists notification preferences for requests, quotations, and messages plus the website language (English/Thai). Notification delivery is not yet integrated and notification controls are no longer shown. Privacy & Security displays the current account's sign-in sessions and can revoke other sessions. Connected Account displays the Google connection without provider identifiers or secrets; linking/unlinking is not added. Delete Account records account_deletion_requested_at after typing DELETE, and the request can be cancelled. It does not delete users, photos, requests, or chat history automatically. Operational review and retention rules must be implemented separately. These fields cannot be changed through ordinary user registration or mass assignment.

### Website language

Account Settings > Language changes the website interface between English and Thai. Saving reloads the page, stores the preference on the user and in the session/cookie, and sets the HTML language. Local translation catalogs cover the main navigation, homepage, authentication, requests, chat controls, and account settings, including dynamic dialogs and counters. User messages, medical notes, customer names, uploaded photos, typed input values, and form submission codes are not translated. No external translation service receives personal data. Unknown interface copy falls back to English. Run node --test tests/js/website-language.test.mjs for catalog and dynamic-label checks.

### Laravel Localization

Website translations now use native Laravel JSON catalogs: lang/en.json, lang/th.json, and lang/ko.json. Blade text and translated attributes call __() so main page text is rendered in the saved locale before JavaScript runs. SetWebsiteLocale selects the user/session/cookie locale. JavaScript dialogs use the same JSON catalogs through small bridge modules, with parameterized dynamic labels handled client-side. Edit lang/*.json to maintain translations; do not duplicate translations in JavaScript. Procedure IDs, form values, customer messages, medical notes, and uploaded photo content remain unchanged. On-demand chat message translation remains a separate on-device feature.

### Saved profile information

My Profile now saves Full Name, an optional international phone number, Country / Region, and Preferred Language using authenticated PATCH /account/profile with CSRF protection. The users table includes nullable phone_number and country_code; name and preferred_language reuse existing columns. Email, Google identity, and role are not writable through the endpoint. Phone numbers are normalized to international +country-code format and validated on the server. Cancel discards only unsaved edits. Profile language updates persist across navigation; a reload action is shown to apply the new language across the currently open page. Google avatar fallback behavior and authentication remain unchanged.

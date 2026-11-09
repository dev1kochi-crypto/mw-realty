# Environment & Setup

What a MW Realty test environment runs on, what must be running in the background, which settings and sandbox accounts it needs, and how the application behaves when a service is missing.

**Scope:** QA / UAT environments and developer machines. Covers supported browsers and devices, the server stack, required background processes, configuration keys (names only, never values), third-party sandbox accounts, time zone, currency and languages, and environment-specific behaviour testers should expect. Sources: `composer.json`, `package.json`, `vite.config.js`, `.env.example`, `bootstrap/app.php` and `config/*.php`. Secret values are never written in this document; the project lead shares them separately with the people who set up the environment.

## 1. Browsers and devices

The project does not define a browserslist or a Vite build target, so the build uses Vite 7's default target ("baseline widely available": roughly Chrome and Edge 107+, Firefox 104+, Safari 16+). Older browsers are not supported.

| Platform | Browsers to test | Priority |
| --- | --- | --- |
| Desktop (Windows / macOS) | Latest Chrome, latest Edge, latest Firefox, latest Safari (macOS) | Chrome every cycle; at least one other per cycle; all before release |
| iPhone / iPad | Safari on the latest two iOS versions | Every release |
| Android phone / tablet | Chrome on a recent Android version | Every release |

| Width (px) | Represents | Check |
| --- | --- | --- |
| 375 | Phone | Menus open, tables scroll inside their box instead of overflowing, forms and pop-ups fit, the AI chat widget and compare bar do not cover buttons |
| 768 | Tablet | Sidebar and filter panels collapse correctly |
| 1280 | Laptop | Default layout for Admin and CRM |
| 1920 | Large desktop | No stretched or misaligned layouts |

- Arabic must be checked: switching the website to Arabic turns the layout right-to-left. Check at least the home page, property search, property details (including the new insight tabs), compare, sign-up / log in, the customer profile and the AI chat (Arabic, Urdu and Persian bubbles are right-aligned).
- Voice input and read-aloud in the AI chat depend on browser support; a missing microphone button on some browsers is expected.
- The Admin (`/admin`) is a server-rendered Bootstrap 5 interface; the website and CRM are Vue single-page apps. Hard-refresh (or clear the cache) after a new build if a screen looks out of date.

## 2. Server stack

| Layer | Technology | Version in the project |
| --- | --- | --- |
| Language | PHP | ^8.5 (Composer platform set to 8.5.0) |
| Framework | Laravel | ^13.0 |
| API authentication | Laravel Sanctum | ^4.3 (Bearer tokens for the mobile apps) |
| Social sign-in | Laravel Socialite | ^5.31 (Google, customers only) |
| Admin / CMS | `mightywarnerskochi/cms` package, Blade, Bootstrap 5, roles via spatie/laravel-permission | dev-main |
| Payments | Stripe PHP SDK | ^21.3 |
| Media | Cloudinary PHP (optional; local storage otherwise) | ^3.1 |
| Other backend | laravel-dompdf (invoices), Laravel Excel (imports / exports), libphonenumber-lite (phones), HTMLPurifier (safe rich text), bacon-qr-code (2FA QR) | see `composer.json` |
| Front end | Vue 3 and Vue Router 4 (website and CRM), axios, Tailwind CSS 4, Leaflet / MapLibre GL maps, GSAP, intl-tel-input | Vue ^3.5.42, Vue Router ^4.6.4 |
| Build | Vite ^7.0.7 with laravel-vite-plugin ^2.0.0 and @vitejs/plugin-vue ^6.0.8; entry points `resources/css/app.css`, `resources/js/app.js`, `resources/js/crm/main.js` | Node version not pinned; Vite 7 needs Node 20.19+ or 22.12+ |
| Database | MySQL (as in `.env.example`, database `mw_realty`); SQLite is the framework fallback | Chosen per environment |
| Queue | Laravel database queue (`QUEUE_CONNECTION=database`), plus a separate `imports` connection and queue for long imports | |
| Cache and sessions | Database (`CACHE_STORE=database`, `SESSION_DRIVER=database`, lifetime 120 minutes) | |
| Mail | `MAIL_MAILER=log` by default (emails written to the log); SMTP for a real test inbox | |
| API docs (developers) | Scribe ^5.11, static output in `public/docs/_scribe` | |

## 3. Background processes

| Process | How it runs | What it does | What breaks without it |
| --- | --- | --- | --- |
| Scheduler | Cron entry `* * * * * php artisan schedule:run` (from the project folder), or `php artisan schedule:work` on a developer machine | Every minute it also starts the queue workers below, and runs all timed jobs (table in 3.1) | Nothing timed happens: no queued emails, no imports, Premium never starts or ends, ads never settle, permits never expire, credits never expire, no renewal reminders |
| Default queue worker | Started by the scheduler every minute (`queue:work --stop-when-empty --tries=3 --max-time=50`); a permanent `php artisan queue:work` may run instead | Sends queued emails and notifications, Facebook lead imports, photo fingerprinting for Listing Review | Emails and some bell notifications never arrive; Listing Review finds no photo matches (unit-number matches still work); Facebook leads do not arrive |
| Imports queue worker | Started by the scheduler every minute (`queue:work imports --queue=imports --tries=1 --timeout=3600 --max-time=50`) | Lead imports, property imports (Excel / CSV), Property Finder syncs | Imports stay at "Waiting for the import worker"; after 15 minutes without progress they are marked failed |
| Public storage link | One-time `php artisan storage:link` (creates `public/storage`) | Serves uploaded files stored locally (photos, logos, documents, banners) when Cloudinary is not used | Uploaded images show as broken; downloads fail |
| Front-end build | `npm run build` after each deployment (or `npm run dev` locally) | Builds the website and CRM assets into `public/build` | Website and CRM show a blank page or old screens |

> Testers: allow 1-2 minutes before reporting a missing email or a stuck import, and say in the report whether the scheduler and workers are known to be running on the environment.

### 3.1 Scheduled jobs

| Job | When | Visible effect |
| --- | --- | --- |
| `queue:work` (default and imports) | Every minute | Emails, notifications, imports processed |
| `properties:expire-permits` | Daily 00:05 Dubai time | Listings with an expired permit go off the website; reminder 7 days before |
| `properties:expire-featured` | Every 15 minutes | Scheduled Premium starts; ended Premium is removed |
| `ads:settle` | Every 15 minutes | Unreviewed client ads past their end are closed and refunded |
| Newsletter campaign sender | Every 15 minutes | Due newsletter content is sent |
| `credits:expire` | Hourly | "Expired credits" lines in credit history |
| `currencies:sync-rates` | Hourly, only when `CURRENCY_RATES_AUTO_SYNC=true` | Currency rates updated from the feed |
| `visitors:prune` | Daily 03:30 | Anonymous visitor activity older than 30 days deleted |
| `market-demand:aggregate` | Hourly at :10 | Website activity rolled into anonymous demand counts (Buyer Demand, CRM Market Demand) |
| `facebook:check-page-tokens` | Daily 08:00 | Broken Facebook Pages flagged; reconnect email |
| `portal:remind-subscription-renewals` | Daily 09:00 Dubai time | Email and bell the day before an auto-renewal |
| `portal:notify-expiring-documents` | Daily | Email when a passport, trade licence or TRN expiry date has passed |
| `landing-pages:cleanup-temp-images` | Daily | Removes unused temporary landing-page images |

## 4. Configuration a QA environment needs

Values live in the environment's `.env` file, which is never committed and never shared in documents. The table lists key names only.

### 4.1 Core application

| Key | Purpose | Required |
| --- | --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Name, environment type, encryption key, error detail, base URL used in links and emails. Use `APP_ENV` other than `production` on QA and `APP_DEBUG=false` for realistic error pages. | Yes |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | Default language (`en`) | Yes |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database (MySQL) | Yes |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_DOMAIN` | Sessions (database, 120 minutes) | Yes |
| `QUEUE_CONNECTION` | Queue driver (database) | Yes |
| `CACHE_STORE` | Cache (database) | Yes |
| `FILESYSTEM_DISK` | Local file storage disk | Yes |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Outgoing email. `log` writes emails to `storage/logs/laravel.log`; SMTP to a test inbox is recommended for QA | Yes |
| `CMS_ADMIN_EMAIL`, `CMS_ADMIN_NAME` | Admin notification address and name used by the CMS | Recommended |
| `LOG_CHANNEL`, `LOG_LEVEL` | Logging | Yes |

### 4.2 Security, sign-in and approval rules

| Key | Purpose | Required |
| --- | --- | --- |
| `OTP_ENABLED`, `OTP_STATIC_CODE` | `true` emails real sign-up / email-change codes; `false` sends none and accepts the fixed test code (also accepted at the new-device step outside production) | Optional (default true) |
| `TWO_FACTOR_REQUIRED` (older name `TWO_FACTOR_REQUIRED_FOR_AGENCIES`) | `true` (default): every agency and agent must set up 2FA, no Skip. `false`: optional unless the agency requires it | Optional |
| `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`, `RECAPTCHA_MIN_SCORE` | Google reCAPTCHA v3 on public forms; blank = forms work unprotected | Optional |
| `LISTING_SUPERADMIN_APPROVAL` | `true`: every listing needs Super Admin approval on Listing Permits. `false` (default): a permit verified online goes live by itself; DTCM and None always need approval | Optional |
| `LISTING_REVIEW_MINUTES_PER_CHECK` | Minutes counted per screened listing in the "Time saved" measure (default 10) | Optional |
| `HOUSE_LICENSE_NAME`, `HOUSE_ORN`, `HOUSE_ADREC_LICENSE` | MW Realty's own licence details shown on house listings | Optional |

### 4.3 Third-party services (use sandbox / test accounts only)

| Service | Keys | Sandbox to request | If not configured |
| --- | --- | --- | --- |
| Stripe (plans, credit top-ups) | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CURRENCY` (aed) | Stripe account in test mode with test API keys and a webhook to `/api/stripe/webhook` | Choosing a paid plan becomes an upgrade request approved in Admin; card top-ups unavailable |
| Mail | `MAIL_*` keys above | Mailpit, Mailtrap or a shared test mailbox | `log` mailer: emails only in the application log |
| Google sign-in (customers) | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` (`https://mw.realty.mightywarnersdemo.com/customer/auth/google/callback`) | Google Cloud OAuth client for the QA domain | "Continue with Google" button hidden |
| Google Translate (auto-translate of bios and listings) | `GOOGLE_TRANSLATE_API_KEY`, `TRANSLATE_DRIVER` (google or mymemory), `MYMEMORY_EMAIL` | Google Cloud project with Cloud Translation API, or MyMemory for testing (small daily quota) | Translate options disabled ("Auto-translate isn't set up on this site yet") |
| AI chat, compare summary, Write with AI, import AI descriptions | `CHATBOT_PROVIDER` (groq or gemini), `GROQ_API_KEY`, `GROQ_MODEL`, `GEMINI_API_KEY`, `GEMINI_MODEL`, `CHATBOT_NAME` (default Noor) | A Groq or Google AI Studio key for the QA project | Fixed-text fallbacks (section 6) |
| Facebook Lead Ads | `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, `FACEBOOK_WEBHOOK_VERIFY_TOKEN`, `FACEBOOK_GRAPH_VERSION` | A Meta developer app in development mode with test Pages; OAuth redirect and webhook URLs for the QA domain | Facebook connect fails; use the Facebook CSV import instead |
| Property Finder | `PROPERTY_FINDER_API_URL` (each account enters its own API key and secret in the CRM) | Test Property Finder Enterprise credentials from the project lead | Connection test fails; no sync |
| DLD Trakheesi (Dubai permits) | `DLD_PERMIT_API_ENABLED`, `DLD_PERMIT_API_URL`, `DLD_PERMIT_API_CLIENT_ID`, `DLD_PERMIT_API_CLIENT_SECRET`, `DLD_PERMIT_API_TIMEOUT` | DLD sandbox access (not yet available; default disabled) | Validate button hidden; RERA listings wait for Super Admin approval |
| ADREC (Abu Dhabi and Al Ain permits) | `ADREC_PERMIT_API_ENABLED`, `ADREC_PERMIT_API_URL`, `ADREC_PERMIT_API_CLIENT_ID`, `ADREC_PERMIT_API_CLIENT_SECRET`, `ADREC_PERMIT_API_TIMEOUT` | ADREC sandbox access (default disabled) | Same as DLD for ADREC listings |
| Cloudinary (media) | `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`, `CLOUDINARY_FOLDER` | A separate Cloudinary folder or account for QA | Uploads stored locally (needs `storage:link`) |
| Currency rates feed | `CURRENCY_RATES_AUTO_SYNC`, `CURRENCY_RATES_URL` | None (public feed) | Off by default: rates are whatever Admin typed |
| Maps | `MAP_STYLE_URL`, `MAP_ATTRIBUTION`, `MAP_FALLBACK_TILE_URL` | None (OpenFreeMap / OpenStreetMap defaults) | Defaults used |
| Market insights | `PROPERTY_INSIGHTS_MIN_SAMPLE` | None | Default 3 listings or deals per figure |
| AWS, Redis, Postmark, Resend, Slack | Standard Laravel keys | Not used by default | Not needed |

> Never point a QA environment at production Stripe keys, a production Facebook app, real DLD / ADREC credentials or the production database or mailbox.

## 5. Time zone, currency and languages

- Application time zone: UTC (`config/app.php`). Business schedules that matter to users run in Dubai time (Asia/Dubai, UTC+4): permit expiry at 00:05, renewal reminders at 09:00, and ad bookings, which start on the hour in Dubai time. Record times in bug reports in UAE time.
- Currency: every price is stored in AED. The website currency switcher converts with rates kept in Admin > General Settings > Currencies (units per 1 AED); Stripe charges in AED. Compare adds a 1.5% conversion margin row for non-AED currencies.
- Website languages: English and Arabic text files exist (`resources/lang/cms-static/en.json`, `ar.json`); Admin enables languages and edits translations. Arabic switches the layout to right-to-left.
- AI chat languages: English, Arabic, Hindi, Urdu, Persian, Russian, Simplified Chinese, French, German and Spanish, independent of the website language.

## 6. Environment-specific behaviour (expected, not bugs)

| Situation | Behaviour |
| --- | --- |
| No AI provider key | AI chat answers "The AI assistant isn't available yet - please use the enquiry form on any listing instead."; compare shows the rule-based "Quick summary"; Write with AI shows "AI writing isn't set up yet ..."; import's AI option disabled |
| AI provider error or rate limit | Chat error line "Sorry, I'm having trouble answering right now..."; compare falls back to the Quick summary |
| DLD / ADREC check disabled | No Validate button; RERA and ADREC listings wait on Listing Permits for Super Admin approval; Listing Review never raises "Ad differs from the permit record" |
| `LISTING_SUPERADMIN_APPROVAL=true` | Every listing waits for approval, even with a verified permit |
| Stripe not configured | Plan purchase becomes an upgrade request; credit top-up unavailable |
| Credits switched off in Admin | Buying refused; credits already held can still be spent |
| `OTP_ENABLED=false` | No code emails at sign-up; the fixed test code is accepted |
| `MAIL_MAILER=log` | No emails in any inbox; read them from the application log |
| Scheduler or workers stopped | Emails, imports, Premium start / end, ad settlement and expiries do not happen |
| No `storage:link` and no Cloudinary | Uploaded images broken |
| Few listings in a community | Market Insights shows "Not enough data yet"; Listing Review price checks need at least 5 similar listings |
| Currency auto-sync off (default) | Rates change only when Admin edits them |
| reCAPTCHA keys blank | Forms work without bot protection |
| reCAPTCHA keys set but PHP has no CA certificate file (common on local XAMPP) | Every protected form, including the /compare unlock, fails with "Verification failed — please try again."; the log shows "cURL error 60". Fix: set `curl.cainfo` and `openssl.cafile` in php.ini to a CA bundle (XAMPP: `apache/bin/curl-ca-bundle.crt`) and restart PHP |
| reCAPTCHA key's allowed domains do not include the test host | "Verification failed — please try again." on every protected form; add the host (e.g. `127.0.0.1`, `localhost`) in the reCAPTCHA admin console |
| Several testers on one IP address | Shared rate limits ("too many requests") are reached sooner |
| Cached configuration on the server | A changed `.env` value has no effect until a developer clears and rebuilds the config cache |

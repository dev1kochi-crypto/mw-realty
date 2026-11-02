# Test Data

Rules, ready-made sample values and boundary values for testing MW Realty without touching real people, companies or money.

**Scope:** All test environments (never production). Covers test-data principles, sample data sets for each business scenario (accounts, listings, leads, plans and coupons, ads, tickets, imports, compare and insights, AI chat), Stripe test-mode payment data and boundary values taken from the validation rules in the code. Every name, email, phone and permit number below is fictitious.

## 1. Principles

- Use only the test accounts listed in Application Access, Roles & Permissions, or accounts you create with a test mailbox you control. Never use a real buyer's, agent's or company's details.
- Start every record you create with "TEST" or "QA" (titles, names, references, coupon codes, ad names, ticket subjects) so it can be found and cleaned up.
- Emails: use the `example.com` domain or the shared test inbox, for example `qa.agency01@example.com`. Use a new email and phone whenever you need a fresh lead: the system merges enquiries with the same email or phone (the last 9 digits of the phone) into one lead.
- Phones: UAE format with country code +971 picked from the list and the number typed without it, 7-13 digits. Use the reserved-looking ranges in this document, for example +971 50 000 0001. Never use a real person's number.
- Money: all prices are entered and stored in AED. Other currencies are only a display conversion.
- Permits: use fictitious numbers that start with a test prefix (for example `QA-RERA-0001`). Never copy a real Trakheesi, DTCM or ADREC permit number from another company's advert. Each permit number may be used on one listing only.
- Licence numbers (ORN, BRN, TRN, trade licence): fictitious values such as `QA-ORN-1001`. Do not use real brokers' numbers.
- Documents and images: sample images and dummy PDFs only, within the size limits in section 8. No real Emirates IDs, passports or trade licences.
- Integrations: do not connect real Facebook Pages or real Property Finder accounts unless the project lead provides test ones.
- Payments: Stripe test mode and Stripe test cards only (section 7). If a real payment page appears, stop and report it.

## 2. Accounts

| Type | Name | Login email | Phone | Licence data | State to set up |
| --- | --- | --- | --- | --- | --- |
| Agency (paid) | Test Agency QA 01 | qa.agency01@example.com | +971 50 000 0001 | ORN QA-ORN-1001, trade licence QA-TL-1001, TRN 100000000000001 | KYC approved, Business plan |
| Agency (free) | Test Agency QA 02 | qa.agency02@example.com | +971 50 000 0002 | ORN QA-ORN-1002 | KYC approved, Free plan |
| Agency (pending) | Test Agency QA 03 | qa.agency03@example.com | +971 50 000 0003 | none yet | KYC draft or submitted |
| Independent agent | QA Agent Independent 01 | qa.agent.ind01@example.com | +971 52 000 0011 | BRN QA-BRN-2001 | KYC approved, Basic or Pro plan |
| Agency agent | QA Agent Member 01 | qa.agent.mem01@example.com | +971 55 000 0021 | BRN QA-BRN-2002 | Approved member of Test Agency QA 01 |
| Agency agent (second) | QA Agent Member 02 | qa.agent.mem02@example.com | +971 56 000 0022 | BRN QA-BRN-2003 | Member of Test Agency QA 01, for round robin |
| Customer | QA Customer 01 | qa.customer01@example.com | +971 58 000 0031 | not applicable | Email verified |
| Admin staff | QA Content Editor | qa.editor01@example.com | not applicable | not applicable | Role with blogs and banners permissions only |

Passwords: at least 8 characters, entered twice. Agree a pattern with the project lead; do not reuse a personal password. Credentials of shared accounts are shared separately.

KYC documents: use dummy files named after the field (`TEST-emirates-id.pdf`, `TEST-passport.jpg`, `TEST-trade-licence.pdf`, `TEST-rera-card.png`), each under 4 MB.

## 3. Listings

### 3.1 Residential and commercial set

| Ref (Your reference) | Title | Purpose | Type | Emirate / City / Community | Beds / Baths / Size sq.ft | Price AED | Permit type and number |
| --- | --- | --- | --- | --- | --- | --- | --- |
| QA-L-001 | TEST 2 Bed Apartment Dubai Marina | Sale | Apartment | Dubai / Dubai / Dubai Marina | 2 / 2 / 1,250 | 1,850,000 | RERA (DLD), QA-RERA-0001 |
| QA-L-002 | TEST Studio JVC | Rent (yearly) | Apartment | Dubai / Dubai / Jumeirah Village Circle | Studio (0) / 1 / 420 | 55,000 | RERA (DLD), QA-RERA-0002 |
| QA-L-003 | TEST Holiday Home Downtown | Rent (monthly) | Apartment | Dubai / Dubai / Downtown Dubai | 1 / 1 / 780 | 12,000 per month | DTCM, QA-DTCM-0001 |
| QA-L-004 | TEST Office DIFC | Rent (yearly) | Office | Dubai / Dubai / DIFC | not applicable / 2 / 1,500 | 240,000 | None (DIFC / JAFZA) |
| QA-L-005 | TEST 4 Bed Villa Yas Island | Sale | Villa | Abu Dhabi / Abu Dhabi / Yas Island | 4 / 5 / 4,200 | 6,200,000 | ADREC, QA-ADREC-0001 |
| QA-L-006 | TEST 3 Bed Townhouse Al Ain | Rent (yearly) | Townhouse | Abu Dhabi / Al Ain / Al Jimi | 3 / 3 / 2,400 | 90,000 | ADREC, QA-ADREC-0002 |
| QA-L-007 | TEST 1 Bed Apartment Al Majaz | Sale | Apartment | Sharjah / Sharjah / Al Majaz | 1 / 1 / 850 | 520,000 | No permit needed |
| QA-L-008 | TEST 2 Bed Apartment Al Nuaimiya | Rent (yearly) | Apartment | Ajman / Ajman / Al Nuaimiya | 2 / 2 / 1,100 | 38,000 | No permit needed |
| QA-L-009 | TEST Penthouse Al Hamra | Sale | Penthouse | Ras Al Khaimah / Ras Al Khaimah / Al Hamra Village | 3 / 4 / 2,900 | 3,400,000 | No permit needed |
| QA-L-010 | TEST Retail Shop Umm Al Quwain | Rent (yearly) | Retail shop | Umm Al Quwain / Umm Al Quwain / Al Salamah | not applicable / 1 / 600 | 45,000 | No permit needed |
| QA-L-011 | TEST Warehouse Fujairah | Sale | Warehouse | Fujairah / Fujairah / Fujairah Industrial Area | not applicable / 1 / 8,000 | 2,100,000 | No permit needed |
| QA-L-012 | TEST Sold 2 Bed Business Bay | Sale, then Mark as sold | Apartment | Dubai / Dubai / Business Bay | 2 / 2 / 1,180 | 1,600,000 (sold 1,550,000) | RERA (DLD), QA-RERA-0003 |
| QA-L-013 | TEST Rented Villa Arabian Ranches | Rent, then Mark as rented | Villa | Dubai / Dubai / Arabian Ranches | 4 / 4 / 3,600 | 260,000 | RERA (DLD), QA-RERA-0004 |
| QA-L-014 | TEST Off-plan 1 Bed Dubai Creek | Sale, Off-plan | Apartment | Dubai / Dubai / Dubai Creek Harbour | 1 / 1 / 700 | 1,150,000 | RERA (DLD), QA-RERA-0005 |

Coordinates for the map pin (approximate, community level): Dubai Marina 25.0805, 55.1403; JVC 25.0560, 55.2090; Downtown 25.1972, 55.2744; DIFC 25.2124, 55.2796; Yas Island 24.4886, 54.6088; Al Ain 24.2075, 55.7447; Al Majaz 25.3250, 55.3870.

### 3.2 Listing Review (duplicate and risk) data

| Purpose | How to prepare | Expected |
| --- | --- | --- |
| Same unit, another agency | Agency QA 01 and Agency QA 02 each save a Dubai Marina sale apartment with unit number `QA-1203` in the same location. | The older listing stays live; the newer is held in Listing Review. |
| Same unit, same agency | Agency QA 01 saves a second listing with unit `QA-1203`, same location and purpose. | Refused on save ("Unit QA-1203 in this location is already listed ..."). |
| Price far below | One listing priced under 60% of the median price per sq.ft of at least 5 similar live listings. | Risk signal, listing stays live with a flag. |
| Details don't add up | Title "TEST 2 Bed Villa in Dubai Marina" on an Apartment with 3 bedrooms in JVC. | Risk signal "Listing details don't add up". |
| Size too small | Villa with size 900 sq.ft (minimum 1,000). | Risk signal. |

## 4. Leads

### 4.1 One lead per source

Use a new email and phone per row (for example `qa.lead01@example.com` to `qa.lead12@example.com`, +971 50 100 0001 to +971 50 100 0012).

| Source (as stored) | How to create it | Lands with |
| --- | --- | --- |
| Website | Property details > Enquire About This Property > Send Enquiry | The listing's owner and agent |
| Book Viewing | Property details > Request Viewing | Listing owner |
| Brochure Download | Property details > download brochure (contact details first) | Listing owner |
| Floor Plan Download | Property details > download floor plan | Listing owner |
| Agent Profile / Agency Profile | `/agent-details/{slug}` or `/agency-details/{slug}` request form | That agent or agency |
| Custom Request | `/properties` > Custom Property Request | Super Admin pool (unassigned) |
| AI Chatbot | AI chat > "Want to keep chatting?" form | Website Leads pool |
| Landing Page | Form on a landing page `/{slug}` | Website Leads pool and Admin > Enquiries |
| Direct Lead | CRM > Leads > Add Lead without choosing a source | Signed-in account |
| Referral, Walk-in, Social Media, Property Portal | CRM > Add Lead choosing the source | Signed-in account |
| Import | CRM > Leads > Import > Direct import (Excel) | Signed-in account |
| Facebook Lead Ads (ad set name) | Leads > Import > Facebook leads sample CSV, or a test Facebook Page | Connected account |
| Direct Sale | Mark a listing as sold to a new buyer | Listing owner |

### 4.2 Stages, statuses and follow-ups

- Stages (shared defaults): New (default), Hot Buyer, Active Buyer, Active Seller, Negotiation, Connected, Closed (closed stage), Dropped (closed stage). Put at least one lead in each.
- Status: Active (new leads) and Inactive (lead menu > Mark as Inactive).
- Duplicate check: send a second enquiry with the same email in upper case, and one with the phone written as `00971 50 100 0001` or `050 100 0001`; both must merge into the existing lead.
- Follow-ups: one Call overdue (due yesterday), one WhatsApp due today, one Email next week, one completed with an outcome, one cancelled with a reason.

### 4.3 Lead import sample rows

| Name | Email | Phone | Stage | Source | Tags | Expected |
| --- | --- | --- | --- | --- | --- | --- |
| QA Import Lead 01 | qa.import01@example.com | +971501000101 | New | Referral | VIP | Imported |
| QA Import Lead 02 | (empty) | +971501000102 | Hot Buyer | Import | QA-NewTag | Imported; missing tag created |
| QA Import Lead 03 | (empty) | (empty) | New | Referral | | Rejected: email or phone required |
| QA Import Lead 04 | qa.import04@example.com | | NoSuchStage | Referral | | Rejected: unknown stage |

## 5. Plans, credits and coupons

| Item | Test values |
| --- | --- |
| Plan switches | Free to Pro (upgrade, charged now), Pro to Basic (downgrade at renewal, no refund), Cancel auto-renew (drops to Free at period end), monthly vs yearly price. |
| Upgrade request flow | Stripe not configured on the environment: Upgrade Now creates a request; approve it in Admin > Clients > Plan Upgrade Requests. |
| Coupon, percent, once | Code `QATEST10`, percent 10, duration once, all plans. |
| Coupon, fixed, repeating | Code `QATEST-50AED`, fixed 50, duration repeating 3 months, only Pro. |
| Coupon, forever, limited | Code `QA_FOREVER`, percent 100, forever, max uses 1, max uses per user 1. |
| Invalid coupon codes | `qa test` (space: refused, letters, numbers, - and _ only), a 41-character code, an expired code (expiry yesterday), a code limited to another plan. |
| Credits top-up | 1,000 credits (default), the minimum and maximum set in Admin > Credits, one below the minimum, one above the maximum. |
| Credits grant (Admin) | 1 credit, 1,000,000 credits, note of 120 characters, note of 121 characters. |
| Credits spend | Extra listing after the plan allowance is used; Premium for 7, 15 and 30 days; an ad beyond the plan's ads per month. |
| Expiry | Admin > Credits validity set to 1 day on a QA environment to see an "Expired credits" line next day. |

## 6. Ads, tickets, imports, compare, insights and chat

### 6.1 Advertisements

| Case | Values |
| --- | --- |
| Normal booking | Ad name "TEST Ad 01", banner image 2400 x 450 px JPG under 8 MB, phone image 800 x 400 px under 4 MB, link to own listing, start next full hour, length 3 hours. |
| Day booking | Length 2 days (only on a plan whose longest booking is at least 48 hours). |
| Too long | Length longer than the plan allows: refused. |
| Too far ahead | Start 61 days ahead: "Ads can be booked up to 60 days ahead." |
| Full slot | Book the same page and hour from accounts until it shows "Full". |
| Review | Approve one, reject one with a reason (max 500 characters), leave one unreviewed past its end (closed and refunded). |
| Per-language text | Small heading 30, title 60, short text 120 characters (and one character more for each). |

### 6.2 Support tickets

| Field | Valid | Invalid |
| --- | --- | --- |
| Issue type | Account & Profile, KYC / Verification, Property Listings, Leads & CRM, Plans & Subscription, Payments & Invoices, Agency & Agents, Technical Issue / Bug | empty |
| Priority | Low, Normal, High, Urgent | empty |
| Subject | "TEST ticket - cannot upload photo" (max 150) | 151 characters |
| Message | up to 5,000 characters | empty, 5,001 characters |
| Attachment | `TEST-screenshot.png`, `TEST-invoice.pdf` under 4 MB | `.docx`, a 5 MB image |

### 6.3 Property import (Excel / CSV)

Use the downloaded `listing-import-template.xlsx` or a CSV with these headings: Your reference, Title, Purpose, Property type, Price (AED), Community / area, City, Emirate, Bedrooms, Bathrooms, Size (sq.ft), Furnishing, Photo URLs, Permit number, Latitude, Longitude, Agent.

| Your reference | Purpose | Property type | Price (AED) | Community / area | City | Bedrooms | Other values | Expected status and remark |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QA-IMP-001 | Sale | Apartment | 1.25M | Dubai Marina | Dubai | 2 | Size 1,250; permit QA-RERA-1001; lat 25.0805, long 55.1403; 3 photo URLs | Needs details or Added ("ready - validate the permit to publish") |
| QA-IMP-002 | Rent | Studio | 95k | JVC | Dubai | Studio | Furnishing Semi | Needs details (studio read as Apartment, 0 bedrooms) |
| QA-IMP-003 | Lease | Villa | 260,000 AED | Arabian Ranches | Dubai | 4 | Rent frequency empty (Yearly) | Needs details |
| QA-IMP-004 | Sale | Office | 3,000,000 | Business Bay | Dubai | | From the Properties page | Lands in Commercial |
| QA-IMP-005 | (empty) | Apartment | 900000 | JLT | Dubai | 1 | | Skipped: "purpose (sale / rent) is empty" |
| QA-IMP-006 | Sale | Hut | abc | Al Barsha | Dubai | 2 | | Skipped: price "abc" isn't a number; property type "Hut" isn't one of ours |
| QA-IMP-007 | Sale | Apartment | 0 | Al Reem Island | | 1 | Emirate empty | Skipped (price 0) |
| QA-IMP-008 | Rent | Apartment | 60,000 | Unknown Place | | 1 | No city or emirate | Skipped: "emirate unknown - add an Emirate or City column" |
| QA-IMP-009 | Sale | Apartment | 1,250,000 | Dubai Marina | Dubai | 2 | Same permit as row 2 | Duplicate: "Same permit as row 2 of this file." |
| QA-IMP-001 | Sale | Apartment | 1,250,000 | Dubai Marina | Dubai | 2 | Same reference, second upload | Duplicate (same reference) |
| QA-IMP-010 | Sale | Apartment | 2,000,000 | Downtown Dubai | Dubai | 2 | Permit `QA RERA #1` | Added with remark: permit left out (letters, numbers, - and / only) |
| QA-IMP-011 | Sale | Townhouse | 4.2 million | Saadiyat Island | Abu Dhabi | 3 | Photo URL `ftp://example.com/x.jpg` | Photos not downloaded remark |
| QA-IMP-012 | Sale | Apartment | 1,000,000 | Al Majaz | Sharjah | 1 | Year built 1700, lat 0 long 0 | Year built and map pin left empty |

File-level cases: a `.pdf` file (refused), an 11 MB file (refused), a heading row only, a CSV separated by semicolons, a file with 2,001 listing rows (refused, max 2,000), a second upload while one import is running.

### 6.4 Compare and property insights

- Compare sets: 2 Dubai sale listings (minimum); 5 mixed listings (maximum); a 6th pick (refused); one sale plus one rent (price rows do not rank); a Golden Visa case at AED 2,000,000 and one at AED 1,999,999.
- Fee check: a Dubai sale at AED 1,000,000 must show buying fees AED 65,780 and total cash needed AED 1,065,780.
- Rent check: Dubai, AED 100,000 per year, unfurnished apartment, 1 cheque, no stated deposit: move-in AED 112,470, year AED 110,470.
- Market Insights: a community with at least 3 similar listings or deals (figures shown) and one with fewer than 3 ("Not enough data yet").
- Currencies: AED, USD, EUR, GBP, INR (home-country notes) and one other active currency.
- Shared link: `/compare?slugs=<slug1>,<slug2>,<slug-of-an-offline-listing>`.

### 6.5 AI chat prompts

| Language | Prompt | Checks |
| --- | --- | --- |
| English | 2 bedroom apartment for rent in Dubai Marina under 120,000 a year | Up to 6 cards with "N% match" and reasons |
| English | Villa for sale near a school in Arabian Ranches, budget 5 million | Budget and "Near: School" chips on the lead's insights |
| Arabic | أبحث عن شقة غرفتين للإيجار في دبي مارينا | Switch offer to Arabic; right-to-left bubbles |
| Hindi | मुझे दुबई में 2 बेडरूम का फ्लैट चाहिए | Switch offer to Hindi |
| Urdu | مجھے دبئی میں کرائے کے لیے فلیٹ چاہیے | Switch offer to Urdu |
| Persian | دنبال یک آپارتمان در دبی هستم | Switch offer to Persian |
| Russian | Ищу квартиру с двумя спальнями в Дубае | Switch offer to Russian |
| Chinese | 我想在迪拜买一套两居室公寓 | Switch offer to Chinese |
| French | Je cherche un appartement avec deux chambres à Dubaï | Switch offer to French |
| German | Ich suche eine Wohnung mit zwei Schlafzimmern in Dubai | Switch offer to German |
| Spanish | Busco un apartamento de dos dormitorios en Dubái | Switch offer to Spanish |
| Off-topic | What is the capital of France? | Polite redirect to property search |
| Legal question | Do I need a lawyer to buy off-plan? | General information only, recommends a professional |
| Too short | ok | No language offer (fewer than 4 letters) |
| Too long | 1,001 characters | Refused (max 1,000) |

Chat details form: name "QA Chat Lead 01", email `qa.chat01@example.com`, phone +971 50 200 0001.

## 7. Payment test data (Stripe test mode)

The application takes card payments through Stripe (plan checkout and credit top-ups, currency AED). Use these only when the project lead confirms the environment uses Stripe test keys.

| Card number | Result | Use for |
| --- | --- | --- |
| 4242 4242 4242 4242 | Succeeds | Plan purchase, upgrade, credit top-up |
| 4000 0000 0000 9995 | Declined (insufficient funds) | Error message, nothing charged, no plan change |
| 4000 0000 0000 0002 | Declined (generic) | Error message |
| 4000 0025 0000 3155 | Requires 3-D Secure authentication | Complete and fail the challenge |
| 5555 5555 5555 4444 | Succeeds (Mastercard) | Card brand display |

With every test card: any future expiry date (for example 12/34), any 3-digit CVC, any postcode, cardholder name "QA Tester" (max 60 characters). Never enter a real card. If Stripe is not configured on the environment, plan changes become upgrade requests and top-ups are unavailable.

## 8. Boundary values

| Field | Rule | Test at |
| --- | --- | --- |
| Listing title (each active language) | Required, max 255 | 255 ok, 256 refused, empty refused |
| Listing key features / description | Max 5,000 / 100,000 | 5,000 and 5,001 |
| Listing address / community, city | Address max 1,000; others max 255 | 1,000 and 1,001 |
| Web address (slug) | Letters, numbers, - and _; max 200 | 200 and 201, a space |
| Permit number | Max 64; letters, numbers, - and / only; unique across all listings | 64, 65, `QA#01`, a number already used |
| Permit QR image | Image, max 2 MB | 2 MB, 2.1 MB |
| Bedrooms, bathrooms | Whole numbers 0-1,000 (0 = Studio) | -1, 0, 1,000, 1,001 |
| Size (sq.ft) | Whole number, 0 or more | -1, 0 |
| Price, security deposit | Number 0 to 9,999,999,999,999.99 | 0, negative, 10,000,000,000,000 |
| Currency | Exactly 3 letters | AED, AE, AEDX |
| Cheques | 1-12 | 0, 1, 12, 13 |
| Year built | 1800-2200 | 1799, 1800, 2200, 2201 |
| Latitude / longitude | -90 to 90 / -180 to 180 | 90.1, -180.1 |
| Photos | Image, max 4 MB each, up to 30 per save | 4 MB, 4.1 MB, 30 and 31 photos |
| Brochure | PDF, JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX, CSV, PPT, PPTX; max 20 MB | 20 MB, 21 MB, `.exe` |
| Floor plan file / floor plan rows | Max 10 MB / max 50 rows, image max 4 MB | 10 MB, 51 rows |
| Available dates | Max 60 dates | 60 and 61 |
| Nearby places on a listing | Max 100 | 100 and 101 |
| SEO meta title / description | Max 255 / 500 | 255 / 500 and one more |
| Lead name | Required, max 255 | empty, 255, 256 |
| Lead email | Valid address with a real domain, max 255 | `name@host` refused |
| Phone (lead, profile, agent) | Country code from the list, 7-13 digits | 6, 7, 13, 14 digits; letters |
| Lead note / follow-up note | Max 5,000 / 2,000 | 5,000 and 5,001 / 2,000 and 2,001 |
| Lead or property import file | `.xlsx`, `.xls`, `.csv` (also `.tsv`, `.txt`), max 10 MB | 10 MB, 10.1 MB; property import max 2,000 rows |
| Profile photo / logo | JPG, PNG, WEBP, max 4 MB, at least 120 x 120 px | 119 x 119 px, 4.1 MB |
| KYC and agent documents | JPG, JPEG, PNG, PDF, max 4 MB each | 4 MB, 4.1 MB, `.docx` |
| Profile description (English, Arabic) | Max 2,000 each | 2,000 and 2,001 |
| Licence / ID numbers | Max 50 (other licence 100) | 50 and 51 |
| Sign-up password | At least 8 characters, entered twice | 7, 8, mismatch |
| Sign-up / email-change code | 4 digits, valid 10 minutes | wrong code, code after 10 minutes |
| New-device code / app code | 6 digits; 10 minutes / 30 seconds | 5 wrong codes in one attempt |
| Coupon code (Admin) | Max 40; A-Z, 0-9, - and _ | 40 and 41, a space |
| Coupon value | Percent 0.01-100; fixed 0.01-1,000,000 | 0, 100.01 |
| Coupon repeating months | 1-120 | 0, 121 |
| Credit price (Admin) | 0.01-100,000 AED | 0, 100,000.01 |
| Credit validity (Admin) | 1-3,650 days | 0, 3,651 |
| Credits grant (Admin) | 1-1,000,000; note max 120 | 0, 1,000,001 |
| Premium length | 7, 15 or 30 days when paid with credits | 8 days |
| Ad booking | Start on the hour, up to 60 days ahead; 1, 2, 3, 6, 12 hours or 1, 2, 3 days within the plan maximum | 61 days ahead |
| Ad banner / phone image | Max 8 MB / 4 MB | 8 MB and 8.1 MB |
| Ad reject reason | Max 500 | 500 and 501 |
| Ad placements (Admin) | At once 0-50; credits 0-1,000,000 | 51 |
| Ticket subject / message | Max 150 / 5,000 | 150 and 151 |
| Ticket attachment | JPG, PNG, PDF, max 4 MB | 4 MB, 4.1 MB |
| Compare | 2-5 listings; slugs max 255 each | 1, 5, 6 |
| AI chat message | Required, max 1,000 | 1,000 and 1,001 |
| Write with AI notes / draft | Max 2,000 / 20,000 | 2,000 and 2,001 |
| Rate limits (per IP) | Enquiry forms 20 / hour; sign-ups 10 / hour; login 5 / minute per email; codes 8 / minute and 30 / day; AI chat 8 / minute and 150 / day; compare summary 10 / minute and 100 / day; Write with AI 20 / minute | One over each limit gives "too many requests" (expected) |

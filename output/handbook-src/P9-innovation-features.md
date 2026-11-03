# Innovation Features: Test Guide

The seven new innovation features: the pages each one changed, how each feature works, and the test scenarios that prove it.

**Scope:** 1 AI property matching · 2 Authenticity and trust engine · 3 Investment intelligence · 4 Cross-border intelligence · 5 Conversational assistant · 6 Agency listing automation · 7 Demand and market analytics. **Who tests:** QA, using the Super Admin, Agency A, Agency B, Agent and website visitor test accounts (P4 Test Data). **Full rules:** guides 24 Listing Review, 25 Property Import & AI Copy and 26 Website: Compare, Insights & AI Chat.

## 0. Before you start

| Need | Why | Check |
|---|---|---|
| CHATBOT_PROVIDER and its API key set on the server | AI chat (1, 5), AI compare summary (4) and Write with AI (6) | The chat answers instead of "The AI assistant isn't available yet…" |
| Queue worker running (the scheduler starts it every minute) | Photo duplicate check (2) and spreadsheet import (6) | An import moves past "Starting import..." within about a minute |
| At least 5 live listings of the same type, purpose and community | Price-risk check (2) needs 5 similar listings; market figures (3) need 3 | Seed them first (P4 Test Data) |
| Two agency accounts, both KYC-approved, with plan listings left | Duplicate and authority checks (2), import (6) | Both can add a listing |
| 2 or more active currencies in Admin > General Settings > Currencies | Currency conversion (4) | The header currency switcher shows them |

Priority: **High** is a core flow, data loss or access problem · **Medium** · **Low** is cosmetic.

## 1. AI property matching engine

**What it does.** The buyer describes what they want in their own words in the website chat, for example: "A two-bedroom apartment in Abu Dhabi, near schools, with rental income potential and a 20-minute commute". The system ranks listings against that description instead of only filtering them. Each result card shows how close it is ("85% match") and why ("Within budget · 2 bedrooms · Near school").

### 1.1 Pages affected

| Page | URL / place | Who | What changed |
|---|---|---|---|
| AI chat widget | Bottom corner of every website page | Everyone | Result cards show "N% match" and up to 3 reasons (reasons in English chats only) |
| Lead page > Insights tab | CRM > Leads > a lead > Insights (/crm/leads/:id) | Agency, Agent, Super Admin | Each chat shows "Looking for" chips with the buyer's brief |
| Website Leads | CRM > Website Leads (/crm/website-leads/:id) | Super Admin | Same "Looking for" chips on each chat |

### 1.2 How it works

> flow: Buyer types a message -> AI reads it into a brief -> Hard filters narrow the pool -> Each listing is scored -> Top 6 cards with % match

- **Brief:** the AI fills these fields from the message: sale / rent / off-plan, property type, location, minimum and maximum budget, bedrooms, bathrooms, minimum size, nearby places (school, metro, hospital, …), furnished, purpose (live or invest), maximum service charge and commute.
- **Hard filters:** live residential listings only, with the same purpose, property type and location as the brief. The pool also allows up to **15% over** the maximum budget and **±1 bedroom**.
- **Scoring weights:** budget 30, bedrooms 25, nearby places 20, purpose 10, size 10, bathrooms 5, furnishing 5. Only the criteria the buyer gave count. The score is the points earned out of the points possible, as a %.
  - Bedrooms: an exact match scores full points; 1 bedroom off scores 40%.
  - Budget: over budget loses points in proportion to how far over it is, and is excluded at more than 15% over. Price on request scores half.
  - Nearby: points are shared between the places the buyer asked for, e.g. school found + metro missing = half.
- **"Invest" purpose:** for-sale listings only. A ready unit scores full points; off-plan scores 60% ("Off-plan — rental income after handover").
- **Not scored:** service charge and commute are not in the listing data. They are kept in the brief so the agent sees them.
- **Code:** `app/Services/Chatbot/PropertyMatcher.php`, `app/Services/ChatbotService.php`.

### 1.3 Test scenarios

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| AM-01 | Natural-language search | High | 1. Open any website page and the chat.<br>2. Type "A two-bedroom apartment in Abu Dhabi near a school, budget up to AED 2 million, for investment". | Up to 6 cards, all for sale, all apartments in Abu Dhabi, sorted from highest to lowest "% match". Top cards show reasons such as "Within budget · 2 bedrooms · Near school". |
| AM-02 | Near miss still shown | High | 1. Make sure a 3-bedroom apartment in Abu Dhabi within budget exists.<br>2. Repeat AM-01. | The 3-bedroom listing is shown below the exact 2-bedroom matches, with a lower %. |
| AM-03 | Budget stretch | High | 1. Set one matching listing's price to AED 2,200,000 (10% over).<br>2. Repeat AM-01.<br>3. Set it to AED 2,400,000 (20% over) and repeat. | At 10% over it shows with a lower %. At 20% over it is not shown. |
| AM-04 | Nearby place affects score | Medium | 1. In the CRM, remove "School" from a top-ranked listing's nearby places and save.<br>2. Repeat AM-01. | That listing's % drops and "Near school" is no longer one of its reasons. |
| AM-05 | Investor excludes rentals | Medium | 1. Ask "Something to invest in, 1 bedroom, Dubai Marina". | Only for-sale listings. A ready unit ranks above an otherwise equal off-plan unit. |
| AM-06 | Unscored wishes kept for agent | Medium | 1. Ask "2 bed in JVC, service charge under 15 per sq.ft, 20 min to DIFC".<br>2. Fill the details form.<br>3. Open the lead's Insights tab in the CRM. | Cards are ranked without service charge or commute. The chat on the Insights tab shows "Looking for" chips including the commute and service charge. |
| AM-07 | Other language | Low | 1. Switch the chat to Arabic and repeat AM-01 in Arabic. | Cards show "N% match" without reason text. The listing text is in Arabic when the website has Arabic enabled. |
| AM-08 | No match | Medium | 1. Ask for "7-bedroom villa in Ajman under AED 300,000". | No invented listings: either no cards, or only near misses with a low %. |

### 1.4 Measure and known gaps

- "Searches that lead to a qualified enquiry" has **no figure on screen**. Count it by hand: chats in Website Leads that reached the details form, out of all chats.
- "Time to find a suitable property" is **not measured**.
- **Not built:** "improves as users interact". The weights are fixed, and past views or favourites are not used for ranking. Do not log this as a bug.

## 2. Property authenticity and trust engine

**What it does.** Every listing is screened automatically each time it is saved. The screening looks for the same unit already listed by another agency, reused photos, suspicious prices, details that don't add up, and an advertiser without the right authority. Flagged listings go to Super Admin's **Listing Review** queue for a person to decide. The system flags only; it never says ownership is confirmed.

### 2.1 Pages affected

| Page | URL / place | Who | What changed |
|---|---|---|---|
| Listing Review list | CRM > Approvals > Listing Review (/crm/listing-review) | Super Admin only | New page: measure tiles, tabs Same unit, another agency / Risk signals / Resolved, sidebar badge |
| Listing Review case | /crm/listing-review/:id | Super Admin only | Side-by-side listings, "Why matched", "Compared with" price table, Approve / Reject |
| Listing Permits | /crm/listing-permits and /crm/listing-permits/:id | Super Admin | Red chips "Same unit as another agency" / "Risk flag"; Approve & publish also approves the review case |
| Property / Commercial edit form | /crm/properties/:id/edit | Agency, Agent | Banners "Not on the website yet…" and "MW Realty rejected this listing…"; switching a held or rejected listing on is refused |
| Bell notifications | CRM header | Agency, Agent | "Listing waiting for review", "Listing rejected", "Listing approved" |
| Website listing pages | /properties, /property-details/{slug} | Everyone | Held or rejected listings are not shown |

### 2.2 How it works

> flow: Listing saved -> Screening runs -> Nothing found: saved as normal -> Flag found: case opens in Listing Review -> Super Admin approves or rejects

| Check | When it is raised | Effect on the listing |
|---|---|---|
| Same unit, another agency | Another agency has an available listing with the same location, purpose and unit number | Newer copy held off the website; the first listing stays live |
| Same photos | At least 2 photos match another agency's listing (photo fingerprints, with the edges cropped so watermarks are ignored) | Same as above |
| Price far below / above similar listings | Price per sq.ft under 60% or over 200% of the median of at least 5 similar listings | Stays live, flagged |
| Listing details don't add up | The title disagrees with bedrooms, type or community, or the size is too small for the type | Stays live, flagged |
| Advertiser authority not confirmed | Account not KYC-approved; Dubai agency without ORN or owner without BRN; agent without BRN; agent not an active member of the agency | Stays live, flagged |
| Ad differs from the permit record | DLD / ADREC record differs: price by more than 1%, bedrooms, or size by more than 3% | Held off the website |
| Resubmitted after rejection | A rejected listing is saved again | Held off the website |
| Expired advertisement | Handled by Listing Permits: an expired permit cannot go live | Offline |

- **Approve:** the hold is lifted. The listing goes back on only if it was Active and its permit is approved.
- **Reject:** the reason is required. The listing stays off and the agency sees the reason. When the agency fixes it and saves, it comes back for review.
- **Official permit check:** the DLD / ADREC online check is built but **off by default** (DLD_PERMIT_API_ENABLED, ADREC_PERMIT_API_ENABLED). The "Ad differs from the permit record" signal can't be raised until it is switched on with real credentials.
- **Existing listings:** the developer command `php artisan properties:screen` shows what would be flagged (dry run); `--apply` opens the cases.
- **Code:** `app/Services/ListingReview/ListingReviewService.php`, `ImageHasher.php`, `app/Jobs/ScreenListingPhotos.php`.

### 2.3 Test scenarios

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| TR-01 | Access | High | 1. As Super Admin open CRM > Approvals > Listing Review.<br>2. As Agency, type /crm/listing-review. | Super Admin sees the page with 5 measure tiles and 3 tabs. The Agency has no menu item and gets an error page. |
| TR-02 | Same unit, another agency | High | 1. Agency A saves a sale listing in location X, unit 1203.<br>2. Agency B saves a sale listing in location X, unit 1203.<br>3. Check Listing Review and Agency B's bell. | Case under Same unit, another agency. A: "Listed first · stays live". B: "Waiting for your decision", off the website. B gets "Listing waiting for review". |
| TR-03 | Not a duplicate | Medium | 1. Agency B saves the same unit for rent (A's is for sale). | No case is opened. |
| TR-04 | Same photos | High | 1. Queue worker running.<br>2. Agency B adds a listing (different unit) with 2 of Agency A's photos, one with a watermark added.<br>3. Wait about a minute and reload Listing Review. | Same-unit case with "Why matched: <ref>: 2 matching photos". |
| TR-05 | Suspicious price | High | 1. In a community with 5 or more similar listings, save one at 40% of the usual price per sq.ft. | Risk signals: "Price far below similar listings", with a "Compared with" table and the normal range. The listing stays live. |
| TR-06 | Details don't add up | Medium | 1. Save an Apartment with 3 bedrooms and the title "Stunning 2 bed villa in Dubai Marina" in location JVC. | Risk case "Listing details don't add up" with the bedroom, type and community messages. |
| TR-07 | Advertiser authority | Medium | 1. Remove the ORN from a Dubai agency's profile.<br>2. Save one of its Dubai listings. | Risk case "Advertiser authority not confirmed". |
| TR-08 | Approve | High | 1. Open the TR-02 case and approve B's listing. | Toast "Approved — the listing can be on the website." B's listing goes live (if Active and permit approved). B gets "Listing approved". Re-saving both listings opens no new case. |
| TR-09 | Reject and resubmit | High | 1. Reject a held listing with the reason "Wrong unit number".<br>2. As the agency, try to switch it on.<br>3. Edit it and click Update. | Switch-on refused, with a red banner showing the reason. After Update it returns to Risk signals as "Resubmitted after rejection" with the old reason. |
| TR-10 | Fixed by the agency | Medium | 1. For the TR-06 listing, correct the title and save. | The risk case closes by itself as "Fixed by the agency". |
| TR-11 | Measures | Medium | 1. Note the tiles before and after TR-02 to TR-07. | "Flagged · last 30 days" goes up by the number of cases. "Duplicate rate" includes the listings in open same-unit cases. "Time saved" and "Avg. time to resolve" update. |
| TR-12 | Saving never breaks | High | 1. Save a listing with no size, no unit and no photos. | It saves normally. No error is caused by the screening. |

### 2.4 Measure

The tiles at the top of Listing Review cover the measures for this feature:

- **Duplicate rate**;
- **Flagged · last 30 days** (suspicious listings detected);
- **Permit verification** (verification completion rate);
- **Time saved · last 30 days**: the minutes per check come from LISTING_REVIEW_MINUTES_PER_CHECK, default 10;
- **Avg. time to resolve**.

## 3. Property investment intelligence

**What it does.** A listing's page now shows the numbers an investor needs:

- estimated rent and rental yield;
- service charges and total cost of ownership;
- cash flow, and financing scenarios with different down payments;
- the cost of renting;
- how the price compares with the area, with comparable deals.

Every figure says whether it is **Verified** (closed deals), an **Estimate** (asking prices) or an **Assumption** (what the visitor typed). The visitor can change the assumptions.

### 3.1 Pages affected

| Page | URL / place | Who | What changed |
|---|---|---|---|
| Property details: Mortgage & Investment tab | /property-details/{slug} (for sale) | Everyone | Switch between "Monthly payments" (old calculator) and the new "Investment returns" calculator |
| Property details: Rental Costs tab | /property-details/{slug} (yearly / monthly rent) | Everyone | New Rental Cost Estimator: cash to move in and the first-year cost |
| Property details: Market Insights tab | /property-details/{slug} (any listing with a price) | Everyone | Assessment badge, area price per sq.ft, 8-quarter trend chart, recent deals |
| Property details: header pill and Similar Properties | /property-details/{slug} | Everyone | Green pill (e.g. "Priced below market") that opens Market Insights; closer similar listings |
| Mark as sold / rented | CRM > Properties > a listing > Mark as sold | Agency, Agent | Closed deals become the "Verified" data |

### 3.2 How it works

- **Peers:** listings of the same property type in the same community, or the same emirate when there is no community. Peers include listings that are live, published in the last 24 months, or closed in the last 24 months.
- **Estimated value / rent:** median price per sq.ft × this listing's size. Peers with the same number of bedrooms are used when there are at least 3 of them. Closed deals are used before asking prices.
- **Minimum data:** a figure needs at least **3** peers. Otherwise it shows "Not enough data yet".
- **Assessment badge:**
  - Price per sq.ft at or below 92% of the area median is "Priced below market"; at or above 112% is "Priced above market".
  - Yield of 7% or more is "High yield potential"; under 4.5% is "Lower rental yield".
  - Growth of 5% or more is "Strong capital growth"; below 0% is "Prices softening".
- **Investment calculator inputs:** the visitor can change all of them.
  - Rent: the estimate, or a 6% yield when there isn't enough data.
  - Service charge: size × the rate for the type (apartment AED 18 per sq.ft).
  - Maintenance 1%, vacancy 5%.
  - Down payment: 20% resident, 15% national, 40% non-resident.
- **Investment calculator outputs:** monthly cash flow, gross and net yield, cash-on-cash return, total cost of ownership, and financing scenarios (minimum, 40%, 60%, cash, own value).
- **Accuracy tracking:** each day the listing's estimate is saved, so it can later be compared with the sold price. This is not visible on screen.
- **Code:** `app/Services/PropertyInsightService.php`; API `GET /api/properties/{slug}/insights`.

### 3.3 Test scenarios

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| IN-01 | Tabs by listing type | High | 1. Open a for-sale listing with a price.<br>2. Open a yearly rental.<br>3. Open a weekly rental. | Sale: Mortgage & Investment + Market Insights. Yearly rental: Rental Costs + Market Insights. Weekly rental: Market Insights only, no Rental Costs. |
| IN-02 | Estimate figures | High | 1. Open a for-sale listing in a community with 3 or more similar live listings and no closed deals.<br>2. Open Market Insights. | Tiles tagged "Estimate · N listings". "Recently sold nearby" shows "No deals closed in this area on MW Realty in the last 24 months…". |
| IN-03 | Verified figures | High | 1. Mark 3 similar listings in that community as sold.<br>2. Edit and save the listing from IN-02, then reload its page. | Tiles tagged "Verified · 3 deals" with a tick. The 3 deals are listed under "Recently sold nearby", with no unit numbers or names. |
| IN-04 | Not enough data | Medium | 1. Open a listing that is the only one of its type in its community. | "There aren't enough similar listings in this area yet to assess this property against the market." or "Not enough data yet" on the tiles. |
| IN-05 | Assessment badge | Medium | 1. Set a listing's price per sq.ft to about 85% of the area median and save. | Badge "Priced below market", and a green pill next to For Sale at the top that opens Market Insights. |
| IN-06 | Change the assumptions | High | 1. Open Investment returns.<br>2. Change the rent, service charges, down payment and residency. | Every result updates at once. The rent hint changes to "Your figure." Non-resident sets the minimum down payment to 40%. |
| IN-07 | Purchase costs sum | High | 1. Use a Dubai listing at AED 1,000,000 and choose Cash (100%).<br>2. Expand Total purchase costs. | Land department 40,580 + trustee 4,200 + agency 21,000 = AED 65,780. |
| IN-08 | Financing scenarios | Medium | 1. Look at the financing scenarios table. | Rows for the minimum, 40%, 60%, Cash and your own value. The current choice is highlighted. Cash flow is red when negative and green when 0 or more. |
| IN-09 | Rental Cost Estimator | High | 1. Use a Dubai unfurnished apartment at AED 100,000 a year, 1 cheque, no stated deposit. | Move-in AED 112,470. Total for the year AED 110,470. |
| IN-10 | No rent data | Medium | 1. Open Investment returns on a listing with no rentals nearby. | Rent = 6% of the price, with the hint "Not enough rentals nearby to estimate — a 6% yield is assumed…". |
| IN-11 | Disclaimer | Low | 1. Read under the badge. | "Automated assessment from market data — not a valuation." |

### 3.4 Measure and known gaps

- **Report usage:** each view of the section is counted in `property_daily_stats.insight_views`, but it is **not shown in any CRM screen yet**.
- **Accuracy against verified outcomes:** the daily estimates are saved in `property_insight_snapshots`, but there is **no comparison screen yet**.
- **Saved-property-to-enquiry conversion:** no figure on screen.

## 4. Cross-border property intelligence

**What it does.** A buyer, for example in India or Europe, compares up to 5 properties in different UAE emirates on one page. Each column shows the price, the full purchase or move-in costs for that emirate, yields, foreign-ownership rules and Golden Visa eligibility. Everything can be shown in the buyer's own currency.

### 4.1 Pages affected

| Page | URL / place | Who | What changed |
|---|---|---|---|
| Listing cards | /properties, /commercial, /premium-properties, /marketing-properties | Everyone | New **Compare** button ("Comparing" when ticked) |
| Property details | /property-details/{slug} | Everyone | Compare button |
| Compare bar | Bottom of every page except /compare | Everyone | Thumbnails, "N / 5 selected", Clear, Compare now |
| Compare page | /compare?slugs=a,b,c | Everyone | New page: side-by-side table, "Show prices in", "Only show differences", fee breakdown, summary, cross-border notes |
| Header currency switcher | All pages | Everyone | Rates can now refresh hourly |
| Currencies | Admin > General Settings > Currencies (/admin/currencies) | Admin | Rates may be overwritten by the hourly sync when it is switched on |

### 4.2 How it works

- **Purchase costs per emirate.** A listing with no emirate is costed as Dubai.
  - **Dubai:** land department 4% + AED 580, trustee AED 4,000 + VAT, agency 2% + VAT.
  - **Abu Dhabi:** transfer 2%, agency 2% + VAT.
  - **Northern Emirates:** registration about 4%, agency 2% + VAT.
- **Rentals:** agency fee 5% + VAT, plus a deposit (the listing's own, otherwise 5%, or 10% if furnished). Rents are compared per year.
- **Ownership:** a foreign-ownership note per emirate. **Golden Visa eligible** when the sale price is AED 2,000,000 or more.
- **Currency:** all amounts are calculated in AED and converted. With a non-AED currency the page shows:
  - "1 AED = x CODE · rates as of …";
  - a "With conversion margin" row (+1.5%);
  - a "Paying in CODE" note for INR, GBP, USD and EUR.
- **Rates:** typed in Admin. The hourly sync from a live feed runs only when CURRENCY_RATES_AUTO_SYNC=true (off by default).
- **Summary:** the "AI summary", or the rule-based "Quick summary" when AI is not available.
- **Code:** `app/Services/PropertyCompareService.php`, `app/Console/Commands/SyncCurrencyRates.php`.

### 4.3 Test scenarios

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| CB-01 | Build a comparison | High | 1. On /properties tick Compare on a Dubai and an Abu Dhabi for-sale listing.<br>2. Click Compare now. | /compare opens with 2 columns. The URL holds both slugs. The bar showed "2 / 5 selected". |
| CB-02 | Limits | Medium | 1. Tick 1 listing.<br>2. Tick 6 listings. | With 1: "Pick one more property to compare." and Compare now is disabled. A 6th: "You can compare up to 5 properties — remove one to add another." |
| CB-03 | Costs per emirate | High | 1. Compare a Dubai and an Abu Dhabi listing, both at AED 1,000,000.<br>2. Tick Show fee breakdown. | Dubai fees AED 65,780 (total AED 1,065,780). Abu Dhabi: 2% transfer + agency 2% + VAT. |
| CB-04 | Foreign currency | High | 1. Choose INR in "Show prices in". | All amounts in INR. "1 AED = x INR · rates as of …" shown. Extra row "With conversion margin" = total + 1.5%. "Paying in INR" note shown. |
| CB-05 | Golden Visa and ownership | Medium | 1. Compare a listing at AED 1,900,000 and one at AED 2,000,000. | ✓ only on the AED 2,000,000 listing. Ownership text differs per emirate. |
| CB-06 | Rent vs sale | Medium | 1. Compare a monthly rental at AED 10,000. | Price shows AED 120,000 "per year · advertised 10,000 monthly". Move-in costs are shown. |
| CB-07 | Shared link | Medium | 1. Copy the /compare URL.<br>2. Open it in a private window. | The same listings are shown, and that window's compare bar is replaced with them. |
| CB-08 | Only show differences | Low | 1. Tick Only show differences. | Rows where every value is the same are hidden. |
| CB-09 | Summary without AI | Medium | 1. With AI off (or the AI call failing), open /compare. | "Quick summary" with labels such as "Lowest budget", "Investors after rental income". |
| CB-10 | Rate sync (developer) | Low | 1. Run `php artisan currencies:sync-rates`. | "Updated N currency rate(s)." The new rates show on the site within 10 minutes. |

### 4.4 Measure and known gaps

- **Markets covered:** UAE emirates only. There is no other country, its own taxes or ownership rules yet.
- International registrations and cross-border enquiries are **not tracked as separate figures**.

## 5. Conversational property assistant

**What it does.** The website chat assistant, **Noor**, talks to buyers in 10 languages. It finds and ranks listings (feature 1), answers questions using listing data, and explains estimated costs. It does not act as a lawyer, financial adviser or broker. When the buyer leaves their details, the conversation becomes a lead for the agency.

### 5.1 Pages affected

| Page | URL / place | Who | What changed |
|---|---|---|---|
| AI chat widget | Bottom corner, all website pages | Everyone | Language button "EN ▾", offer to switch language, right-to-left support, voice in the chat language, details form with "Not now" (×), match % on cards |
| Website Leads | /crm/website-leads and /crm/website-leads/:id | Super Admin | Chats with "Looking for" chips |
| Lead > Insights tab | /crm/leads/:id | Agency, Agent | Same chat history and brief |
| Lead Insights | /crm/lead-insights | Agency, Super Admin (plan feature) | Counts AI chats per lead |

### 5.2 How it works

- **Languages:** English, العربية, हिन्दी, اردو, فارسی, Русский, 简体中文, Français, Deutsch, Español.
  - The choice is remembered in the browser and saved on the conversation.
  - A message in another language (4+ letters) triggers a yellow offer to switch.
- **Rules given to the AI:**
  - base facts on listing data and call estimates estimates;
  - give only general information on legal, tax, visa or financing questions, and recommend a professional;
  - never invent listings.
- **Enquiry:** after the free first question, the "Want to keep chatting?" form asks for the visitor's details. Sending it creates a website lead, routed to the listing's agency or agent.
- **Limits:** 8 messages per minute and 150 per day per IP address.
- **Code:** `app/Services/ChatbotService.php`, `app/Services/Chatbot/GroqChatbotProvider.php` / `GeminiChatbotProvider.php`, `app/Http/Controllers/Api/ChatbotController.php`.

### 5.3 Test scenarios

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| CA-01 | Welcome and name | Medium | 1. Open the chat on the home page. | "Welcome to MW Realty! I'm Noor, your personal property finder…". |
| CA-02 | Change language | High | 1. Click "EN ▾" and choose العربية before typing. | The welcome is shown in Arabic, right-aligned. The placeholder is in Arabic. |
| CA-03 | Switch offer | High | 1. In English, type "أبحث عن شقة بغرفتين في دبي".<br>2. Click العربية. | A yellow bar offers Arabic. After switching, a confirmation line is shown and every later reply is in Arabic. |
| CA-04 | Decline the offer | Low | 1. Type in French, decline, then type in French again. | Not offered again until the page is reloaded. |
| CA-05 | Listing question | High | 1. Ask about the price and service charge of a shown card. | The answer uses the listing's figures. Estimated values are called estimates. |
| CA-06 | Advice boundaries | High | 1. Ask "Can I get a mortgage as a non-resident and is this legal for foreigners?" | General information only, with a recommendation to check with a bank, lawyer or adviser. It does not claim to be one. |
| CA-07 | No invented facts | High | 1. Ask about "the penthouse on Palm Jumeirah for AED 500,000". | It does not invent a listing; it says it can't find one. |
| CA-08 | Enquiry hand-off | High | 1. Ask a second question so the details form appears.<br>2. Click × (Not now).<br>3. Send again, fill the form and send. | × puts the message back in the input box. After the form is sent, the lead appears in CRM > Website Leads (Super Admin) and on the agency's Leads, with the chat and "Looking for" chips. |
| CA-09 | Rate limit | Low | 1. Send 9 messages within one minute. | The 9th shows the chat error line in the chat language. |
| CA-10 | AI not configured | Medium | 1. On a test server without CHATBOT_PROVIDER, send a message. | "The AI assistant isn't available yet — please use the enquiry form on any listing instead." |

### 5.4 Measure

- **Enquiry completion:** count the Website Leads created from chats.
- **Self-service sessions and support cost per user:** **no figure on screen**.

## 6. Agency listing automation API

**What it does.** An agency loads its whole inventory in one of four ways: the dashboard, a spreadsheet (any column layout), a Property Finder sync, or the CRM API. The system:

- tidies the text (standardises descriptions);
- can write missing descriptions with AI and translate into the other site languages;
- detects duplicates;
- lists exactly what is still missing per listing.

### 6.1 Pages affected

| Page | URL / place | Who | What changed |
|---|---|---|---|
| Properties / Commercial list | /crm/properties, /crm/commercial | Agency, Agent | Add Property caret menu (Add one by one / Import from Excel / CSV / Sync from Property Finder); import progress banner |
| Import listings | /crm/properties/import, /crm/commercial/import | Agency, Agent | New page: 1 Upload file → 2 Match columns → 3 Import, plus Previous imports |
| Listing form > Description | /crm/properties/create, /crm/properties/:id/edit | Super Admin, Agency, Agent | New **Write with AI** button |
| Integrations | /crm/integrations | Agency, Agent | New tile "Excel / CSV import" |
| Bell notifications | CRM header | Agency, Agent | "Listing import finished" / "Listing import failed" |
| CRM API | /api/crm/... (documentation at /docs) | Agency, Agent (Bearer token) | Listings and imports can be automated |

### 6.2 How it works

> flow: Upload file -> Match columns -> Background import -> Each row checked -> Added / Needs details / Duplicate / Skipped -> Status file + bell

- **Reading the file:** values are matched to MW Realty options (types, amenities, locations, emirates), and text is tidied.
- **Duplicate when:**
  - the same permit number exists;
  - the same "Your reference" exists;
  - the same type + community + bedrooms + size (±3%) + price (±2%) exists;
  - an earlier row in the same file is the same.
  - A permit already used by **another** account makes the row Skipped.
- **Needs details** lists what is missing, e.g. "still needed: photos, description, map pin, AR title, DLD advertising permit number".
- **Optional ticks:**
  - "Write a description with AI" for rows with none;
  - "Translate" into the other site languages.
- **Listings and photos:**
  - Every imported listing is created **Inactive**, and goes live only through the normal permit flow.
  - Up to 30 photos per listing are downloaded and watermarked.
- **Limits:** one import per account at a time. The plan listing limit is checked on every row.
- **API:** POST /api/crm/auth/login gives a Bearer token. Then:
  - POST /api/crm/properties adds a listing;
  - POST /api/crm/properties/imports uploads a file;
  - POST /api/crm/properties/imports/{id}/start starts it;
  - GET /api/crm/properties/imports/{id} returns its status;
  - GET /api/crm/properties/imports/{id}/result returns the status file.
- **Code:** `app/Imports/PropertiesImport.php`, `app/Jobs/ProcessPropertyImport.php`, `app/Services/Properties/ListingCopywriter.php`, `ListingTextNormalizer.php`, `ListingOptionMatcher.php`.

### 6.3 Test scenarios

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| LA-01 | Template import | High | 1. Import page > Download template.<br>2. Fill 2 complete rows with photos and a permit.<br>3. Upload, keep the suggested matches, and import. | Progress bar, then "Import finished — 2 rows" with "2 ready". Both listings are Inactive in Properties. A bell "Listing import finished" arrives. |
| LA-02 | Own column names | High | 1. Upload a sheet with the headings "Cost", "Beds", "Area Name", "Kind".<br>2. Match them in step 2. | Sample values show under each column. The import works without editing the file. |
| LA-03 | Row results | High | 1. In one file add: a row with no price, a row with the same permit as row 2, a copy of an existing listing, and a row without photos. | Status file colours: Skipped "Price is empty", Duplicate "Same permit as row 2 of this file.", Duplicate "Looks like RERA… — same type, community, bedrooms, size and price.", Needs details "still needed: photos…". |
| LA-04 | Permit of another agency | Medium | 1. Use a permit number already used by Agency B's listing in Agency A's file. | Skipped: "This permit number is already used by another listing on MW Realty — each advertising permit covers one listing." |
| LA-05 | AI description and translation | High | 1. Leave the description empty on 2 rows.<br>2. Tick both AI and Translate in step 2. | The result says "2 descriptions written by AI · N translated". The listings have a description, and an Arabic title when Arabic is enabled. |
| LA-06 | Plan limit | Medium | 1. Use an account with 2 listings left; import 5 rows. | 2 added; the rest Skipped "Plan listing limit reached." with a red upgrade message. |
| LA-07 | One import at a time | Low | 1. Upload a second file while the first is running. | "<file> is still importing — please wait for it to finish before uploading another file." |
| LA-08 | Write with AI | High | 1. Fill a listing form (type, bedrooms, location, amenities).<br>2. Click Write with AI > Write new > Professional > Write.<br>3. Click Use this. | The preview shows a title (no price, max 80 characters), 4–8 key features and a description using only the form's details. The fields are filled; nothing is saved until Save Property. |
| LA-09 | Improve disabled | Low | 1. Open Write with AI with an empty description. | "Improve my description" is disabled. |
| LA-10 | API import | Medium | 1. In Postman, POST /api/crm/auth/login as the agency.<br>2. POST the file to /api/crm/properties/imports with the Bearer token, then call start.<br>3. Poll GET /api/crm/properties/imports/{id}. | Same result as the dashboard: progress, counts and a status file link. The import also shows in Previous imports. |
| LA-11 | Measures on screen | Medium | 1. Open Previous imports. | The **Automated** column shows the % of rows with nothing left to fill in. The result card shows the duration. |

### 6.4 Measure

- **Listings processed automatically:** the **Automated %** column in Previous imports, and "N% of rows imported with nothing left to fill in" on the result card.
- **Onboarding time:** the duration on the result card (e.g. "3 min 5s").
- **Agency retention:** plan subscriptions. This is not specific to this feature.

## 7. Property demand and market analytics

**What it does today.** The platform records visitor searches, saved searches, property views, favourites, AI chats and enquiries, plus impressions and clicks per listing. Agencies see this **for their own leads and listings**.

### 7.1 Pages affected

| Page | URL / place | Who | What it shows |
|---|---|---|---|
| Lead Insights | /crm/lead-insights | Agency, Super Admin (plan feature lead_insights) | Website leads ranked by activity: Recently active, Most time on site, Most property views, Most AI chats |
| Lead > Insights tab | /crm/leads/:id | Agency, Agent, Super Admin | Timeline, favourites, searches and saved searches, chats, most-viewed properties |
| Website Leads | /crm/website-leads/:id | Super Admin | The same panel for every website visitor lead |
| Listing performance | Properties list > a card's Insights / quality ring | Agency, Agent, Super Admin (plan feature property_insights) | Impressions → listing clicks → lead clicks → leads, last 30 days, quality score |
| Reports | /crm/reports/leads, /properties, /sales, /agents | Plan feature reports_access | Leads, listings, sales and agents over time, for the account's own data |

### 7.2 How it works

- **Website tracking:** a visitor is identified by a browser cookie. Views, searches, saved searches, favourites and chats are stored as visitor events. When the visitor leaves their details, these events are attached to the lead.
- **Listing tracking:** listing cards seen on screen and clicks are added up per listing per day.
- **Privacy:** an account sees only its own leads and listings. Super Admin sees all.
- **Code:** `app/Services/Visitors/VisitorTracker.php`, `VisitorInsights.php`, `app/Services/ListingPerformanceService.php`, `app/Http/Controllers/Crm/Leads/LeadInsightsController.php`.

### 7.3 Test scenarios

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| DA-01 | Visitor activity recorded | High | 1. In a fresh browser, search for "2 bed Dubai Marina under 2M".<br>2. Open 3 listings of Agency A, add 1 to favourites and save the search.<br>3. Chat once and send the details form (or enquire on a listing). | The lead appears for Agency A. Its Insights tab shows the search, the 3 views, the favourite, the saved search and the chat with "Looking for" chips. |
| DA-02 | Lead ranking | Medium | 1. Open /crm/lead-insights as Agency A.<br>2. Change the sort between Recently active, Most time on site, Most property views and Most AI chats. | The DA-01 lead moves according to the chosen sort. Totals at the top count all leads. |
| DA-03 | Listing funnel | High | 1. Scroll /properties so Agency A's listing card is seen, then open it and click Call / WhatsApp.<br>2. In the CRM open that listing's Insights. | Impressions, listing clicks and lead clicks go up for today in the 30-day chart. |
| DA-04 | Privacy between agencies | High | 1. As Agency B, open Lead Insights and Leads. | Agency A's DA-01 lead and its activity are not visible. |
| DA-05 | Reports | Medium | 1. Open /crm/reports/leads and /crm/reports/properties as Agency A. | Charts include the new lead and listing activity. Without the plan feature a "locked" message names the plan. |

### 7.4 Known gap (not a bug)

There is **no marketplace-wide, anonymised demand screen** yet: where buyers are searching across the whole platform, which price bands have few listings, or how demand changes over time. The raw data needed for it (searches, saved searches, views and enquiries in `visitor_events`, plus `property_daily_stats`) is already being collected, so this screen can be built on top of it. Until then, these measures are **not available**:

- forecast performance;
- subscription renewals driven by analytics;
- reduction in marketing spend.

## 8. Summary

| # | Feature | Status | Main page to test | Scenarios |
|---|---|---|---|---|
| 1 | AI property matching | Done. Learning from behaviour not built | Website AI chat | AM-01 to AM-08 |
| 2 | Authenticity and trust | Done. Official permit API off by default | /crm/listing-review | TR-01 to TR-12 |
| 3 | Investment intelligence | Done. Usage and accuracy not shown on screen | /property-details/{slug} tabs | IN-01 to IN-11 |
| 4 | Cross-border | Partly done: UAE emirates and currencies | /compare | CB-01 to CB-10 |
| 5 | Conversational assistant | Done | Website AI chat, /crm/website-leads | CA-01 to CA-10 |
| 6 | Listing automation API | Done | /crm/properties/import, /api/crm | LA-01 to LA-11 |
| 7 | Demand analytics | Partly done: per lead and per listing | /crm/lead-insights, listing Insights, Reports | DA-01 to DA-05 |

**Reporting a bug:** give the scenario ID, the account used, the URL, the listing reference, what you expected and what you saw, with a screenshot. For AI answers, copy the full chat text, because AI wording changes from run to run.

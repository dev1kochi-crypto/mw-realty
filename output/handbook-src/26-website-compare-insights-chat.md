# Website: Compare, Insights & AI Chat

New public-website tools: side-by-side property comparison, market and investment figures on the property page, live currency rates, and a multilingual AI chat.

**Where:** Public website: the **Compare** button on listing cards (/properties, /commercial and their filtered variants) and on /property-details/{slug}; the floating compare bar on every page; the comparison page /compare; the new tabs **Mortgage & Investment**, **Rental Costs** and **Market Insights** on /property-details/{slug}; the AI chat widget (bottom corner, all pages). Currency rates are kept in Admin > General Settings > Currencies and can be refreshed automatically by the server. **Who:** Everyone. Visitors, signed-in customers, agents and agencies see the same pages; nothing here needs a login. Admin manages currency rates. **Locks:** None on the website. The AI summary on /compare and the AI chat need an AI provider configured on the server; without one, both use fixed-text fallbacks (see section 2). Requests per network (IP) address are rate-limited (section 2.6).

## 1. What this module does

**Compare.** A visitor ticks **Compare** on up to 5 listings. A floating bar collects them; **Compare now** opens /compare with all of them side by side. The page shows price, size, buying or move-in costs for each emirate, estimated yields, specs, nearest places, amenities, foreign ownership and Golden Visa eligibility, in the currency the visitor picks. A written summary of the key differences sits at the top. When AI is available it is labelled **AI summary**; otherwise **Quick summary**.

**Property insights.** On a listing's page the old **Mortgage** tab is now **Mortgage & Investment**, with a switch between the mortgage calculator and a new buy-to-let **Investment Calculator**. Rental listings get a **Rental Cost Estimator**. Every listing with a price gets **Market Insights**: an automated assessment badge, area price-per-sq.ft figures, an 8-quarter trend chart and recent closed deals. Each figure is tagged with where it comes from. **Similar Properties** now shows closer matches.

**Currency rates.** Rates can now refresh hourly from a live feed when the server switch is on. /compare shows the time of the last rate change ("rates as of").

**AI chat.** The assistant (default name now **Noor**, was Remi) chats in 10 languages. A language button in the chat header changes the language, and the chat offers to switch when the visitor types in another language. The details form can now be closed with **Not now**. Result cards show a **"{score}% match"** label.

| Page / tool | What it does |
|---|---|
| **Compare** button on cards (/properties, /commercial, /premium-properties, /marketing-properties) and on /property-details/{slug} | Adds or removes the listing from the compare list. Label changes from "Compare" to "Comparing". |
| Compare bar (all pages except /compare) | Thumbnails of picked listings with ×, empty slots up to 5, **Clear**, **Compare now** (active from 2 listings). |
| /compare?slugs=a,b,c | Side-by-side table, **Show prices in** currency picker, **Only show differences**, **Show fee breakdown**, summary box, cross-border notes. The link can be shared. |
| /property-details/{slug} > **Mortgage & Investment** | For-sale listings: **Monthly payments** (existing mortgage calculator) or **Investment returns** (new Investment Calculator). |
| /property-details/{slug} > **Rental Costs** | Yearly or monthly rentals: cash to move in and cost of the first year. |
| /property-details/{slug} > **Market Insights** | Assessment badge and signals, area figures, price trend chart, recently sold or rented nearby. |
| /property-details/{slug} > **Similar Properties** | Up to 6 listings with the same purpose and type, close in price and bedrooms. |
| Header currency switcher | Same switcher as before. Rates may now come from the hourly sync. |
| AI chat widget (all pages) | Chat language button "EN ▾", language-switch offer, details form with ×, match % on cards. |

## 2. How it works (business rules)

### 2.1 Compare

- **Limits:** at least 2 and at most 5 listings (config property_compare: min_items 2, max_items 5). A 6th pick is refused and the bar shows "You can compare up to 5 properties — remove one to add another." for about 3.5 seconds. With 1 listing the bar says "Pick one more property to compare." and **Compare now** is greyed out. With 2 to 5 it shows "N / 5 selected".
- **Storage:** the list is kept in the browser (localStorage key `mw-compare`). It survives reloads, is shared across tabs of the same browser, and is not tied to an account. The bar is hidden when the list is empty and on /compare itself.
- **URL is the list:** /compare reads `?slugs=`. Opening /compare without slugs uses the bar's list. If that list is also empty, the page shows "You haven't picked any properties to compare yet. Tick "Compare" on any listing to add it here." with **Browse properties**. With only 1 valid listing it shows "Add at least one more property to compare." Slugs are trimmed, duplicates removed, and only the first 5 are used. Slugs of listings that are not live are skipped without an error message. Opening a shared link replaces the visitor's bar with that link's listings.
- **Removing:** × on a column header removes the listing from the page, the URL and the bar. While fewer than 5 listings are shown, an extra column offers **+ Add a property**, which links to /properties.
- **Sections and rows:** Price & cost to buy; Investment (only when at least one listing is for sale); The property; Location (one "Nearest …" row for each nearby-place type any listing has); Amenities (count plus one ✓/— row per amenity, A–Z); Ownership & residency (sale listings only). A row where every listing shows "—" is always hidden. **Only show differences** also hides rows where all values are the same, and drops sections that end up empty.
- **Best value highlight:** in rows that rank (lowest price, price/sq.ft, costs and total; highest yield, size, bedrooms, bathrooms, parking, year built, amenities; nearest place) the best cell is highlighted. A cell is highlighted only when at least 2 listings have a value and the values differ. Price and cost rows rank only when all listings have the same purpose (all sale or all rent).
- **Rent vs sale:** rent is compared per year. A monthly rent is ×12, weekly ×52, daily ×365, and a rent with no period counts as yearly. The Price cell shows "per year · advertised <price> <period>".
- **Buying costs (sale), by the listing's emirate.** A listing with no emirate, or an unknown one, is costed as Dubai.
  - Dubai: Dubai Land Department fee 4% + AED 580; registration trustee fee AED 4,000 + VAT; agency fee 2% + VAT.
  - Abu Dhabi: transfer fee 2%; agency fee 2% + VAT.
  - Northern Emirates: registration about 4%; agency fee 2% + VAT.
  - VAT is 5%. Example: Dubai, AED 1,000,000 gives fees 40,580 + 4,200 + 21,000 = **AED 65,780**, so Total cash needed is **AED 1,065,780**.
- **Move-in costs (rent):** agency fee 5% of annual rent + VAT, plus a refundable deposit. The deposit is the listing's own security deposit when it states one, otherwise 5% of the annual rent (10% if furnished). Total cash needed = first year's rent + move-in costs.
- **Investment rows (sale):** Estimated annual rent, Gross rental yield, Net yield (est.), Service charge (est.) and Area price growth (1 yr) all come from the Market Insights figures (2.2).
  - Net yield = (estimated rent × (1 − 5% vacancy) − service charge − 1% of price) ÷ price.
  - Yields above 30% are treated as a mistyped price and shown as "—".
  - "Price vs similar in area" shows "N% below", "N% above" or "In line".
- **Ownership & Golden Visa:** fixed text for each emirate. **Golden Visa eligible** is ✓ when the sale price is at least AED 2,000,000.
- **Currency:** all amounts are calculated in AED and converted for display. When the currency is not AED:
  - the toolbar shows "1 AED = x CODE · rates as of <date time>";
  - an extra row **With conversion margin** adds 1.5% to Total cash needed;
  - a **Currency conversion** note appears;
  - for INR, GBP, USD and EUR a **Paying in CODE** note adds a home-country tax/remittance note.

  **Taxes in the UAE** is always shown. **Mortgages for non-residents** (up to 60% loan) and **Golden Visa** notes appear when any listing is for sale.
- **Summary box ("Key differences at a glance"):**
  - Loads after the table and reloads when the listings or the currency change. There is a 0.4-second pause so that flicking through currencies sends only one request.
  - With AI it shows a 2-sentence overview, 4–6 points, the disclaimer "Written from the listing data below. Yields, values and fees are estimates — check them with the agent before you decide." and "Best for: …" under each column.
  - Without AI, or when the AI call fails, fixed rules write the **Quick summary**: lowest and highest price, best value per sq.ft, largest, highest yield, most amenities, different emirates, Golden Visa. Its "Best for" labels are "Lowest budget", "Most space for the money", "Buyers who need the most space", "Investors after rental income" and "Lifestyle and amenities".
  - When the request itself fails: "The summary isn't available right now — the full comparison is below."
  - AI summaries are reused for 1 hour for the same listings + currency + language.

### 2.2 Property insights (property details page)

- **Which tabs appear:**
  - **Mortgage & Investment**: for-sale listings with a price above 0.
  - **Rental Costs**: rent listings with a price above 0 whose period is yearly, monthly or empty. Weekly and daily lets do not get it.
  - **Market Insights**: any listing with a price above 0.

  The tabs and the mobile tab dropdown list the same options. The Market figures load after the page, so the tabs and sections appear first and fill in afterwards. While loading, Market Insights shows three loading dots. On failure it shows "Market data is not available right now."
- **Where market data comes from:**
  - Peers are listings of the same property type in the same community (location). If the listing has no community, the emirate is used.
  - Peers include listings still live, listings published in the last 24 months, and deals closed (Mark as sold / rented) in the last 24 months.
  - "Verified" figures come from closed deals. Their tag reads "Verified · N deals" with a tick. "Estimate" figures come from asking prices: "Estimate · N listings". Inputs the visitor sets show the tag "Assumption".
  - A figure needs at least **3** listings or deals (PROPERTY_INSIGHTS_MIN_SAMPLE, default 3). Otherwise it is left out or shows "Not enough data yet" / "No closed deals yet".
  - The estimated value or rent is the median price per sq.ft × the listing's size. Peers with the same number of bedrooms are used when there are at least 3 of them; otherwise all peers. Verified deals are used before asking prices. Values are rounded to the nearest 1,000.
  - Growth compares the median price/sq.ft of the last 12 months with the 12 months before.
  - Results are cached for 1 hour per listing. Editing the listing refreshes them.
- **Assessment badge:** the thresholds are below. Sale: two or more positive signals and no caution signal gives **Strong investment choice**. Otherwise the headline is the first positive signal, then the first caution signal, then **In line with the market** (sale) or **Fair market rent** (rent). With no data at all the card reads "There aren't enough similar listings in this area yet to assess this property against the market." Only a positive headline also appears as a green pill next to For Sale / For Rent at the top of the page. Clicking the pill opens Market Insights.

  | Signal | Positive | Neutral | Caution |
  |---|---|---|---|
  | Price per sq.ft vs area median | ≤ 92% → Priced below market (rent: Good value rent) | between → In line with the market | ≥ 112% → Priced above market (rent: Above market rent) |
  | Gross yield (sale only) | ≥ 7% → High yield potential | 4.5–7% → Steady rental yield | < 4.5% → Lower rental yield |
  | 12-month growth | ≥ 5% → Strong capital growth | 0–5% → no signal | < 0% → Prices softening |

  The disclaimer under the badge reads "Automated assessment from market data — not a valuation."
- **Market Insights layout:**
  - Stat tiles: This listing per sq.ft, Area asking per sq.ft, Area sold/rented per sq.ft, Estimated market value (or rent a year), Gross rental yield, Price change last 12 months.
  - Chart "Price per sq.ft over time" (rent: "Rent per sq.ft over time"): 8 quarters, blue "Asking price (estimate)" and red "Closed deals (verified)" lines, with a tooltip on hover or focus. It needs at least 2 data points in total, otherwise: "Not enough listings or deals in this area over the last two years to chart a trend."
  - "Recently sold nearby" / "Recently rented nearby": up to 6 deals (Date, Bedrooms, Size, Price/Annual rent, Per sq.ft). Rows with the same number of bedrooms are highlighted. No unit, address or party names are shown. With no deals: "No deals closed in this area on MW Realty in the last 24 months, so the figures above come from asking prices and are estimates."
- **Investment Calculator** (Mortgage & Investment > **Investment returns**). Starting values, all of which the visitor can change:
  - Expected annual rent: the rent estimate, otherwise a 6% yield on the price. The fallback hint reads "Not enough rentals nearby to estimate — a 6% yield is assumed. Ask the agent for the achievable rent." After the visitor edits the rent, the hint changes to "Your figure."
  - Service charges: size × AED/sq.ft by type (apartment 18, penthouse 22, villa/townhouse 5, office 20, shop 22, showroom 20, warehouse 6, other 12). With no size the value is 0 and the hint is "Size not listed — enter the building's annual service charges."
  - Maintenance 1% of price; vacancy 5%.
  - Residency toggle. The minimum down payment is 20% resident, 15% national, 40% non-resident; above AED 5M it is 30% / 25% / 40%. Rates are 3.75% / 4.24% / 4.19%. Loan period 25 years, holding period 5 years.

  Results:
  - **Monthly cash flow**: green when ≥ 0, red when negative.
  - Gross and net rental yield, cash-on-cash return, monthly rent after vacancy and monthly payment.
  - **Total cost of ownership**, with an expandable **Total purchase costs** list.
  - **Financing scenarios** for the minimum down payment, 40%, 60%, Cash (100%) and the visitor's own value. The current choice is highlighted.

  Purchase costs here are always the Dubai fees: land department 4% + 580; trustee 4,000 + VAT; agency 2% + VAT; with a loan also mortgage registration 0.25% + 10, bank arrangement 1% + VAT and valuation 3,000 + VAT. Net yield = (rent × (1 − vacancy) − service charges − maintenance) ÷ (price + purchase costs).
- **Rental Cost Estimator** starting values:
  - Annual rent: listing price × 12 for monthly rents. The hint "Listed per month — shown here for 12 months." appears for monthly rents.
  - Agency fee 5%.
  - Number of cheques: the listing's own count, otherwise 12 for monthly rents and 1 for others. Options are 1, 2, 4, 6, 12, plus the listing's own count.
  - Security deposit: the listing's own amount ("As stated on this listing…"), otherwise 5% of the rent, or 10% if furnished.
  - Utility connection: Villa / townhouse for villas and townhouses, otherwise Apartment.

  Fixed items: Ejari/Tawtheeq registration AED 220; utility deposit 2,000 (apartment) or 4,000 (villa). Municipality housing fee: Dubai 5%, Abu Dhabi 3%, other emirates none (row hidden).
  - **Cash needed to move in** = first rent payment (annual ÷ cheques) + deposit + agency fee + registration + utility deposit.
  - **Total for the year** = rent + housing fee + move-in costs − refundable deposits.
  - Example: Dubai, AED 100,000/yr, unfurnished apartment, 1 cheque, no stated deposit. Deposit 5,000, agency 5,250, registration 220, utility 2,000. Move in AED 112,470. Year AED 110,470. "Agency fee & registration" 5,470. Refundable 7,000.
- **Similar Properties:** same purpose and property type in the same community or emirate, price within ±30% and bedrooms within ±1. Ranked by same community, then bedrooms, price and distance. If fewer than 6 are found, the list is topped up with listings of the same type or community.
- **Behind the scenes:** each time the figures are built, the day's estimate for the listing is saved (one row per listing per day) so it can later be checked against the sold price. Testers cannot see this on screen.

### 2.3 Currency rates

- Prices are stored in AED. The switcher multiplies by each currency's rate (units per 1 AED) as before.
- **Automatic sync** runs only when the server setting CURRENCY_RATES_AUTO_SYNC=true. The default is off, so the rates Admin types in are not overwritten. When on, it runs every hour and updates every active currency except AED from the feed (default https://open.er-api.com/v6/latest/AED). A currency the feed does not list keeps its rate. Every updated currency gets a new "updated" time even when the rate did not change.
- A developer can also run the command `currencies:sync-rates` by hand. Messages: "Updated N currency rate(s).", "CODE: not in the feed, rate kept.", "Rate feed unreachable: …", "Rate feed returned an unexpected response (HTTP n)."
- The public currency list and the "rates as of" time are cached for up to 10 minutes. Saving or deleting a currency in Admin clears the cache. "Rates as of" is shown only on /compare, and only when a non-AED currency is selected.

### 2.4 AI chat

- **Name:** the server setting CHATBOT_NAME. The default changed from Remi to **Noor**.
- **Languages:** English (default), العربية, हिन्दी, اردو, فارسی, Русский, 简体中文, Français, Deutsch, Español. The header button shows the current code, e.g. "EN". The dropdown is titled "Chat language" and shows each language's own name with its English name underneath; the current one is ticked. Click outside or press Escape to close it.
- **Switching:**
  - Before anything has been said, the welcome is shown again in the new language.
  - Mid-conversation, the assistant adds a confirmation line in the new language, e.g. "Sure — I'll reply in English from now on." From then on the AI replies only in that language, whatever language the visitor types in.
  - The choice is remembered in the browser (`mw-chat-language`) and saved on the conversation. After a reload the browser's choice wins; otherwise the conversation's language is used.
- **Switch offer:**
  - When a sent message looks like another of the assistant's languages, a yellow bar shows the offer in that language, e.g. "يبدو أنك تكتب بالعربية. هل تريد المتابعة بالعربية؟", with two buttons: the new language (e.g. العربية) and the current one (e.g. English).
  - Declining means that language is not offered again until the page is reloaded.
  - Detection needs at least 4 letters. Arabic, Urdu, Persian, Hindi, Russian and Chinese are recognised by their script. French, German and Spanish (and English) need at least two common words and a clear winner; otherwise nothing is offered.
- **Language-specific texts:** the welcome, the input placeholder, the error line ("Sorry, I'm having trouble answering right now…") and the not-configured line ("The AI assistant isn't available yet — please use the enquiry form on any listing instead.") come in the chat language. The English welcome is now "Welcome to MW Realty! I'm {name}, your personal property finder. Tell me what you're looking for, and I'll pull up real matches for you in seconds."
- **Right-to-left:** chat bubbles and the input use automatic direction, so Arabic, Urdu and Persian text is right-aligned.
- **Voice:** the microphone listens, and read-aloud speaks, in the chat language's locale (e.g. ar-AE). Support depends on the browser.
- **Result cards:**
  - Up to 6 cards, residential only, ranked against what the visitor described (budget, bedrooms, nearby places, purpose, size, bathrooms, furnishing).
  - Each card shows "N% match". In an English chat, up to 3 reasons follow (e.g. "Within budget · 2 bedrooms · Near school"). In other languages only the percentage is shown.
  - The listing text on cards follows the chat language when the website also has that language enabled (e.g. Arabic). Otherwise it follows the website language.
- **Details form** ("Want to keep chatting?"): the free first question works as before. The form now has an × (**Not now**) button. Closing it takes the waiting message off the chat and puts it back in the input box. The form appears again on the next send.
- **What the AI may say:** the assistant is told to base facts on listing data, say when something is an estimate, and give only general information on legal, tax, visa or financing questions, recommending a professional.
- **CRM side:** on a lead's Insights panel (CRM > Leads > lead > Insights), each chat now shows "Looking for" chips of what the visitor asked for, e.g. "Budget: up to AED 2,000,000", "Near: School". The chat language is saved but is not shown in the CRM (per code).

### 2.5 Tracking

- When the Market Insights / Investment section comes into view (at least 25% visible), or the visitor clicks **Investment returns**, an "insight view" is counted for the listing. It is counted once per listing per page load, and bots and offline listings are ignored. The counter is stored in the listing's daily stats but is **not yet shown in any CRM report** (per code).
- Listing card impressions and contact-click tracking are unchanged.

### 2.6 Rate limits (per IP address)

- /api/compare and the property insights request: 60 per minute.
- Compare summary: 10 per minute and 100 per day.
- AI chat messages: 8 per minute and 150 per day (unchanged).
- Insight-view tracking: 60 per minute.

A blocked summary request shows "The summary isn't available right now…". A blocked chat message shows the chat error line.

## 3. Who can do what

| Action | Visitor | Customer | Agency / Agent | Admin |
|---|---|---|---|---|
| Add listings to compare, open /compare, share a compare link | Yes | Yes (list stays in the browser, not the account) | Yes (as website user) | Yes (as website user) |
| See Mortgage & Investment, Rental Costs, Market Insights | Yes | Yes | Yes | Yes |
| Change calculator inputs (not saved) | Yes | Yes | Yes | Yes |
| Use AI chat and change chat language | Yes (one free question, then details form) | Yes | Yes | Yes |
| See chat "Looking for" chips | No | No | CRM Lead Insights for their own leads (plan/KYC rules as before) | Super Admin in CRM |
| Edit currency rates | No | No | No | Admin > General Settings > Currencies |
| Turn the hourly rate sync on, or run it by hand | No | No | No | Developer / server setting only |
| Influence market figures | No | No | Yes, indirectly: their listings and Mark as sold / rented deals feed the peer data | Same |

## 4. Fields and validation

- **/compare URL:** `slugs` holds comma-separated listing slugs. Blanks and duplicates are dropped, each slug can be up to 255 characters, and only the first 5 are used. Unknown or offline slugs are skipped. `lang` is the site language.
- **Show prices in:** a dropdown of the active currencies ("CODE — Name"). Choosing one also changes the site-wide currency, which is remembered in the browser.
- **Only show differences:** a checkbox. **Show fee breakdown / Hide fee breakdown:** a toggle button under the Price section that lists each fee in the costs row.
- **Investment Calculator inputs** (money amounts are typed in the selected currency and stored in AED; text that cannot be read reverts to the previous value):

  | Field | Range / step | Notes |
  |---|---|---|
  | Expected annual rent | 0 to max(100,000, 25% of price) on the slider, step 1,000 | A typed amount may go above the slider maximum. |
  | Service charges (per year) | 0 to max(50,000, 3% of price), step 500 | A typed amount may go above the slider maximum. |
  | Maintenance (of price, per year) | 0–5%, step 0.1 | |
  | Vacancy (time empty per year) | 0–30%, step 1 | |
  | Down payment | residency minimum to 100% | Hint: "100% means buying in cash. Minimum {pct}% for this purchase." |
  | Interest rate | 1–10%, step 0.05 | Hidden at 100% down. |
  | Loan period | 1–25 yrs | Hidden at 100% down. |
  | Holding period | 1–30 yrs | |

  Changing residency resets the down payment to the new minimum and the rate to that residency's default.
- **Rental Cost Estimator inputs:**

  | Field | Range / step | Notes |
  |---|---|---|
  | Annual rent | 0 to max(500,000, 3 × listing's annual rent), step 1,000 | A typed amount may go above the slider maximum. |
  | Agency fee | 0–10%, step 0.5 | |
  | Number of cheques | 1, 2, 4, 6, 12, plus the listing's own count | |
  | Security deposit | amount | Badge shows % of rent; follows the rent until edited. |
  | Utility connection | Apartment / Villa / townhouse | |
- **Chat message:** required, up to 1,000 characters. The language code must be one of the 10 configured codes; anything else falls back to English.
- **Chat details form:** name, email and phone are all required, with the same validation as before.

## 5. Test scenarios

Priority: High = core flow, wrong numbers, or data shown to the wrong person; Medium = secondary behaviour; Low = cosmetic.

You need at least 6 live test listings: sale and rent; Dubai, Abu Dhabi and one with no emirate; one monthly rent; one with a stated security deposit; one priced ≥ AED 2,000,000; one villa. A community with 3 or more live listings of the same type is needed for Market figures.

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| CP-01 | Add and remove from compare | High | 1. Open /properties.<br>2. Click **Compare** on a card.<br>3. Click it again. | Step 2: the button shows "Comparing" with a tick, and the compare bar appears at the bottom with the thumbnail, name and ×, "Pick one more property to compare." and a greyed **Compare now**.<br>Step 3: the listing is removed and the bar disappears. |
| CP-02 | Limit of 5 | High | 1. Tick Compare on 5 listings (mix /properties and /commercial).<br>2. Tick a 6th. | After 5: "5 / 5 selected", no empty slots. 6th: not added, and the bar shows "You can compare up to 5 properties — remove one to add another." for a few seconds. |
| CP-03 | List persists | Medium | 1. Pick 3 listings.<br>2. Reload; open a second tab of the site; remove one in tab 2. | The bar still shows 3 after the reload. Tab 1 updates to 2 without a reload. |
| CP-04 | Compare from property page | Medium | 1. Open /property-details/{slug}.<br>2. Click the large **Compare** button next to Download Brochure. | Added to the bar; the label reads "Comparing". |
| CP-05 | Open comparison | High | 1. With 3 picked, click **Compare now**. | /compare?slugs=… opens; the bar is hidden on this page. One column per listing (photo, name link, location, ×) plus a **+ Add a property** column. Sections in order: Price & cost to buy, Investment (if any sale), The property, Location, Amenities, Ownership & residency (if any sale). |
| CP-06 | Buying cost maths | High | 1. Compare a Dubai sale listing at AED 1,000,000 with an Abu Dhabi sale listing.<br>2. Click **Show fee breakdown**. | Dubai: Buying costs AED 65,780 (40,580 + 4,200 + 21,000), Total cash needed AED 1,065,780, sub-text "Dubai fees". Abu Dhabi: 2% transfer + 2% + VAT agency. A listing with no emirate uses the Dubai fees. Golden Visa eligible is ✓ only for a sale priced at or above AED 2,000,000 (hint "from AED 2,000,000"); Foreign ownership text matches each emirate. |
| CP-07 | Rent vs sale in one comparison | Medium | 1. Compare a monthly rent (e.g. 10,000/month) with a sale listing. | The rent column's Price is AED 120,000 "per year · advertised AED 10,000 monthly". Move-in costs = 5% + VAT agency fee + deposit. No best-value highlight in the price and cost rows (mixed purpose). Investment rows show "—" for the rent column. |
| CP-08 | Currency and FX rows | High | 1. On /compare choose USD in **Show prices in**.<br>2. Choose INR, then back to AED. | USD: all amounts converted; toolbar "1 AED = x USD · rates as of <date>"; extra row **With conversion margin** (+1.5%); notes **Currency conversion** and **Paying in USD**. INR: Paying in INR note about the Liberalised Remittance Scheme. AED: the rate line, margin row and FX notes disappear. The header currency changes too. |
| CP-09 | Only show differences / empty rows | Medium | 1. Tick **Only show differences**. | Rows with identical values (e.g. same Property type) are hidden; empty sections vanish. Rows where all cells are "—" never show, even when the box is unticked. |
| CP-10 | Summary with and without AI | High | 1. Open a comparison on a server with AI configured.<br>2. Repeat on a server without AI, or ask a developer to disable it. | With AI: badge **AI summary**, a short overview, 4–6 points, the disclaimer and "Best for: …" under the columns, in the chosen currency. Without AI: badge **Quick summary** with fixed sentences (e.g. "… has the lowest asking price …") and "Best for" labels such as "Lowest budget". If the request fails: "The summary isn't available right now — the full comparison is below." |
| CP-11 | Shared link, bad input, empty states | Medium | 1. Open /compare?slugs=a,a,b,c,d,e,f with valid slugs plus one unknown slug.<br>2. Open /compare with an empty bar.<br>3. Remove columns until 1 is left. | 1: duplicates and the unknown slug are skipped, at most 5 columns, and the bar is replaced with these listings. 2: "You haven't picked any properties to compare yet…" with **Browse properties**. 3: "Add at least one more property to compare." |
| IN-01 | Tabs per listing type | High | 1. Open a sale listing, a yearly rent, a monthly rent, a weekly/daily rent and a listing with no price. | Sale: **Mortgage & Investment** + **Market Insights**. Yearly and monthly rent: **Rental Costs** + **Market Insights**. Weekly/daily: Market Insights only. No price: none of the three. The mobile tab dropdown matches. |
| IN-02 | Calculator switch | Medium | 1. On a sale listing open Mortgage & Investment.<br>2. Click **Investment returns**, change the rent, then **Monthly payments** and back. | The title changes between "Mortgage Calculator" and "Investment Calculator". Inputs are kept when switching back. Opening another listing starts on Monthly payments. |
| IN-03 | Investment maths | High | 1. Sale listing AED 1,000,000.<br>2. Set Down payment 100%, rent 60,000, service charges 0, maintenance 1%, vacancy 5%. | Interest rate and Loan period are hidden. Gross yield 6.00%. Net yield about 4.41%. Monthly cash flow about +AED 3,917 (green). Total purchase costs AED 65,780. Scenario table includes "Cash (100%)" highlighted. |
| IN-04 | Investment defaults and fallback | Medium | 1. Open a sale listing in an area with no rentals.<br>2. Edit the rent.<br>3. Switch residency to Non-resident. | Rent = 6% of the price, with an "Assumption" tag and the fallback hint. After editing: "Your figure." Non-resident: down payment jumps to 40% and the rate to 4.19%. A listing with no size: service charges 0 with the "Size not listed…" hint. |
| IN-05 | Rental cost maths | High | 1. Dubai yearly rent AED 100,000, unfurnished apartment, no stated deposit, cheques 1. | Cash needed to move in AED 112,470. Rows: Security deposit 5,000, Agency fee (5% + VAT) 5,250, Ejari/Tawtheeq 220, Utility 2,000. Housing fee 5% = 5,000. Total for the year AED 110,470. Deposits back 7,000. Changing cheques to 4: First rent payment 25,000. |
| IN-06 | Rental variants | Medium | 1. Monthly rent listing.<br>2. Listing with a stated deposit.<br>3. Furnished listing.<br>4. Abu Dhabi and Sharjah listings.<br>5. Villa. | 1: annual = ×12, hint "Listed per month — shown here for 12 months.", cheques default 12. 2: the deposit equals the listing's, with hint "As stated on this listing…". 3: deposit 10%. 4: housing fee 3% / row hidden. 5: Villa / townhouse selected, utility 4,000. |
| IN-07 | Market Insights with data | High | 1. Open a listing in a community with ≥ 3 live peers of the same type (and, if available, closed deals). | Badge and signals with source tags ("Estimate · N listings" / "Verified · N deals"); stat tiles; trend chart with legend and hover tooltip; recent deals table with same-bedroom rows highlighted; disclaimer. A positive headline also shows as a green pill at the top that jumps to Market Insights. |
| IN-08 | Market Insights without data | Medium | 1. Open a listing whose type/community has fewer than 3 peers. | "There aren't enough similar listings in this area yet…", tiles show "Not enough data yet" / "No closed deals yet", trend empty text, recent deals empty text. No green pill at the top. No error on the page. |
| IN-09 | Similar Properties | Medium | 1. Open a 2-bed Dubai Marina apartment for sale at AED 2M.<br>2. Check Similar Properties. | Listings shown are for sale; same-type 1–3 bed listings at AED 1.4M–2.6M in the same community come first. If fewer are found, the list is topped up with same-type or same-community listings (max 6). |
| CU-01 | Rates as of | Medium | 1. Admin changes a currency rate in General Settings > Currencies.<br>2. Within 10 minutes reload /compare in that currency. | The new rate is used, and "rates as of" shows the time of the change. |
| CU-02 | Auto sync off (default) | High | 1. On a server without CURRENCY_RATES_AUTO_SYNC=true, note a rate Admin typed in.<br>2. Check again after more than 1 hour. | The rate is unchanged (not overwritten). |
| CU-03 | Auto sync on / manual run | Medium | 1. Ask a developer to run `currencies:sync-rates` on the test server (or enable the hourly sync). | Output "Updated N currency rate(s)."; active non-AED rates match the feed; AED stays 1; a currency missing from the feed keeps its rate with a warning line. The website converts with the new rates within 10 minutes. |
| CU-04 | Currency in calculators | Medium | 1. Switch the header currency to USD on a property page. | Investment and Rental cost amounts, chart and tiles show USD. Typed amounts are read as USD (e.g. typing 10,000 rent stays 10,000 USD on screen). |
| CH-01 | New name and welcome | Medium | 1. Open the chat in a new private window. | Welcome "Welcome to MW Realty! I'm Noor, your personal property finder. Tell me what you're looking for…" (or the server's CHATBOT_NAME). The header shows a "EN ▾" language button. |
| CH-02 | Change language before chatting | High | 1. Click EN ▾, choose العربية. | Dropdown titled "Chat language" with 10 languages; العربية ticked after choosing. The welcome is replaced by the Arabic welcome (right-aligned). Placeholder in Arabic. The button shows "AR". The choice persists after a reload. |
| CH-03 | Change language mid-chat | High | 1. Ask a question in English.<br>2. Switch to Français.<br>3. Ask another question in English. | A confirmation line in French appears. The next AI reply is in French even though the question was in English. |
| CH-04 | Switch offer | Medium | 1. In an English chat, send "أبحث عن شقة في دبي مارينا".<br>2. Choose English on the bar.<br>3. Send another Arabic message. | A yellow bar asks in Arabic whether to continue in Arabic, with buttons العربية / English. Choosing English closes it; the Arabic offer does not come back until reload. Choosing العربية instead switches the chat. Short or ambiguous text (e.g. "hi") gives no offer. |
| CH-05 | Details form Not now | High | 1. As a new visitor, ask one question.<br>2. Type a second question and send.<br>3. Click × on "Want to keep chatting?". | Step 2: the form appears and the message is shown. Step 3: the form closes, the message is removed from the chat and is back in the input box. Sending again shows the form again. Filling the form sends the waiting message. |
| CH-06 | Match on cards | Medium | 1. Ask "2 bedroom apartment in Dubai Marina under 2M near a school".<br>2. Repeat in Arabic chat language. | Up to 6 residential cards, each with "N% match" and (English) reasons like "Within budget · 2 bedrooms". In Arabic only the percentage is shown, and card text is Arabic if the website has Arabic enabled. CRM > the lead's Insights shows "Looking for" chips for that chat. |
| CH-07 | Voice in chat language | Low | 1. In Chrome, set the chat to Deutsch; use the mic and turn on read-aloud. | Speech is recognised as German and replies are read with a German voice (browser support permitting). |
| OT-01 | Insight view tracking | Low | 1. Open DevTools > Network.<br>2. Scroll to Market Insights on a listing; scroll away and back. | One POST /track/insight-view (204) per listing per page load. Clicking **Investment returns** on a new listing also sends one. No visible change in CRM reports (not shown yet). |
| OT-02 | Arabic website | Medium | 1. Switch the website to Arabic.<br>2. Open a property page and /compare. | Property page tabs, calculators and Market Insights texts are in Arabic (translations exist). /compare labels and the compare bar/button appear in English (no Arabic texts for compare exist yet; see section 7). The layout is right-to-left; nothing overlaps. |
| OT-03 | Signup while signed in to CRM | Medium | 1. Sign in to the CRM as Agency.<br>2. In the same browser open /signup, fill Agent or Agency and submit.<br>3. On a fresh browser, type a licence number, trigger a validation error and check the field. | Step 2: the message "You're already signed in to an agent/agency account in this browser. Sign out first to create a new account." appears instead of a silent refresh. Step 3: the BRN / trade licence number you typed stays in the field. |
| OT-04 | Mobile width | Low | 1. At about 375 px open /compare, the compare bar, Market Insights and the chat language menu. | The table scrolls sideways inside its box (not the whole page); chart axis labels are thinned; calculator switch stacks under the title; the language menu stays inside the chat panel. |

## 6. Expected results checklist

- Compare holds 2–5 listings, survives reload, syncs across tabs, and its URL can be shared.
- /compare amounts match the fee rules for each emirate and rent annualisation; best cells are highlighted only when the comparison is fair.
- Currency choice converts every amount; the FX margin row and notes appear only for non-AED currencies.
- The summary shows **AI summary** or **Quick summary**, or a clear "not available" line; the table always works.
- Property pages show the right tabs for sale, long-term rent and short-stay listings.
- Investment and Rental Costs calculators give the example numbers in section 2.2 and keep inputs when switching.
- Market Insights shows source tags and handles thin data without errors; only positive headlines appear as the top pill.
- Currency rates are not overwritten unless auto sync is on; "rates as of" follows the last change.
- The chat replies in the chosen language, remembers it, offers switching once per language per page load, and the details form can be dismissed without losing the message.
- No console errors on any of these pages.

## 7. Known behaviour (not bugs)

- The compare list is stored in the browser only. Signing in, or using another device, does not carry it over.
- Opening a shared /compare link replaces the visitor's own compare list.
- Listings that are offline, or slugs that do not exist, are silently left out of /compare.
- Compare toggles are on the list cards of /properties, /commercial, /premium-properties and /marketing-properties (all share the same two page components) and on the property page. They are not on Home sections, agent/agency profiles, Similar Properties or chat cards.
- On /compare a rent's Price is yearly, so it can differ from the advertised monthly price on the card.
- The Investment Calculator always uses Dubai purchase fees (it shares the mortgage calculator's fee list), while /compare uses the listing's emirate. Abu Dhabi or Northern Emirates totals can therefore differ between the two (per code; confirm with the product owner whether this is intended).
- Market figures are estimates from MW Realty's own listings and deals, not DLD data. In thin areas tiles show "Not enough data yet". Figures can stay up to 1 hour old unless the listing is edited.
- Neutral or caution assessments (e.g. "Priced above market") appear only inside Market Insights, never as the pill at the top.
- **Compare** texts (page, bar, button) have no entries in resources/lang/cms-static/en.json or ar.json, so they show built-in English on every site language. The chat language menu title, its button tooltip and the × label of the details form are the same. The chat's own lines (welcome, placeholder, switch offer, errors) come from config/chatbot.php in all 10 languages.
- The chat's match reasons are English only. In other chat languages only "N% match" is shown.
- Language detection for the switch offer is a best guess. Short messages, mixed languages, or Latin-script languages other than English, French, German and Spanish do not trigger an offer.
- A declined language offer can come back after a page reload.
- The chat language is stored on the conversation but is not shown in the CRM transcript.
- Insight views are counted but not yet visible in any report.
- AI answers vary from run to run. Check language, tone and that listings are real, not exact wording.
- **Test data cautions:**
  - Use test listings only.
  - Do not turn on CURRENCY_RATES_AUTO_SYNC on a server where Admin's hand-set rates matter.
  - Respect the AI rate limits (chat 8/min and 150/day; compare summary 10/min and 100/day per IP). A burst of tests from one office IP can hit them.
  - Do not run tests or migrations against a shared database.

## 8. Reporting a bug

Include:
- the page URL (for /compare, the full ?slugs= link) and the listing slugs or reference numbers;
- each listing's purpose, emirate, price, rental period and size;
- website language, selected currency and chat language;
- whether the summary badge read AI summary or Quick summary;
- for calculators, every input value and the expected vs actual figure (with your working);
- for Market Insights, the source tags and counts shown;
- for chat, the exact messages sent and received and whether the visitor had filled the details form;
- browser and device, time (UAE), and a screenshot or screen recording with the Network tab open for failed requests.

Mask real visitor names, emails and phone numbers.

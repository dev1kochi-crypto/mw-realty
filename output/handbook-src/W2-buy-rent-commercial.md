# Website: Buy, Rent & Commercial

Property search for sale, rent and commercial, map views, Premium and Realty lists, the property details page, Compare and the market insight tabs.

**Where:** /properties?listing_type=sale (Buy), /properties?listing_type=rent (Rent), /commercial, the /map views, /premium-properties, /marketing-properties, /property-details/{slug}, the floating compare bar and /compare. **Who:** Everyone; no sign-in needed except for the wishlist and Save Search. **Locks:** Only live listings appear. The AI summary on /compare needs an AI provider on the server.

## 1. What this page covers

Buy and Rent are the residential search; Commercial has its own filter set. Each list has a map view. The property page shows the listing, its permit, nearby places and contact forms, which create leads in the CRM. Visitors can compare up to 5 listings side by side, and the property page shows mortgage, investment, rental-cost and market insight tabs.

| Page / menu | URL | What it does |
|---|---|---|
| Header | every page | Logo, menu (Home, Buy, Rent, Commercial, Agents, Agencies, Market Insights, Calculators ▾, Explore ▾), Login or account menu, language and currency switchers, mobile menu button. |
| Footer and mobile bottom bar | every page | Link columns, contact details, newsletter, social links. On phones there is a bottom bar: Home, Buy, Search, Rent and Login / Profile. |
| Buy / Rent | /properties?listing_type=sale or rent | Residential listings with filters, sorting, numbered pages, grid or list layout, Save Search, Clear Filters, wishlist hearts and Compare. |
| Properties map | /properties/map | The same filters, with results as map pins. |
| Commercial | /commercial, /commercial/map | Commercial listings with their own filter set; list or map. |
| Premium | /premium-properties (+ /map) | Every listing currently featured as Premium, residential and commercial together. |
| Realty Properties | /marketing-properties (+ /map) | The Super Admin's Marketing Properties list, in its own order (the Home "Realty Property" view-all). |
| Property details | /property-details/{slug} | Gallery, details, tabs, floor plans, location and nearby places, permit block, agent card with Call / WhatsApp / Email, enquiry form, Book a Viewing, brochure and floor-plan downloads, Similar Properties. |
| Market Insights | /market-insights, /market-insights/{slug} | Research reports: featured report, topic and market filters, report pages. |
| Calculators menu | /mortgage-calculator, /eligibility-calculator, /rent-vs-buy-calculator, /refinance-calculator | The four calculators, each with FAQs and an enquiry form. |
| Legal pages | /terms-and-conditions, /privacy-policy, /security-policy, /cookie-settings | Admin-managed text pages. |
| Thank-you page | /thank-you | Shown after the Contact, home, calculator, property-enquiry and agent/agency request forms. |
| Landing pages | /{slug} | Admin landing pages (Blog design or custom HTML). |
| 404 page | any unknown URL | "Page Not Found" with a search box and popular links. |
| SEO files | /sitemap.xml, /robots.txt, /llms.txt | Static files generated from Admin > SEO. |

## 2. How it works (business rules)

### 2.1 Buy / Rent / Commercial, map views, Premium and Marketing lists

**Residential list (/properties, used by Buy, Rent, /premium-properties and /marketing-properties)**

- **Which filters show:** this comes from Admin > Filters > Search Filters. Premium has its own set; Properties, Marketing and the agent/agency "View All" lists share the Properties set.
  - **Top bar:** Purpose ("Any", Sale, Rent), Location, Property Type ("All Type"), Bedrooms, Bathrooms, the filter (funnel) button and **Search**. A top-bar field shows only while Admin has that filter as a dropdown or radio buttons. In other styles it moves into the panel.
  - **Toolbar:** completion tabs (**All**, Ready, Off Plan), the Sort dropdown, Grid / List / Map buttons, **Save Search**, and **Clear Filters** with a count badge. Hovering Clear Filters shows the applied filters ("Label: value · …").
  - **More filters panel** (funnel button): Price (slider with Min / Max read-outs, "Quick select" presets), Area (Min / Max sq.ft), any other Admin filter in Admin's order and style, **Features** (Floor plans, Open house properties), **Amenities** (built-in: Furnished, Community pool, Parking, Gym, Security, Balcony, Concierge, Private pool), and **Reset** / **Apply**.
- **When filters apply:** top-bar dropdowns, completion tabs and Sort apply at once. The Location box applies on **Search** or Enter. The panel applies on **Apply**.
- **Price presets:** Under 500K, 500K–1M, 1M–2M, 2M–5M, 5M–10M, 10M+ (or Admin's presets). In a non-AED currency the labels are rebuilt from the converted amounts, e.g. "Under USD 136K".
- **Sort:** Recommended (featured first, then CRM display order), Most Recent, Price: Low to High, Price: High to Low, Most Popular (by number of leads). Admin can rename, reorder or remove these. /marketing-properties keeps the Super Admin's order under Recommended.
- **Paging:** numbered pages with **Previous** / **Next**, **12 listings per page**. There is no infinite load. Changing page scrolls up to the list.
- **Headings:**
  - The page title is "Properties in Dubai", or "Properties in {place}" when a location is applied.
  - The list heading describes the search, e.g. "Penthouse Properties for Rent in Dubai", "Off-Plan Properties for Sale in Abu Dhabi", "All Properties in Dubai", or "Properties for sale in dubai" (Buy with no other filter). The count line reads "{total} …".
- **URL:** the applied filters, sort and features are written to the URL, so a reload or a shared link keeps them. Old links still work: ?category=sale|rent becomes the Purpose, category=off_plan becomes Off Plan, completion= becomes completion_status=, and min_area/max_area become min_sqft/max_sqft.
- **Ticker:** under the toolbar (list view only) a moving strip shows "Recommended Searches" with "{N} Bedroom Properties for sale in dubai" (N = 1–6, opens /properties?bedrooms=N) and "Invest in Off Plan Properties" with four links (see section 7).
- **Cards:**
  - photo slider and photo count, type tag, heart, price, **Compare** (W1), title, location, beds / baths / area;
  - Email / Call Us / WhatsApp buttons (fixed company contacts);
  - on Premium, Marketing and agent/agency lists, a **Residential** / **Commercial** tag.
- **Empty results:** "No Properties Available" / "No properties match your search right now — try adjusting your filters or broadening your criteria." with a **Clear Filters** button.
- **Ad banner:** under the list (not on the map), with placements properties-dubai, premium-properties and marketing-properties.

**Commercial (/commercial)**

- **Filter set:** Admin > Filters > Search Filters > Commercial.
  - **Top bar:** Location (commercial suggestions only), **Properties** keyword box, Property Type (with counts, e.g. "Office (12)"), Category ("Buy", "Lease", "Off-Plan"), the filter button with a badge counting panel filters, and **Search**.
  - **Panel:** Price, Bathrooms (Any, 1–5+), Area, Completion (Any, Ready, Off-Plan), other Admin filters, **Sort by** (Recommended, Most recent, Price: low to high, Price: high to low), Amenities (Furnished, Parking, Pantry, Meeting rooms, Metro, Security, Central AC, Loading bay, Gym, Concierge), **Reset** / **Apply**.
- **Paging:** **6 listings per page**; the count line reads "{count} commercial properties".
- **URL:** links into /commercial with a ?query work, but any filter change clears the URL. A reload after changing filters starts again unfiltered (unlike /properties).
- **Cards:**
  - Verified badge, "For Rent" or "Buy Now" pill, service-charge line, price, title, location, short description, "Floor:" and "RERA:" lines, stats, **Compare**, up to 4 amenities plus "+N more";
  - Call Us / WhatsApp / Email (fixed company contacts);
  - **No wishlist heart** on commercial cards.
- **Empty results:** "No Properties Available" / "No commercial properties match your search right now — try adjusting your filters or broadening your criteria."

**Map views (/properties/map, /commercial/map, /premium-properties/map, /marketing-properties/map)**

- Grid / List and Map buttons switch views and carry the current filters across. The map has no title or ticker.
- **Area and limit:** the map starts on Dubai. Pins load only for the visible area (up to 500), and reload as the visitor pans or zooms.
- **Pins:** one pin per address. A pin shows the price, or "On request". Several listings at one address become a "{N} Properties" pin that opens a list ("properties at this address", **All properties here**). Pins cluster when zoomed out. Premium listings have a highlighted pin.
- **Messages:** "No properties in this area", "No properties match your filters", "Show all results", "zoom in to see all", "Loading properties…", and the **Back to Dubai** button. An "i" button shows the map credits.

**Save Search, wishlist, Custom Property Request**

- **Save Search:**
  - A visitor who is not signed in is sent to /login.
  - A signed-in Customer: the button reads "Saving…", then "Search Saved ✓" for 2.5 s. On failure it reads "Couldn't save — try again".
  - The saved title is the list heading (plus " · N Bed"). The criteria are the current URL query.
  - The search appears in /profile > Saved Searches. Saving the same search again creates another entry (no duplicate check seen in code).
- **Wishlist heart:**
  - Visitors are sent to /login.
  - Customers toggle the heart (filled = saved). The state is shared across every page in the session: Home sliders, /properties, agent and agency listings, and /profile > Wishlist.
- **Custom Property Request:**
  - The form that used to be on /properties ("Custom Request") was removed from the listing page in September 2026. The form with this title now lives only on agent and agency profiles (2.5).
  - The server endpoint for the listing-page version (/leads/custom-request, unassigned lead for the Super Admin) still exists and is used by the mobile app API only.
  - The project overview ((guide 00)) still lists it on /properties; that line is out of date.

### 2.2 Property details (/property-details/{slug})

- **Who can open it:** only live listings. An offline or unknown slug shows "Property Not Found" / "This property listing doesn't exist or is no longer available." with **Back to Properties**. The browser tab title becomes "{listing name} |tab title is the listing name followed by "MW Realty".
- **Top of the page:**
  - breadcrumb Home / Properties / name, and a photo slider (short galleries are repeated so the slider always moves);
  - badges: **For Sale** / **For Rent**, **Premium**, **Upgraded**, a green market pill (W1), and "Open house · {first date} +N" when open-house days exist (click jumps to the dates);
  - beds, baths and "Published" date;
  - **price label:** "Starting Price" for sale; "Rent · {period}" for rent, or "Yearly Rent" when no period is set. Without a price: "Price on request";
  - **Download Brochure** (only when a brochure exists) and the large **Compare** button (W1).
- **Tabs:** they scroll to sections. On phones they are a dropdown with the same options. A tab shows only when its section has content.

  | Tab | Shown when | Content |
  |---|---|---|
  | Overview | always | Description (or "A {type} located in {location}."), Key features, Property details pills (Permit/RERA ID, Status, Type, Bedrooms, Bathrooms, Location, Size, and when filled: Category, Furnishing, Parking, Garage, Floor, View, Developer, Year built "· N years old", Direct from owner, Emirate), Video tour / 360° virtual tour links. |
  | Price & Availability | cheques, deposit, rental period or availability is set | Price, Number of cheques, Security deposit, Status, "Open house & viewing days" (date buttons, **Book viewing**) or "Available immediately" / "No upcoming open house dates — ask the agent for a private viewing.", **Book a Viewing**. |
  | Features & Amenities | any amenities, easy access or highlights | Groups Amenities, Easy access, Highlights. |
  | Floor Plans | the listing has floor plans | Plan list (label, size, price range "AED 240,000 – 264,000"), a large drawing that opens zoomed, **Download Floor Plan** when a downloadable plan exists. |
  | Gallery | always | Main photo plus up to 4 thumbnails; the last shows "+N photos". Opens a lightbox. |
  | Location & Nearby | the listing has a map point or full address | Address; a map with the listing pin; chips for each nearby-place type with an "All" option; places listed with their distance. |
  | Mortgage & Investment, Rental Costs, Market Insights | see W1 (AI chat and currency) | Covered in W1 (AI chat and currency). |

- **Regulatory information** (permit block, below the tabs): rows for Reference, Listed, the permit number (labelled "{issuer} permit number" when the issuer is known), Broker License, Agency name, Zone name and Agent License. Also **Verify this ad — {issuer}** (opens the issuer's site in a new tab) and the permit QR code (hidden if the image fails). See Module 09 for how permits are set.
- **Sidebar:** it stays in view while scrolling.
  - **"Listed by" card:** photo, name, Agent / Agency tag, "Serves in Dubai", years of experience, badges and "Preferred areas", plus **Call**, **WhatsApp** and **Email** (each only when the agent has that detail) and **View profile** / **View listings**.
  - **"Part of" block:** for an agency agent, the agency name with Agency listings and mini call / email buttons.
  - Every Call / WhatsApp / Email click is counted as a lead click for the listing's CRM statistics.
- **Enquiry form "Enquire About This Property":**
  - The message is pre-filled with `I'm interested in "{name}" — please share more details.`
  - **Send Enquiry** creates a lead in the CRM of the listing's agent or agency. If the visitor (matched by browser, email or phone) is already another account's lead, that account keeps them.
  - The visitor then lands on /thank-you, which names the property and who has the lead (agent or agency, with Call / WhatsApp).
  - Focusing the form counts one "enquiry" click.
- **Book a Viewing** (button under the form, or a date button): a popup with:
  - "Choose a day": the next 14 days, or only the listing's open-house days when it has any;
  - "Preferred time": Morning 9 AM – 12 PM, Afternoon 12 PM – 4 PM, Evening 4 PM – 7 PM;
  - Name, Email, Phone, and "Note for the agent (optional)";
  - **Request Viewing**.

  On success: "Viewing requested", the chosen day and time, "The agent will contact you shortly to confirm the exact time." and **Done**. The lead goes to the listing's agent or agency with the message `Viewing request for "{title}" — {day, date}, {slot}.`
- **Brochure and floor-plan downloads:**
  - Popup "Get the property brochure" / "Share your contact details to access the brochure." (or "Get the floor plan" / "Share your contact details to download the floor plan."), with Name, Email, Phone (all required) and **Continue to download**.
  - Then "Preparing…", "Downloading… N%" with a progress bar, then "Download complete — check your downloads folder." The popup closes by itself after about 1.8 s.
  - A lead is saved first ("Requested the brochure for …"). The file then comes from a short-lived signed link.
  - The form is emptied after each download, so a second download asks again.
- **Similar Properties:** a slider under the page (rules in W1 (AI chat and currency)). An ad card sits in the sidebar (placement property-details).

### 2.3 Compare

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

### 2.4 Property insights (property details page)

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

### 2.5 Rate limits (per IP address)

- /api/compare and the property insights request: 60 per minute.
- Compare summary: 10 per minute and 100 per day.
- AI chat messages: 8 per minute and 150 per day (unchanged).
- Insight-view tracking: 60 per minute.

A blocked summary request shows "The summary isn't available right now…". A blocked chat message shows the chat error line.

## 3. Who can do what

| Action | Visitor | Customer | Agency / Agent |
|---|---|---|---|
| Browse every public page, filter, sort, map view, compare | Yes | Yes | Yes (as a visitor) |
| Wishlist heart, Save Search | No (sent to /login) | Yes | No (a CRM sign-in is not a Customer session; sent to /login) |
| Send property enquiry, Book a Viewing, download brochure / floor plan | Yes (contact details required) | Yes (linked to the account when signed in) | Yes (as a visitor) |
| Custom Property Request on an agent / agency profile | Yes | Yes | Yes (as a visitor) |
| Receive the resulting leads | No | No | Yes: the listing's or profile's agency / agent in the CRM |
| /profile (wishlist, saved searches, enquiries, settings) | No | Yes | No |
| Change website content, filters, calculators copy, SEO | No | No | No (Admin only; Marketing Properties and Premium choices come from the CRM) |

| Action | Visitor | Customer | Agency / Agent | Admin |
|---|---|---|---|---|
| Add listings to compare, open /compare, share a compare link | Yes | Yes (list stays in the browser, not the account) | Yes (as website user) | Yes (as website user) |
| See Mortgage & Investment, Rental Costs, Market Insights | Yes | Yes | Yes | Yes |
| See chat "Looking for" chips | No | No | CRM Lead Insights for their own leads (plan/KYC rules as before) | Super Admin in CRM |
| Influence market figures | No | No | Yes, indirectly: their listings and Mark as sold / rented deals feed the peer data | Same |

## 4. Fields and validation

**Shared rules (all lead and contact forms)**
- **Email:** must look like name@domain.tld. Messages: "Please enter your email address." and "Please enter a valid email address.". Server limit 255 characters.
- **Phone:** typed **without** the country code. A flag picker sets the code (default +971).
  - Only digits, spaces and dashes are allowed: "The phone number may only contain digits.".
  - 7–13 digits: "The phone number must be 7–13 digits (without the country code)." (the server wording is "… 7 to 13 digits …").
  - When required: "Please enter your phone number.".
- **Name:** "Please enter your name." on the property enquiry, viewing, download, contact and calculator forms. Server limit 255.
- **Server errors:** the first error shows in the form's message line; otherwise "Something went wrong — please try again.".

| Form | Fields (required = *) | Limits and messages |
|---|---|---|
| Contact page | Name *, Email *, Phone, Message * | "Please enter a message."; Message up to 2,000. |
| Property enquiry | Name *, Email *, Phone, Property (shown), Message * (pre-filled) | Server: message up to 2,000. Button "Send Enquiry" ("Sending…"). |
| Book a Viewing | Day *, Time *, Name *, Email *, Phone *, Note | "Please pick a day.", "Please pick a time."; Note up to 1,000. Server: date from today up to 60 days ahead; with open-house days: "Please pick one of the open house days for this property." |
| Brochure / floor-plan download | Name *, Email *, Phone * | Same shared rules. Button "Continue to download" ("Preparing…"). |
| Custom Property Request (agent / agency) | First Name *, Last Name, Email *, Phone, Property Category (Apartment, Villa, Townhouse, Penthouse, Commercial), Specification, Price Range, Area (sqft), Preferred Location, Additional Details, Move-in Timeline (Immediate, 1–3, 3–6, 6–12 Months, Flexible), Furnishing Status (Furnished, Unfurnished, Semi-Furnished, No Preference) | Missing first name: "First Name is required." Server: category, area and timeline up to 100; details up to 2,000; others up to 255. Button "Submit Request" ("Submitting…"). |
| Calculator enquiry / unlock popup | Name *, Email *, Phone, Message (enquiry only) | Message up to 2,000. Up to 20 calculation lines of up to 150 characters each. Buttons "Get a mortgage quote" / "Continue" ("Sending…"). |

**Rate limits (per IP address):**
- property enquiry, viewing, downloads and agent / agency requests: 20 per hour together (lead-capture);
- contact, home and calculator forms, newsletter, career applications and forgot / reset password: 20 per hour each (form-submit);
- sign-up: 10 per hour;
- email code verify / resend: 8 per minute and 30 per day.
A blocked request shows the server's "Too Many Attempts." text or the generic error.

- **/compare URL:** `slugs` holds comma-separated listing slugs. Blanks and duplicates are dropped, each slug can be up to 255 characters, and only the first 5 are used. Unknown or offline slugs are skipped. `lang` is the site language.
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
- **Rental Cost Estimator inputs:**
  | Field | Range / step | Notes |
  |---|---|---|
  | Annual rent | 0 to max(500,000, 3 × listing's annual rent), step 1,000 | A typed amount may go above the slider maximum. |
  | Agency fee | 0–10%, step 0.5 | |
  | Number of cheques | 1, 2, 4, 6, 12, plus the listing's own count | |
  | Security deposit | amount | Badge shows % of rent; follows the rent until edited. |
  | Utility connection | Apartment / Villa / townhouse | |



## 5. Test scenarios

Priority: **High** core flow / data loss / access · **Medium** · **Low** cosmetic. Use fake names and example.com emails.

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| PS-01 | Buy and Rent lists | High | 1. Click Buy, then Rent in the header.<br>2. Check heading, count and cards. | Buy: only sale listings, heading "Properties for sale in dubai"; Rent: only rent listings, "Properties for Rent in Dubai". The count line shows the total. 12 cards per page; each card has photos, type tag, heart, price, Compare, title, location, stats. |
| PS-02 | Top-bar filters | High | 1. On /properties set Purpose, Property Type, Bedrooms, Bathrooms one by one.<br>2. Type a location, pick a City suggestion, press Search. | Each dropdown applies at once and the URL updates. A picked city filters exactly (?city=) and the title becomes "Properties in {city}"; typed text uses ?location=. Heading reflects the filters, e.g. "Villa Properties for Sale in Dubai". |
| PS-03 | More filters panel | High | 1. Open the funnel; set a price preset, Area min 1,000 / max 2,000, tick Floor plans and an amenity; **Apply**.<br>2. Reopen; click **Reset**. | Results meet every filter; the Clear Filters badge counts them and its tooltip lists "Price: …", "Area: 1000 – 2000 sq.ft", "Feature: …", "Amenity: …". Reset clears everything and reloads all listings. |
| PS-04 | Sort and paging | Medium | 1. Sort by Price: Low to High, then High to Low, Most Recent, Most Popular.<br>2. Go to page 2 and the last page with **Next**/**Previous**. | Prices follow the chosen order (listings without a price at an end). Sort is in the URL (?sort=). Paging keeps the filters, scrolls up to the list, and Previous / Next are disabled at the ends. No infinite load. |
| PS-05 | Clear filters and empty results | Medium | 1. Filter to something with no matches (e.g. 6 bedrooms + a tiny price range).<br>2. Click **Clear Filters** in the empty box. | "No Properties Available" / "No properties match your search right now — try adjusting your filters or broadening your criteria." Clear Filters resets all filters and sort and shows all listings. |
| PS-06 | Shared links and old links | Medium | 1. Apply filters, copy the URL, open it in a new window.<br>2. Open /properties?category=sale&min_area=500 and /properties?completion=off_plan. | 1: the same filters, sort and results. 2: old keys are understood: Purpose Sale, Area min 500, Off Plan tab selected. |
| PS-07 | Map view | High | 1. On /properties?listing_type=rent click the Map button.<br>2. Pan and zoom; click a single pin and a "{N} Properties" pin.<br>3. Click the Grid button. | /properties/map opens with the same filters, centred on Dubai, pins showing prices or "On request". Pins reload for the visible area; clusters split on zoom; a group pin lists its listings. The card links to the property. Grid returns with the filters kept. "Back to Dubai" recentres. A filter with no matches shows "No properties match your filters". |
| PS-08 | Save Search | High | 1. Signed out, click **Save Search**.<br>2. Sign in as the Customer, filter Rent + 2 bedrooms, Save Search.<br>3. Open /profile > Saved Searches > **View Results**. | 1: redirected to /login. 2: "Saving…" then "Search Saved ✓" for about 2.5 s. 3: the entry is listed with its title and View Results reopens /properties with the same filters. |
| PS-09 | Wishlist hearts | High | 1. Signed out, click a heart on /properties.<br>2. As the Customer, heart a listing on /properties; check Home and the agent profile for the same listing; open /profile > Wishlist; remove it there. | 1: sent to /login. 2: the heart is filled everywhere the listing appears; Wishlist lists it; removing it unfills the hearts. |
| PS-10 | Commercial list | High | 1. Open /commercial; use Location, Properties keyword, Property Type (with counts), Category (Buy / Lease / Off-Plan).<br>2. Open the panel: price, bathrooms, area, completion, sort, amenities; Apply.<br>3. Reload the page. | Only commercial listings, 6 per page, "{count} commercial properties". Filters work; the filter button shows a badge for panel filters. After any change the URL query is cleared, so the reload shows the unfiltered list. Cards have Verified badge, For Rent / Buy Now pill, Floor and RERA lines, amenities "+N more", Compare, and no heart. Map button opens /commercial/map with the filters. |
| PS-11 | Premium and Realty lists | Medium | 1. Open /premium-properties and /marketing-properties.<br>2. Filter each by Purpose. | Premium shows every featured listing (residential and commercial, each tagged); Realty shows the Marketing list in its saved order with the CMS eyebrow/intro. Headings e.g. "All Premium Properties", "Premium Properties for Sale". Map views work for both. |
| PS-12 | Filters on a phone | Medium | 1. At 375 px open /properties.<br>2. Open the funnel panel. | The top bar keeps only the funnel and Search; the panel holds Location, Purpose, Property Type, Price, Bedrooms, Bathrooms, Area, Features and Amenities. Apply works; completion tabs turn into a dropdown. Cards stack one per row with no sideways scroll. |
| PD-01 | Page header and details | High | 1. Open a sale listing, a monthly rent listing and a listing with no price. | Breadcrumb Home / Properties / name; tab title is the listing name followed by "MW Realty". Badges For Sale / For Rent (+ Premium / Upgraded when set). Price label "Starting Price", "Rent · Monthly", or "Price on request". Property details pills show only filled values; Year built shows "· N years old". |
| PD-02 | Tabs per listing | Medium | 1. Open listings with and without floor plans, amenities, map point, cheques/deposit.<br>2. Click each tab; on a phone use the tab dropdown. | Only tabs with content appear; each scrolls to its section; the phone dropdown lists the same options. Mortgage & Investment / Rental Costs / Market Insights follow W1. |
| PD-03 | Gallery and floor plans | Medium | 1. Use the top slider arrows; open the Gallery tab and click "+N photos".<br>2. In Floor Plans click each plan and the drawing. | Slider moves (short galleries repeat). Lightbox opens with all photos. Each plan shows its size and price range in the selected currency; the drawing opens zoomed with its label as the caption. |
| PD-04 | Location and nearby places | Medium | 1. Open Location & Nearby on a listing with nearby places.<br>2. Click each type chip and "All". | Address line and map with the listing pin. Places of the chosen type are listed with distances and shown on the map. A listing without a map point but with an address shows the address only. |
| PD-05 | Enquiry form validation | High | 1. Clear the name and send.<br>2. Enter email "visitor@test" and send.<br>3. Enter phone "12ab".<br>4. Enter phone "123". | Messages in order: "Please enter your name.", "Please enter a valid email address.", "The phone number may only contain digits.", "The phone number must be 7–13 digits (without the country code)." Nothing is sent. |
| PD-06 | Enquiry reaches the right CRM | High | 1. On a listing owned by the test agent, send Name "Test Buyer", Email buyer.one@example.com, Phone 501234567 and the pre-filled message.<br>2. Check the thank-you page and the agent's CRM > Leads. | /thank-you: "Your enquiry about "{name}" has been sent.", "{agent name} will get in touch with you shortly.", "Sent to" card with Call / WhatsApp, **Back to Property**. The lead is in that agent's (or agency's) CRM with source property-detail. As a signed-in Customer it also appears in /profile > My Enquiries. |
| PD-07 | Book a Viewing | High | 1. Click **Book a Viewing** with no open-house days; submit without choosing.<br>2. Pick a day and Evening, fill details, Request Viewing.<br>3. On a listing with open-house days, click a date button. | 1: "Please pick a day." then "Please pick a time.". 2: "Viewing requested" with e.g. "Friday, 6 November 2026 · 4 PM – 7 PM" and **Done**; CRM lead message "Viewing request for "…" — …". The day list is the next 14 days. 3: only the open-house days are offered, the clicked one pre-selected. |
| PD-08 | Brochure and floor-plan downloads | High | 1. Click **Download Brochure**; send with an empty phone.<br>2. Fill all fields; **Continue to download**.<br>3. Click **Download Floor Plan** in Floor Plans. | 1: "Please enter your phone number.". 2: "Preparing…", "Downloading… N%", "Download complete — check your downloads folder."; the file saves; the popup closes by itself; a lead "Requested the brochure for …" is in the CRM. 3: the same popup titled "Get the floor plan" with an empty form. Buttons are absent when no brochure / downloadable plan exists. |
| PD-09 | Contact card and permit block | Medium | 1. Check "Listed by" on an agency agent's listing; click Call, WhatsApp, Email, View profile, View listings, the agency mini buttons.<br>2. Check Regulatory information on a listing with a permit. | Buttons appear only for filled details; WhatsApp opens wa.me with digits only. "Part of" shows the agency. Rows Reference, Listed, "{issuer} permit number", Broker License, Agency name, Zone name, Agent License; **Verify this ad** opens a new tab; the QR shows (or hides if broken). |
| PD-10 | Similar properties and not found | Medium | 1. Scroll to Similar Properties and use its arrows.<br>2. Switch the listing off in the CRM; reload its URL.<br>3. Open /property-details/no-such-listing. | 1: slider of related listings (W1 rules). 2 and 3: "Property Not Found" / "This property listing doesn't exist or is no longer available." with **Back to Properties**. |
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

## 6. Expected results checklist

- /properties filters, sort and paging (12 per page) agree with the results and survive a reload; /commercial (6 per page) filters work; map pins follow the filters and the visible area.
- Property pages show only tabs with content; enquiries, viewings and downloads create leads in the right agency or agent CRM and go to the right thank-you page.
- Compare holds 2–5 listings, survives reload, syncs across tabs, and its URL can be shared.
- /compare amounts match the fee rules for each emirate and rent annualisation; best cells are highlighted only when the comparison is fair.
- Currency choice converts every amount; the FX margin row and notes appear only for non-AED currencies.
- The summary shows **AI summary** or **Quick summary**, or a clear "not available" line; the table always works.
- Property pages show the right tabs for sale, long-term rent and short-stay listings.
- Investment and Rental Costs calculators give the example numbers in section 2.4 and keep inputs when switching.
- Market Insights shows source tags and handles thin data without errors; only positive headlines appear as the top pill.
- No console errors on any of these pages.

## 7. Known behaviour (not bugs)

- Listing cards on Home, /properties, /commercial and /profile > Wishlist have Email / Call Us / WhatsApp buttons that go to MW Realty's fixed company contacts, not the listing's agent, and are not counted as lead clicks. Only the property page contact card uses the agent's details. Confirm with the product owner whether this is intended.
- /commercial clears the URL after any filter change, so a reload or shared link after filtering shows the unfiltered list. /properties keeps filters in the URL.
- Commercial cards have no wishlist heart.
- Saved searches made on /commercial reopen on /properties ("View Results" always goes to /properties), so the results do not match the commercial search (per code; looks like a bug).
- The project overview ((guide 00)) lists the Custom Property Request form on /properties; that is out of date.
- The compare list is stored in the browser only. Signing in, or using another device, does not carry it over.
- Opening a shared /compare link replaces the visitor's own compare list.
- Listings that are offline, or slugs that do not exist, are silently left out of /compare.
- Compare toggles are on the list cards of /properties, /commercial, /premium-properties and /marketing-properties (all share the same two page components) and on the property page. They are not on Home sections, agent/agency profiles, Similar Properties or chat cards.
- On /compare a rent's Price is yearly, so it can differ from the advertised monthly price on the card.
- The Investment Calculator always uses Dubai purchase fees (it shares the mortgage calculator's fee list), while /compare uses the listing's emirate. Abu Dhabi or Northern Emirates totals can therefore differ between the two (per code; confirm with the product owner whether this is intended).
- Market figures are estimates from MW Realty's own listings and deals, not DLD data. In thin areas tiles show "Not enough data yet". Figures can stay up to 1 hour old unless the listing is edited.
- Neutral or caution assessments (e.g. "Priced above market") appear only inside Market Insights, never as the pill at the top.
- **Compare** texts (page, bar, button) have no entries in resources/lang/cms-static/en.json or ar.json, so they show built-in English on every site language. The chat language menu title, its button tooltip and the × label of the details form are the same. The chat's own lines (welcome, placeholder, switch offer, errors) come from config/chatbot.php in all 10 languages.
- Insight views are counted but not yet visible in any report.
- **Test data cautions:**
  - Use fake names and example.com emails only.
  - Every enquiry, viewing, download and request creates real leads, enquiries and admin emails, so tell the receiving agency or agent and clean up afterwards.
  - Respect the rate limits: 20 leads and 20 forms per hour per IP, 10 sign-ups per hour, 5 logins per minute. A test burst from one office IP can hit them.
  - Do not change Admin home content or calculator copy on a shared server without warning the team.
  - Do not run tests or migrations against a shared database.

## 8. Reporting a bug

Include:
- the full URL, including the query string (filters, ?price=, ?agent=);
- website language, selected currency, signed-in state (visitor or Customer) and screen width;
- for listings: slug or reference number, purpose, emirate and price;
- for forms: every value entered (fake data only), the exact message shown, and where the record was expected (CRM account, Admin > Enquiries, Candidates);
- for calculators: every input, the expected figure with your working or the Property Finder figure for the same inputs, and the date of the test (Refinance);
- for Admin-driven content: what was changed in Admin and when;
- browser and device, time (UAE), and a screenshot or recording with the Network tab open for failed requests.

Mask real visitor names, emails and phone numbers.

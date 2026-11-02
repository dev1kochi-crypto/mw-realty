# Website: Home, Search, Agents, Agencies, Calculators & Explore

Every public page in the header menu: what is on it, where its content comes from, which forms it has and where those forms go.

**Where:** Public website, in header-menu order: header and footer, Home (/), Buy and Rent (/properties?listing_type=sale|rent), Commercial (/commercial), the map views, Premium and Marketing lists, property pages (/property-details/{slug}), Agents, Agencies, Market Insights, the Calculators menu, the Explore menu (About, Contact, Blog, Careers), legal pages, the thank-you page, landing pages, the 404 page and the customer account pages (/signup, /login, /verify-email, /forgot-password, /reset-password, /profile). **Who:** Visitors (no sign-in), Customers (website "User" accounts) and anyone else using the site as a visitor. Agencies and agents sign in from the same /login page, but their work happens in the CRM. Admin manages the content. **Locks:** None on browsing. Saving a search, the wishlist and /profile need a Customer sign-in. Brochure and floor-plan downloads, and the Eligibility and Refinance results, unlock only after the visitor enters contact details. Forms are protected by invisible reCAPTCHA (only when the server has keys) and by per-IP rate limits (section 2.10).

## 1. What this module does

The website is a single-page app. Each page loads its content from the server. Listings appear only while they are live (switched on in the CRM). Banners, home sections, filters, blogs, insights, calculator text and FAQs, legal pages and SEO are managed in Admin. Labels and buttons come from the static-text files (Admin > Languages > Translations), in English and Arabic.

The website creates three kinds of records:
- **Leads.** Property enquiries, viewing requests, brochure and floor-plan downloads, and agent or agency requests go to the CRM of the agency or agent behind the listing or profile.
- **Enquiries.** The Contact page, the home "Get in touch" form and the calculator forms go to Admin > Enquiries, with an email to the admin notification address.
- **Other records.** Career applications go to Admin > Careers > Candidates, and newsletter sign-ups to Admin > Newsletter Signups.

The **Compare** button, the property-page tabs **Mortgage & Investment**, **Rental Costs** and **Market Insights**, the currency-rate sync and the AI chat are covered in Module 26. Module 26 is referred to below where they appear.

| Page / menu | URL | What it does |
|---|---|---|
| Header | every page | Logo, menu (Home, Buy, Rent, Commercial, Agents, Agencies, Market Insights, Calculators ▾, Explore ▾), Login or account menu, language and currency switchers, mobile menu button. |
| Footer and mobile bottom bar | every page | Link columns, contact details, newsletter, social links. On phones there is a bottom bar: Home, Buy, Search, Rent and Login / Profile. |
| Home | / | Banner search, partner logos, New Projects, Premium Properties, ad slider, Post your property, Popular Places, Luxury Project, Why Choose Us, Realty Property, Communities, Find Properties, Testimonials, "Get in touch" form. |
| Buy / Rent | /properties?listing_type=sale or rent | Residential listings with filters, sorting, numbered pages, grid or list layout, Save Search, Clear Filters, wishlist hearts and Compare. |
| Properties map | /properties/map | The same filters, with results as map pins. |
| Commercial | /commercial, /commercial/map | Commercial listings with their own filter set; list or map. |
| Premium | /premium-properties (+ /map) | Every listing currently featured as Premium, residential and commercial together. |
| Realty Properties | /marketing-properties (+ /map) | The Super Admin's Marketing Properties list, in its own order (the Home "Realty Property" view-all). |
| Property details | /property-details/{slug} | Gallery, details, tabs, floor plans, location and nearby places, permit block, agent card with Call / WhatsApp / Email, enquiry form, Book a Viewing, brochure and floor-plan downloads, Similar Properties. |
| Agents | /agents, /agent-details/{slug} | Agent directory with search; agent profile with listings and the **Custom Property Request** form. |
| Agencies | /agencies, /agency-details/{slug} | Agency directory with search; agency profile with listings, agents and the **Custom Property Request** form. |
| Market Insights | /market-insights, /market-insights/{slug} | Research reports: featured report, topic and market filters, report pages. |
| Calculators menu | /mortgage-calculator, /eligibility-calculator, /rent-vs-buy-calculator, /refinance-calculator | The four calculators, each with FAQs and an enquiry form. |
| Explore > About Us | /about | About content managed in Admin. |
| Explore > Contact Us | /contact | Contact form and office details. |
| Explore > Blog | /blogs, /blog-details/{slug} | Blog list with categories; article pages. |
| Explore > Careers | /careers, /careers/{slug} | Job openings with filters; job page; application form with CV upload. |
| Legal pages | /terms-and-conditions, /privacy-policy, /security-policy, /cookie-settings | Admin-managed text pages. |
| Thank-you page | /thank-you | Shown after the Contact, home, calculator, property-enquiry and agent/agency request forms. |
| Landing pages | /{slug} | Admin landing pages (Blog design or custom HTML). |
| 404 page | any unknown URL | "Page Not Found" with a search box and popular links. |
| Sign up / Log in | /signup, /login, /verify-email, /forgot-password, /reset-password | Customer accounts (User tab) and the Agent / Agency sign-in. |
| My Account | /profile | Overview, Wishlist, Saved Searches, My Enquiries and Account Settings. |
| SEO files | /sitemap.xml, /robots.txt, /llms.txt | Static files generated from Admin > SEO. |

## 2. How it works (business rules)

### 2.1 Header and footer

- **Menu order:** Home, Buy, Rent, Commercial, Agents, Agencies, Market Insights, then two dropdowns:
  - **Calculators:** the calculator pages. Titles, order and which calculators appear come from Admin > Calculators. The built-in list is Mortgage Calculator, Eligibility Calculator, Rent vs Buy Calculator and Refinance Calculator.
  - **Explore:** About Us, Contact Us, Blog and Careers.
- **Active item:** the current menu item is highlighted. Buy is highlighted on /properties?listing_type=sale and Rent on ?listing_type=rent. /properties without a purpose highlights neither. A dropdown is highlighted when one of its pages is open.
- **Opening dropdowns:** a dropdown opens on hover, on click or tap, or from the keyboard. It closes on navigation, on an outside click, or with Esc (focus returns to the menu button). Only one dropdown is open at a time. After a link in it is clicked, it stays closed until the pointer leaves it.
- **Logo:** the logo goes to /. The Home page has a transparent header with the light logo; every other page has a light header with the dark logo.
- **Language switch:**
  - The flag and code button (e.g. "EN") appears only when Admin has more than one language active.
  - Choosing a language reloads every page's content and the static texts in that language.
  - **Any language other than the default** switches the page to right-to-left (the `is-rtl` class on the page) and sets the page's lang attribute.
  - The choice is **not remembered**: after a reload or a new tab the site is back in the default language.
- **Currency switcher:**
  - The currency code button (e.g. "AED") lists the active currencies from Admin > General Settings > Currencies.
  - All prices are stored in AED and shown as AED × the currency's rate. Price filters still work in AED behind the scenes.
  - The choice is remembered in the browser (localStorage key `mw-currency`). Until a visitor chooses, the Admin default currency is used.
  - Rates and the hourly sync are covered in Module 26.
- **Login / account menu:**
  - Signed out, the header shows **Login**, which opens /login.
  - Signed in as a Customer, it shows the avatar (or a user icon) and the first name ("Account" if there is no name). The dropdown has **Profile** (/profile) and **Logout**.
  - Logout always ends on /login with a full reload.
  - Only Customer sessions count. An agent or agency signed in to the CRM still sees **Login** on the website.
- **Mobile menu:** the ☰ button opens a side panel with the logo, ×, every primary link, the Explore links and the Calculator links (one flat list), Login (or "{first name} — Profile" and Logout), and the language and currency switchers.
- **Footer columns:**
  - **Quick Links:** Home, Market Insights, About Us, Blog, Careers, Mortgage Calculator, Contact Us.
  - **Properties:** "Properties in" Dubai, Abu Dhabi, Sharjah, Ajman, Ras Al Khaimah and Umm Al Quwain (/properties?location=City), plus Premium Properties.
  - **Buy:** Properties for Sale, Apartments for Sale, Villas for Sale, Townhouses for Sale, Penthouses for Sale, Ready to Move In.
  - **Off-Plan:** All Off-Plan Projects, and "Off-Plan in" each of the six cities.
  - **Legal:** Terms of use, Privacy policy, Security Policy, Cookie settings.

  Below the columns: address, toll-free number, phone, email and social links, all from Admin site information (each line is hidden when empty). Then the copyright line "Copyright © {year} {company name} | Designed by : Mighty Warners Technologies".
- **Newsletter:** an email box, an arrow button and the tick box "I have read and agree to the terms & conditions" (section 4).
- **Mobile bottom bar:** Home, Buy, Search, Rent and Login (shows Profile when signed in).
  - **Search** jumps to the banner search on Home; on other pages it opens /properties.
  - The current item is highlighted. The account item is highlighted on the profile, login, sign-up, verify and password pages.
- **AI chat:** a floating chat button is on every page. It is covered in Module 26.

### 2.2 Home (/)

The whole page comes from one request (GET /api/home), cached per language for 3 minutes. Saving Common Titles (section headings) or the Marketing Properties list clears the cache straight away; other edits can take up to 3 minutes to show. Every section below is hidden when it has nothing to show. No placeholder cards are shown.

| Section | What it shows | Where the content comes from |
|---|---|---|
| Banner (hero) | Video background, two title lines, search box, note line and guide link. | The first active banner in Admin (title lines, video, note text, note badge, link). Fallback text: "Your Trusted Partner in" / "Finding the Best Properties". The fallback note badge reads "Comming Soon" (spelling as in the text file). |
| Banner search | Tabs **Buy**, **Rent**, **Ready**, **Off-Plan** (Buy is selected by default); Location box ("Search location or property title") with suggestions; **Bedroom**; **Price Range** (Min / Max, slider, **Quick select**); **Property Type**; **Search**. | Tab labels and dropdown options come from Admin > Filters > Search Filters > Home. Without them, bedrooms are 1–5+ and types are apartment, villa, penthouse, townhouse, plot and office. The price slider runs 0 to the Admin maximum (default 30,000,000, step 50,000). |
| Partner logos | A scrolling strip of brand logos. | Active brands in Admin (up to 30). |
| New Projects ("Browse New Projects in the UAE") | City tabs ("All" plus Admin cities), up to 8 cards, **View All Properties**. | Up to 8 live listings in CRM display order, shuffled on each load. These are any live listings, not only off-plan ones. Clicking a city loads that city's listings (loose match on address, community or city). The city tabs come from Common Titles > Home developments. |
| Premium Properties | A slider of up to 12 cards and a button (default **View More Details** → /premium-properties). | Live listings marked Featured / Premium in the CRM, shuffled. |
| Ad slider | A full-width banner, one slide per running ad. | Admin > Ads plus clients' booked ads (placement "home"). Hidden when no ad is running. |
| Post your property | Numbered steps with icons and a button. | Admin post-property steps (up to 10). |
| Popular Places | Place cards. Each opens /properties?location={place}. | Admin popular places (up to 20). |
| Luxury Project | A slider of up to 12 residential listings. | Listings inside the Min / Max Price set on Common Titles > Luxury Project. With no range, the most expensive residential listings. |
| Why Choose Us | Image plus items. | Admin Why Choose Us items (up to 20). |
| Realty Property | A slider of up to 12 cards and a button (→ /marketing-properties). | The Super Admin's CRM > Marketing Properties list, first 12 in its order. While that list is empty: automatic live listings that are not already in the Luxury slider (button → /properties). |
| Communities | Tabs **For Sale / For Rent / Off Plan**, then community rows in 4 columns. A row opens /properties with that location plus the tab's purpose (or completion_status=off_plan). | Admin community highlights (up to 40). |
| Find Properties ("Browse by type") | Type cards with "N properties". Each opens /properties?property_type=…. | Admin Find Properties items (up to 12). |
| Testimonials | A slider of client quotes. | Admin testimonials (up to 20). |
| Get in touch | The contact form (section 4) and office details. | The form saves an Enquiry (source "Home Page"). The details come from Common Titles > Contact Us. |

- **Banner search rules:**
  - A picked **address/property** suggestion opens that property's page straight away.
  - A picked **city** or **community** filters exactly (?city= or ?community=). Typed text without a pick searches ?location= loosely.
  - The tab adds ?listing_type=sale|rent or ?completion_status=ready|off_plan.
  - The price adds ?min_price / ?max_price only when moved off the ends.
  - Suggestions start after 2 characters, with a 0.35 s pause. Each one is labelled City, Community, Address or Property.
- **Cards:** each card has a photo slider, a heart (wishlist) and price, beds, baths and area.
  - The price follows the selected currency, or shows the listing's own text such as "Price on request".
  - The Email / Call Us / WhatsApp buttons on the New Projects, Luxury and Realty cards go to fixed MW Realty company contacts, not to the listing's agent (see section 7).
  - Clicking a card opens /property-details/{slug}.

### 2.3 Buy / Rent / Commercial, map views, Premium and Marketing lists

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
  - photo slider and photo count, type tag, heart, price, **Compare** (Module 26), title, location, beds / baths / area;
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
  - The project overview (Module 00) still lists it on /properties; that line is out of date.

### 2.4 Property details (/property-details/{slug})

- **Who can open it:** only live listings. An offline or unknown slug shows "Property Not Found" / "This property listing doesn't exist or is no longer available." with **Back to Properties**. The browser tab title becomes "{listing name} |tab title is the listing name followed by "MW Realty".
- **Top of the page:**
  - breadcrumb Home / Properties / name, and a photo slider (short galleries are repeated so the slider always moves);
  - badges: **For Sale** / **For Rent**, **Premium**, **Upgraded**, a green market pill (Module 26), and "Open house · {first date} +N" when open-house days exist (click jumps to the dates);
  - beds, baths and "Published" date;
  - **price label:** "Starting Price" for sale; "Rent · {period}" for rent, or "Yearly Rent" when no period is set. Without a price: "Price on request";
  - **Download Brochure** (only when a brochure exists) and the large **Compare** button (Module 26).
- **Tabs:** they scroll to sections. On phones they are a dropdown with the same options. A tab shows only when its section has content.

  | Tab | Shown when | Content |
  |---|---|---|
  | Overview | always | Description (or "A {type} located in {location}."), Key features, Property details pills (Permit/RERA ID, Status, Type, Bedrooms, Bathrooms, Location, Size, and when filled: Category, Furnishing, Parking, Garage, Floor, View, Developer, Year built "· N years old", Direct from owner, Emirate), Video tour / 360° virtual tour links. |
  | Price & Availability | cheques, deposit, rental period or availability is set | Price, Number of cheques, Security deposit, Status, "Open house & viewing days" (date buttons, **Book viewing**) or "Available immediately" / "No upcoming open house dates — ask the agent for a private viewing.", **Book a Viewing**. |
  | Features & Amenities | any amenities, easy access or highlights | Groups Amenities, Easy access, Highlights. |
  | Floor Plans | the listing has floor plans | Plan list (label, size, price range "AED 240,000 – 264,000"), a large drawing that opens zoomed, **Download Floor Plan** when a downloadable plan exists. |
  | Gallery | always | Main photo plus up to 4 thumbnails; the last shows "+N photos". Opens a lightbox. |
  | Location & Nearby | the listing has a map point or full address | Address; a map with the listing pin; chips for each nearby-place type with an "All" option; places listed with their distance. |
  | Mortgage & Investment, Rental Costs, Market Insights | see Module 26 | Covered in Module 26. |

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
- **Similar Properties:** a slider under the page (rules in Module 26). An ad card sits in the sidebar (placement property-details).

### 2.5 Agents (/agents, /agent-details/{slug})

- **Directory:**
  - The list shows **approved and active** agents, sorted by name. All agents load at once; there is no paging.
  - Page title from Common Titles > Agents (default "Our Agent").
  - Search boxes: **Location** (matches the agent's preferred areas), **Agent Name** and **Agency**. They apply on **Search** or Enter. Matching is case-insensitive "contains".
  - Grid / list toggle. No match: "No agents match your search."
  - /agents?agency={slug} (from an agency's **View All**) pre-fills the Agency box and matches that agency exactly. Editing the box switches to a name search.
- **Agent card:** photo, name, "Serves in Dubai", "Rent : N", "Sell : N" (live listings shown under the agent), years of experience, preferred areas, View Details.
- **Profile:**
  - photo, name, "Serves in Dubai", a link to the agent's agency, share buttons (X, Facebook, Instagram copies the link and shows "Link copied", LinkedIn);
  - chips for experience, preferred areas, Rent and Sell counts;
  - **Email**, **Call Us** and **WhatsApp** (each only when filled), and **Custom Property Request**;
  - tabs **About Me** (bio, or a fallback sentence) and **My Listing Properties**;
  - "Listings by {name} (N)" slider: the 10 newest live listings, with hearts. **View All** opens /properties?agent={slug}, which shows an "Agent: {name} ×" chip and the heading "Properties by {name}".
- **Unknown or unapproved slug:** "Agent Not Found" / "This agent profile doesn't exist or is no longer active."

### 2.6 Agencies (/agencies, /agency-details/{slug})

- **Directory:**
  - The list shows approved and active agencies, A–Z by company name. All agencies load at once.
  - Headings from Common Titles > Agencies (default "Our Agencies", "Top Agencies" and a description).
  - Search **Location** (matches the office address) and **Agency Name**. No match: "No agencies match your search."
- **Agency card:** logo, name, "Properties : N", office address, call and email icons, View Details.
- **Profile:**
  - logo, name, "Since {year}", call / email / WhatsApp icons, **Custom Property Request**;
  - office address; counts of agents, total properties, for sale and for rent (live listings only); bio; an embedded map;
  - "Listings by {name} (N)": the 10 newest live listings; **View All** opens /properties?agency={slug};
  - an agents panel with up to 6 active agents; **View All** opens /agents?agency={slug}.

  Unknown slug: "Agency Not Found".
- **Custom Property Request form (agents and agencies):**
  - The button **Custom Property Request** opens a side drawer: "Tell us what you're looking for and we'll help you find it."
  - Sections: Personal Information, Property Requirements, Preferences (fields in section 4). **Submit Request**.
  - On success the drawer closes and /thank-you shows "Your property request has been received." with "Your agent is already on it…" or "The agency is already on it…".
- **Where the request goes:** a CRM lead with source "agent-profile-request" or "agency-profile-request".
  - Independent agent: their own lead.
  - Agent inside an agency: the agency's lead, with that agent as the preferred agent.
  - Agency: the agency's lead.
  - If the visitor is already another account's lead (same browser, email or phone), that account keeps them.
  - The answers are saved as the lead message and as extra fields.

### 2.7 Market Insights (/market-insights, /market-insights/{slug})

- **List page:**
  - Content comes from Admin > Market Insights; the title defaults to "Market Insights" and the heading to "Research & Reports".
  - On the unfiltered first page, the newest **featured** post leads as a big "Featured Report" (topic chip, market, author · date · reading time, **Read the report**, a lead figure). It is not repeated in the grid.
  - Topic chips ("All" + topics in use) and a **Market** dropdown ("All markets"; shown only when more than one market is in use).
  - Filters and the page number live in the URL (?topic=&region=&page=), so a filtered view can be shared.
  - **9 posts per page**, numbered paging.
  - Empty: "No insights found" with "Nothing matches these filters yet — try another topic or market." and **Clear filters**.
- **Report page:**
  - breadcrumb, topic chip (links to the topic list), market, author with role, summary, figure tiles, "Key Takeaways", the article;
  - "Share this insight" with **Copy link** (shows "Copied!");
  - side panels "At a glance" (Topic, Market, Published, Reading time, Author), **Download full report** (only when a report file or link is set), "Explore topics", and "Talk to an expert" → **Contact our team** (/contact);
  - "Related Insights" (up to 3) with **View all insights**.

  Unknown slug: "Insight Not Found" / "This market insight doesn't exist or has been removed."

### 2.8 Calculators

All four calculators share one page layout: title and breadcrumb, calculator tabs (the Calculators menu list), heading and intro, the calculator, FAQs, then the enquiry form. The maths runs in the browser. Calculators are meant to give **the same numbers as Property Finder's calculators** (Mortgage Finder for refinance) for the same inputs; any difference is a bug.

- **Admin-managed (Admin > Calculators):**
  - which calculators appear in the menu, their titles and order;
  - each page's title, heading, intro, FAQ title and FAQs (question / answer, one paragraph per line);
  - the enquiry block's eyebrow, title, text and bullet points;
  - the refinance lender list, and SEO.

  Without Admin text the built-in fallbacks are used, e.g. "Estimate Your Monthly Payments", "How Much Can You Borrow?", "Should You Rent or Buy?", "Refinance Your Mortgage". The FAQ block is hidden when there are no FAQs.
- **Inputs:** amount fields accept typed values in the selected currency ("1.5M", "250k" and "1,200,000" all work). Unreadable text reverts. Typed numbers are kept within the field's limits. Disclaimer under each: "Figures are estimates for guidance only and do not constitute a loan offer. …"
- **Enquiry form under each calculator** (title per calculator: "Get a mortgage quote", "Get pre-approved for a mortgage", "Ready to buy? Talk to an advisor", "Find a better mortgage rate"):
  - Fields: Name, Email, Phone, "Message (optional)". The button is **Get a mortgage quote**.
  - Sends the visitor's figures with the enquiry, always in AED (e.g. "Monthly payment: AED 4,936").
  - Saved in Admin > Enquiries with page source "Mortgage Calculator", "Eligibility Calculator", "Rent vs Buy Calculator" or "Refinance Calculator", and emailed to the admin notification address.
  - The visitor also becomes a Website Lead. The visitor lands on /thank-you (contact wording).

**Mortgage Calculator (/mortgage-calculator; the same calculator sits on property pages)**

| Input | Default | Limits |
|---|---|---|
| Purchase price | AED 1,200,000 (or ?price= in the URL) | 300,000 – 200,000,000, step 10,000 |
| Residency status | UAE Resident | UAE National / UAE Resident / Non-resident |
| Down payment (with % badge) | the minimum for the residency | minimum % to 80%, step 1,000 |
| Loan amount (with % badge) | price − down payment | 20% to (100 − minimum)% |
| Loan period | 25 yrs | 1 – 25 |
| Interest rate | 3.75% (resident) | 1 – 10%, slider step 0.05, typed 0.01 |

- **Minimum down payment:** National 15%, Resident 20%, Non-resident 40%. Above AED 5,000,000: National 25%, Resident 30%, Non-resident 40%. The hint reads "Minimum {pct}% for {who}", plus "(homes above AED 5M)" for those prices.
- **Changing residency** resets the down payment to that residency's minimum and the rate to its typical rate: National 4.24%, Resident 3.75%, Non-resident 4.19%.
- **Results:**
  - **Monthly payment** "at {rate}% over {years} years", a principal/interest bar, Principal and Total interest, Loan amount, Total loan repayment;
  - **Estimated upfront costs:** Down payment, **Total purchase costs** (expand for the list), "Total amount required upfront", and "Calculations based on".
- **Buttons:** **Browse properties in budget** opens /properties?listing_type=sale&max_price={price}. **Talk to an advisor** scrolls to the form.
- **Purchase costs (Dubai fees):**
  - Land department fee 4% + AED 580;
  - Registration trustee fee AED 4,000 + VAT (4,200);
  - Real estate agency fee 2% + VAT;
  - with a loan only: Mortgage registration 0.25% + AED 10, Bank arrangement 1% + VAT, Mortgage valuation AED 3,000 + VAT (3,150).
- **Worked example 1 (defaults):** price 1,200,000, Resident, 20% down, 25 yrs, 3.75%.
  - Loan 960,000.
  - **Monthly payment AED 4,936**; Total loan repayment AED 1,480,698; Total interest AED 520,698.
  - Purchase costs 48,580 + 4,200 + 2,410 + 25,200 + 10,080 + 3,150 = **AED 93,620**.
  - **Total amount required upfront AED 333,620**.
- **Worked example 2:** price 2,000,000, UAE National (15%, rate 4.24%), 25 yrs.
  - Loan 1,700,000; **monthly AED 9,200**.
  - Purchase costs AED 152,040; upfront AED 452,040.
- **Worked example 3 (high value):** price 6,000,000, Resident. The down payment jumps to 30% and the hint adds "(homes above AED 5M)".
  - Loan 4,200,000; **monthly AED 21,594**; upfront AED 2,228,540.

**Eligibility Calculator (/eligibility-calculator)**

| Input | Default | Limits |
|---|---|---|
| Monthly income | 30,000 | 5,000 – 300,000, step 1,000 |
| Total monthly liabilities | 0 | 0 – 150,000, step 500 |
| Credit card limits | 0 | 0 – 1,000,000, step 5,000 |
| Residency status | UAE Resident | three options |
| Is this your first time buying property in the UAE? | Yes | Yes / No |

- **Rule:** all monthly debts must stay at or under 50% of income. Card limits count 5% as a monthly debt.
  - Maximum instalment = income × 50% − (liabilities + 5% of card limits).
  - Maximum loan = what that instalment repays over 25 years at the residency rate.
  - Price = loan ÷ (1 − minimum down payment).
  - Second or later home: 35% minimum (non-resident 40%).
  - When a first-home price would go above AED 5M, the higher down payment applies, which can hold the price at exactly 5,000,000.
- **Results are locked:**
  - The results card is blurred and shows placeholder figures ("AED 0,000,000"); the real figures are not on the page.
  - **Get your eligibility certificate** or **Unlock your results** opens "Your eligibility certificate is ready!" / "Please share your contact details to unlock it." with Name, Email, Phone and **Continue**.
  - Sending it saves an Enquiry with the figures and unlocks the results for the rest of the browser tab session (sessionStorage `mw-eligibility-unlocked`).
  - After that, editing inputs shows "You have changed your details — check again to update your eligibility." and **Check eligibility** re-runs without the form.
- **Results:** Property purchase price, Mortgage loan amount, Monthly payments, Down payment (N%), the upfront-costs card, **See homes you can afford** (/properties?listing_type=sale&max_price=…) and **Talk to an advisor**. When debts already use 50% or more: "Your monthly liabilities already use 50% or more of your income, so banks are unlikely to approve a new home loan."
- **Worked example 1 (defaults):** income 30,000, no debts, Resident, first home.
  - Max instalment 15,000.
  - **Price AED 3,646,929**; loan AED 2,917,543; monthly AED 15,000; down payment (20%) AED 729,386.
- **Worked example 2:** income 30,000, liabilities 2,000, card limits 50,000.
  - Instalment 15,000 − (2,000 + 2,500) = 10,500.
  - **Price AED 2,552,850**; loan AED 2,042,280; down payment (20%) AED 510,570.

  Other checks:
  - Income 60,000: price AED 8,335,838, down payment (30%).
  - First home "No", income 30,000: price AED 4,488,528, down payment (35%).
  - Income 10,000 with liabilities 5,000: the over-limit message and zero figures.

**Rent vs Buy Calculator (/rent-vs-buy-calculator)**

| Input | Default | Limits |
|---|---|---|
| Annual rent | 140,000 | 20,000 – 2,000,000, step 5,000 |
| Purchase price | 1,200,000 | 300,000 – 200,000,000, step 10,000 |
| Residency status | UAE Resident | sets the minimum down payment ("It affects the minimum down payment.") |
| Down payment | minimum % | minimum to 80% |
| Mortgage loan period | 25 yrs | 1 – 25 (this is also the comparison period) |
| Assumptions (expandable): Mortgage type | Fixed | Fixed / Variable |
| Fixed interest rate / Fixed period / Rate after the fixed period | 4.24% / 3 yrs / 3.24% | 1–10% / 1–25 / 1–10% |
| Margin over EIBOR (Variable) | 4.24% | 1–10%; hint "Your rate = 3-month EIBOR (4.9%) + this margin." |
| Yearly rent increase / Yearly property value growth | 0% / 0% | −50% to 50% |

- **Model:** month by month, the same model as Property Finder's calculator.
  - **Renting:** the tenant moves every 3 years and pays a 5% + VAT agent fee on each move, Ejari AED 120 a year and AED 42 per cancellation.
  - **Buying:** agent 2% + VAT, Land Department 4% + 580, trustee 4,200, bank processing 0.5% + VAT, mortgage registration 0.25% + 290, valuation 3,150, life insurance 0.2% a year of the balance, property insurance 0.06% a year of the price, service charges AED 15,000 a year.
  - **Selling at the end:** 2% agent fee plus a NOC of AED 500.
- **Results:**
  - Verdict "Over {n} years": **Buying is cheaper** / **Renting is cheaper** "by" an amount;
  - "Saved if buying" / "Lost if renting" (or "Lost if buying" / "Saved if renting");
  - "Buying pays off after" N years, or "Not within the loan period";
  - **Comparison** and **Monthly cost** charts (with **View as table**), a "How this is calculated" breakdown for renting and buying, and **Properties for sale** / **Properties for rent** buttons.
- **Worked example 1 (defaults):** rent 140,000, price 1,200,000, Resident 20% (240,000), 25 yrs, fixed 4.24% for 3 yrs then 3.24%, growth 0%.
  - **Buying is cheaper by AED 2,561,576**. Buying pays off after **2 years**.
  - Monthly cost: Rent AED 11,667 vs Buying AED 5,415 (principal 1,803 + interest 3,392 + insurance 220).
  - Renting breakdown: agent fees 66,150, Ejari 3,336, rent payments 3,500,000, net cost **3,569,486**.
  - Buying breakdown: agent 25,200, Land Department & trustee 52,780, bank fees 10,880, interest 474,007, insurance 45,543, service charges 375,000, selling price 1,200,000, selling fees 24,500, net cost **1,007,910**.
- **Worked example 2:** rent 60,000, price 1,200,000, 20% down, loan period 5 yrs, other defaults.
  - **Buying is cheaper by AED 7,251**; pays off after **5 years**.
  - Monthly: Rent AED 5,000 vs Buying AED 18,004.

**Refinance Calculator (/refinance-calculator)**

- **Three steps**, each checked when **Next** is pressed. Completed steps can be revisited.
  1. **Current loan:** Current mortgage lender (Admin list, built-in list of 19 UAE banks plus "Other"), Original loan amount, Current property valuation, Full loan term (years 0–25 and months 0–11; months are locked at 25 years), Date mortgage started, Current interest rate, Fixed interest rate period (1–5 years).
  2. **Employment details:** Employment status (Employed Salary Transfer, Employed No Salary Transfer, Self Employed, Non Resident), Income per month, Employment tenure (years 0–40, months 0–11).
  3. **Refinance:** Borrower's date of birth, Current mortgage balance, Desired equity release.

  On arriving at step 3, the balance is pre-filled with an estimate, with the hint "Estimated from your loan details — change it if your statement shows a different amount."
- **Results locked:** like Eligibility. **See my savings** asks for contact details once; the unlock is kept for the tab session (sessionStorage `mw-refinance-unlocked`). Placeholders "AED 0,000" are shown until then.
- **Maths (Mortgage Finder's):**
  - **Current loan:** the rate the loan moves to after its fixed period, which is the lender's margin plus EIBOR. For example HSBC 0.99 + 3-month EIBOR 4.44348 = 5.43%; Emirates NBD 1.99 + 1-month 4.07423 = 6.06%; unknown lenders and "Other" use 1.6 + 3-month = 6.04%. It is calculated on the balance over the months left (term minus days since the start ÷ 30).
  - **Refinance rate:** by employment, without / with equity release: Salary Transfer 3.75 / 3.89, No Salary Transfer 3.99 / 3.99, Self Employed 4.19 / 4.19, Non Resident 4.19 / 4.44.
  - **New loan and term:** new loan = balance + equity release. The term is 25 years, cut so the loan ends by age 65; then the note "The new loan has to end by age 65, so its term is shorter than 25 years." appears.
  - **Cost of switching:** early settlement 1% of the balance (max AED 10,000) + VAT, valuation 3,150, and registration, release and transfer 5,070.
  - **Savings:** shown per month, in the first year and over 3 years. When the new payment is higher, "Refinancing would not lower your payments" is shown.
  - Original loan, valuation, current rate, fixed period, income and tenure are sent with the lead but do not change the figures (as on Mortgage Finder).
- **Results depend on today's date** (months left). The examples assume the test is done on 2 Nov 2026; on other days the figures move slightly.
- **Worked example 1:**
  - Inputs: HSBC; original 1,200,000; valuation 2,000,000; 25 yrs 0 months; started 2021-11-02; current rate 4%; fixed 3 years; Employed Salary Transfer; income 30,000; tenure 5 yrs; date of birth 1990-01-01; balance overwritten to 1,000,000 (the estimate offered is 1,045,255); equity release 0.
  - Rates: current loan 5.43% vs refinance 3.75%. Term left 19 years 11 months; new term 25 years.
  - Payments: current monthly **AED 6,855**, new **AED 5,141**.
  - Saving: **AED 1,714 per month**, AED 20,565 in the first year, **AED 61,696 over 3 years**.
  - Refinancing will cost **AED 18,720** (10,500 + 3,150 + 5,070).
- **Worked example 2 (age-limited):** as example 1 but lender "Other", date of birth 1970-06-15, balance 800,000.
  - Current rate 6.04%, current monthly AED 5,762.
  - The new term is cut by age (about 8.6 years) and the age note shows. The new monthly is AED 9,066, so "Refinancing would not lower your payments".
  - Cost AED 16,620 (settlement 8,400).

### 2.9 Explore menu, legal pages, thank-you, landing pages and 404

- **About Us (/about):** sections from Admin (About Us, Market Trends, Our Builders, Connect with us, plus the shared Post-property steps and Why Choose Us items). Cached up to 3 minutes.
- **Contact Us (/contact):**
  - Heading and text from Common Titles > Contact Us (fallback "Get in Touch").
  - The form has Name, Email, Phone and Message.
  - Office address, Call Us, Email Us, WhatsApp, working hours, social links and a map, all from Admin.
  - **Send** saves an Enquiry (page source "Contact Page") in **Admin > Enquiries**. It also emails the admin notification address (Admin site information) and makes the visitor a Website Lead in the Super Admin's pool.
  - The visitor lands on /thank-you: "Your message is on its way to our team." / "A member of MW Realty will get back to you within one business day."
- **Blog (/blogs, /blog-details/{slug}):**
  - The list has category pills (All + categories; filtered on the server), 12 posts per page and **Read More** links.
  - An article shows the cover, category, author with role, date, reading time, content and share icons, and Previous / Next post links. The first and last articles show "Earliest article" and "Latest article".
- **Careers (/careers, /careers/{slug}):**
  - Intro with "Open Positions" and "Teams Hiring" counts, **View Open Roles** and **Send Your CV**.
  - Perks, then "Find Your Next Role" with Department, Job Type and Location filters (applied on change and kept in the URL).
  - Job cards open /careers/{slug}, which has the job details and the application form.
  - The bottom of /careers has the General Application form (#apply).
  - Applications go to **Admin > Careers > Candidates** ("General Application" when not for a job) with an email to the admin. Success: "Thanks for applying — our team will review your application and get back to you."
- **Legal pages:** Terms, Privacy, Security and Cookie Settings show the Admin page text. Without text: the "unpublished" message. Missing page: the legal "not found" text. **Cookie settings is a text page only**: there is no consent banner or cookie switch in the code.
- **Thank-you page (/thank-you):**
  - The heading is "Thank you, {first name}!" (or "Thank You!") with "Submission received".
  - The wording and three steps depend on the form: contact, home enquiry, agent/agency request, or property enquiry. The property version shows "You enquired about" with the property and "Sent to" with the agent or agency and Call / WhatsApp.
  - The form data travels in browser history, not the URL. A refresh keeps it; opening /thank-you directly shows the generic "Your submission has been received." text.
  - Buttons: **Back to Homepage**, **Browse Properties**, and **Back to Property** on the property version.
- **Landing pages (/{slug}):**
  - A one-segment URL is first checked against Admin > Landing Pages. A "Blog design" landing page uses the blog article layout without Previous/Next.
  - Custom-HTML landing pages are served by the server. Any form on them is captured at /{slug}/enquiry and listed under Admin > Landing Pages > Enquiries (20 per hour per IP).
  - Anything else shows the 404 page.
- **404 page:**
  - "Error 404", "Page Not Found", "Sorry, We can't find the page you're looking for." and the requested path.
  - A search box ("Search by city, community or building") that opens /properties?location=….
  - Popular pages chips (Buy, Rent, Commercial, Agents, Market Insights), **Back to Homepage** and **Contact Us**.
  - Unknown URLs loaded directly return a real HTTP 404 status.
- **SEO a tester can see:**
  - Each page sets the browser tab title, e.g. "Properties in Dubai | MW Realty", "Our Agent | MW Realty", "Mortgage Calculator | MW Realty", "Page Not Found | MW Realty".
  - Content pages (home, blogs, insights, calculators, profiles, properties) also take the meta title and description from Admin SEO data. Property pages use "{name} | MW Realty".
  - /sitemap.xml, /robots.txt and /llms.txt are static files regenerated from Admin > SEO.
- **Visitor tracking (observable):**
  - The server sets a first-party cookie **mw_vid** (2 years, HttpOnly, SameSite=Lax) that identifies the browser.
  - The page sends POST /track/page about 1 second after each page view, and POST /track/page/{id}/time with the visible seconds (every 30 s and on leaving).
  - Listing cards send /track/impressions; contact clicks send /track/lead-click; ads send /track/ad-impressions and /track/ad-click.
  - These requests do not change anything on screen. Their effect is in CRM Website Leads (Module 04) and reports.

### 2.10 Sign up, log in, verify email, forgot / reset password, My Account

- **/signup:**
  - Account Type **User** / **Agent** / **Agency**.
  - **User:** Full Name, Email Address, Phone Number (optional), Password, Confirm Password, and the Terms tick box. **Create Account** stays disabled until all of these are valid (password strength rules in section 4).
  - Agent and Agency sign-up is covered in Module 12.
  - "Continue with Google" is for customer accounts only.
- **/verify-email:**
  - A 4-digit code is emailed. It expires after 10 minutes and verifies automatically once entered.
  - **Resend** is available after a 30 s countdown ("Resend in {seconds}s").
  - Wrong or expired code: "Invalid or expired code."
  - Success: "Verified Successfully" and **Continue to Account** (/profile).
- **/login:**
  - Tabs **User** and **Agent / Agency**. The User tab signs in to /profile; the Agent / Agency tab goes to the CRM (with possible new-device or 2FA code steps, Module 12).
  - Wrong details: "Invalid credentials."
  - Old links /agent-login and /agency-login redirect here, and /agent-signup and /agency-signup redirect to /signup.
- **/forgot-password:**
  - Step 1: enter the email. **Continue** ("Checking…") answers "No account found with this email." or "This email is not verified yet — please complete sign-up first.".
  - A verified email goes straight to step 2 ("Email verified" badge): enter the new password twice. **No email or code is sent** in this flow (see section 7).
  - Success returns to /login with "Your password has been reset — sign in with your new password."
- **/reset-password:** opened from an emailed link (?token=&email=). Without both: "Invalid reset link" with **Request New Link**.
- **/profile (Customers only):** a visitor who is not signed in is sent to /login. Left-hand tabs:
  - **Overview:** "Welcome back, {name}", Saved Properties / Saved Searches / Enquiries Sent counts, and Recent Activity (empty: "No activity yet — start browsing properties to save favorites and send enquiries.").
  - **Wishlist:** saved listing cards; the heart removes one (empty: "You haven't saved any properties yet.").
  - **Saved Searches:** title, summary, **View Results** (opens /properties with the saved criteria) and delete (empty: "You haven't saved any searches yet.").
  - **My Enquiries:** table with Property, Sent To, Date and Status (empty: "You haven't sent any enquiries yet.").
  - **Account Settings:** photo ("JPG or PNG, up to 2MB."), Full Name, Email Address, Phone Number, Location, New Password ("Leave blank to keep current password"), Confirm New Password, **Save Changes**. The header name and photo update straight away.
  - **Log Out**.

## 3. Who can do what

| Action | Visitor | Customer | Agency / Agent |
|---|---|---|---|
| Browse every public page, filter, sort, map view, compare | Yes | Yes | Yes (as a visitor) |
| Change language and currency | Yes | Yes | Yes |
| Wishlist heart, Save Search | No (sent to /login) | Yes | No (a CRM sign-in is not a Customer session; sent to /login) |
| Send property enquiry, Book a Viewing, download brochure / floor plan | Yes (contact details required) | Yes (linked to the account when signed in) | Yes (as a visitor) |
| Custom Property Request on an agent / agency profile | Yes | Yes | Yes (as a visitor) |
| Receive the resulting leads | No | No | Yes: the listing's or profile's agency / agent in the CRM |
| Contact form, home "Get in touch", calculator enquiries | Yes | Yes | Yes |
| Unlock Eligibility / Refinance results | Yes, after sending contact details | Same | Same |
| Apply for a job, newsletter | Yes | Yes | Yes |
| /profile (wishlist, saved searches, enquiries, settings) | No | Yes | No |
| Change website content, filters, calculators copy, SEO | No | No | No (Admin only; Marketing Properties and Premium choices come from the CRM) |

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
| Footer newsletter | Email *, terms tick box * | Unticked: "Please agree to the terms & conditions to subscribe." Success: "Thanks for subscribing!" or "You are already subscribed." |
| Home "Get in touch" | Name *, Email *, Phone *, "what are you interested in?" * (Buy a property, Rent a property, Sell / list a property, Property management), Message | Name and Email up to 255, Message up to 2,000. Button "Contact Our Expert" ("Sending…"). The message is optional when an interest is chosen. |
| Contact page | Name *, Email *, Phone, Message * | "Please enter a message."; Message up to 2,000. |
| Property enquiry | Name *, Email *, Phone, Property (shown), Message * (pre-filled) | Server: message up to 2,000. Button "Send Enquiry" ("Sending…"). |
| Book a Viewing | Day *, Time *, Name *, Email *, Phone *, Note | "Please pick a day.", "Please pick a time."; Note up to 1,000. Server: date from today up to 60 days ahead; with open-house days: "Please pick one of the open house days for this property." |
| Brochure / floor-plan download | Name *, Email *, Phone * | Same shared rules. Button "Continue to download" ("Preparing…"). |
| Custom Property Request (agent / agency) | First Name *, Last Name, Email *, Phone, Property Category (Apartment, Villa, Townhouse, Penthouse, Commercial), Specification, Price Range, Area (sqft), Preferred Location, Additional Details, Move-in Timeline (Immediate, 1–3, 3–6, 6–12 Months, Flexible), Furnishing Status (Furnished, Unfurnished, Semi-Furnished, No Preference) | Missing first name: "First Name is required." Server: category, area and timeline up to 100; details up to 2,000; others up to 255. Button "Submit Request" ("Submitting…"). |
| Calculator enquiry / unlock popup | Name *, Email *, Phone, Message (enquiry only) | Message up to 2,000. Up to 20 calculation lines of up to 150 characters each. Buttons "Get a mortgage quote" / "Continue" ("Sending…"). |
| Refinance steps | every field except tenure months | Empty: "This field cannot be left empty." Lender: "Please select your current lender." Original loan below AED 100,000: "Minimum mortgage amount should be AED 100,000." Valuation below 300,000: "Property value must be at least AED 300,000." (shown while typing). Term: "Please enter the full loan term." Start date: "Mortgage start date cannot be in the future." / "… cannot be before 1950." / "With this start date and term, the loan is already paid off." Rate (0–30): "Enter your interest rate (e.g. 5.25)." Employment: "Please choose your employment status." Income 10,000 or less: "Total monthly income must be more than AED 10,000." Tenure: "Please enter how long you have been in your job." Date of birth: future / before 1950 / "Minimum age should be 18." / "Maximum age should be 64." Balance above the original loan: "The balance cannot be more than the original loan." Amounts are typed in the selected currency (up to 12 digits); minimums are converted for display. |
| Career application | Full Name *, Email Address *, Phone *, Country, Experience (Less than 1 year … 10+ years), Designation, Additional information, CV *, Privacy * | CV: PDF, DOC or DOCX, up to 5 MB, drag and drop or browse. Messages: "Please attach your CV.", "Your CV must be a PDF or Word document.", "Your CV must be 5 MB or smaller.", "Please accept the privacy policy to continue." Phone up to 50, Additional information up to 2,000. |
| Customer sign-up | Full Name *, Email *, Phone, Password *, Confirm *, Terms * | Password: at least 8 characters, one lowercase, one uppercase, one number, one special character (strength Weak / Medium / Strong). "Passwords match" / "Passwords do not match". "Please enter a valid email address." / "Please enter a valid phone number." Server: email must be unused. |
| Verify email | 4-digit code | 10-minute expiry; "Invalid or expired code."; resend after 30 s; "A new code has been sent." |
| Login (User) | Email *, Password * | "Invalid credentials." Limits: 5 tries per minute per email + IP, 30 per minute per IP. |
| Forgot / reset password | Email *, then New Password *, Confirm * | Same 5 password rules on screen; the server needs at least 8 characters and matching confirmation. |
| Account Settings | Full Name *, Email Address *, Phone, Location, New Password, Confirm, Photo | Email must be unique; phone up to 50; location up to 255; password at least 8 and confirmed; photo an image up to 2 MB. Success message "Settings updated." |

**Rate limits (per IP address):**
- property enquiry, viewing, downloads and agent / agency requests: 20 per hour together (lead-capture);
- contact, home and calculator forms, newsletter, career applications and forgot / reset password: 20 per hour each (form-submit);
- sign-up: 10 per hour;
- email code verify / resend: 8 per minute and 30 per day.

A blocked request shows the server's "Too Many Attempts." text or the generic error.

## 5. Test scenarios

Priority: High = core flow, wrong numbers, or data going to the wrong place; Medium = secondary behaviour; Low = cosmetic.

Test data needed:
- live sale and rent listings in at least two emirates, one with a brochure, floor plans (one downloadable), open-house days, a permit, nearby places and no price;
- one commercial listing;
- one Premium (featured) listing and a non-empty Marketing Properties list;
- one approved independent agent, one agency with an agent;
- Arabic active as a second language, and USD as an active currency;
- a test Customer account (e.g. tester.customer@example.com).

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| HD-01 | Header menu order and active item | High | 1. Open /.<br>2. Click each header link in turn: Home, Buy, Rent, Commercial, Agents, Agencies, Market Insights.<br>3. Open /properties with no query. | Order matches section 2.1. Each opens its page and is highlighted; Buy opens /properties?listing_type=sale and Rent ?listing_type=rent. On /properties with no purpose neither Buy nor Rent is highlighted. Home has the transparent header; other pages the light one. |
| HD-02 | Calculators and Explore dropdowns | Medium | 1. Hover **Calculators**, then **Explore**.<br>2. Click Explore, press Esc.<br>3. Open Explore, click **Blog**. | Each lists its links (4 calculators, or the Admin list; About Us, Contact Us, Blog, Careers). Only one is open at a time. Esc closes it and focuses its button. After clicking Blog the menu closes and stays closed while the pointer is still over it. The parent is highlighted on its pages. |
| HD-03 | Arabic and right-to-left | High | 1. Choose Arabic in the language dropdown.<br>2. Visit Home, /properties, a property page, /agents, /mortgage-calculator and /contact.<br>3. Reload the page. | Menus, labels and Admin content switch to Arabic and the layout flips right-to-left; nothing overlaps or is cut off. Some texts stay English (section 7). After the reload the site is back in English (the choice is not remembered). |
| HD-04 | Currency switcher | High | 1. Choose USD.<br>2. Check Home cards, /properties cards and price presets, a property page, /mortgage-calculator.<br>3. Reload; open a new tab. | All prices show "USD …" converted at the Admin rate; presets read e.g. "Under USD 136K"; calculator boxes show USD. The choice is kept after reload and in new tabs. Filtering by price still returns the same listings as in AED. |
| HD-05 | Login and account menu | High | 1. Signed out, check the header.<br>2. Sign in as the test Customer.<br>3. Open the account dropdown; click Profile; then Logout. | Signed out: **Login**. Signed in: avatar/icon and first name; dropdown Profile and Logout. Profile opens /profile and the menu closes. Logout ends on /login with the header back to Login. |
| HD-06 | Mobile menu and bottom bar | Medium | 1. Set the browser to 375 px wide.<br>2. Open ☰; check its contents; close with ×.<br>3. Use the bottom bar on Home and on /blogs. | Side panel lists primary, Explore and Calculator links in one list, Login (or "{name} — Profile" and Logout), language and currency. Bottom bar: Home, Buy, Search, Rent, Login/Profile; the current one is highlighted. Search on Home jumps to the banner search; on /blogs it opens /properties. No sideways page scroll. |
| HD-07 | Footer links and newsletter | Medium | 1. Click "Properties in Abu Dhabi", "Villas for Sale", "Off-Plan in Sharjah", each Legal link.<br>2. Newsletter: enter newsletter.tester@example.com without the tick box; then tick and send; send again. | Links open /properties with location / property_type / completion_status filters and headings such as "Properties in Abu Dhabi". Without the tick: "Please agree to the terms & conditions to subscribe." Then "Thanks for subscribing!", and the second time "You are already subscribed." The entry is in Admin > Newsletter Signups. |
| HD-08 | AI chat entry point | Low | 1. Check the bottom corner on several pages; open and close the chat. | The chat button is on every page and opens the chat panel. Behaviour is covered in Module 26. |
| HM-01 | Banner search | High | 1. On Home pick **Rent**, type "Mar" in Location and pick a Community suggestion, Bedroom 2, Property Type Apartment, move the price max down; **Search**.<br>2. Back on Home, type a listing title and pick an Address/Property suggestion; Search.<br>3. Type free text without picking; Search. | 1: /properties?listing_type=rent&community=…&bedrooms=2&property_type=apartment&max_price=… with matching results. 2: that property's page opens directly. 3: ?location={text}. Suggestions appear after 2 characters, labelled City / Community / Address / Property. Ready / Off-Plan tabs send completion_status. |
| HM-02 | Price range panel | Medium | 1. Open Price Range; click each Quick select preset; drag the slider.<br>2. Switch currency to USD and reopen. | Presets move the slider thumbs; Min / Max read-outs follow. In USD the read-outs and preset labels are in USD. Searching with both ends untouched adds no price to the URL. |
| HM-03 | New Projects city tabs | Medium | 1. In "Browse New Projects in the UAE" click a city tab, then **View All Properties**.<br>2. Click a city that has no listings. | Up to 8 cards for that city load. View All opens /properties?location={city}. An empty city shows no cards but the section and tabs stay. Order changes between reloads (shuffled). |
| HM-04 | Premium, Luxury and Realty sliders | High | 1. Check the Premium Properties slider and its button.<br>2. Check Luxury Project against Common Titles > Luxury Project Min/Max Price.<br>3. Check Realty Property and its button with a non-empty, then an empty Marketing Properties list (ask the Super Admin). | Premium shows only featured listings (up to 12); the button opens /premium-properties. Luxury shows residential listings within the price range. Realty shows the Marketing list in its order (up to 12) with the button to /marketing-properties; with an empty list it shows other live listings (not those in Luxury) and the button goes to /properties. Cards open their property pages; hearts work. |
| HM-05 | Communities, Popular Places, Find Properties | Medium | 1. In Communities click a row on each tab For Sale / For Rent / Off Plan.<br>2. Click a Popular Place card and a Find Properties card. | Rows open /properties?listing_type=sale or rent (or completion_status=off_plan)&location={community}. Place cards open ?location={place}; type cards ?property_type={type} and show "N properties". |
| HM-06 | Sections from Admin, hidden when empty, ad slider | Medium | 1. Admin switches off all testimonials (or another section's items).<br>2. Wait up to 3 minutes; reload Home.<br>3. Check the ad slider with 0, 1 and 2+ running home ads. | The empty section disappears entirely (no placeholder cards). Common Titles changes show at once; item changes within 3 minutes. Ad slider: hidden with no ads; one ad static; several rotate with progress bars and arrows, pause on hover. |
| HM-07 | Home "Get in touch" form | High | 1. Send with an empty phone.<br>2. Fill Name "Test Visitor", Email visitor.home@example.com, Phone 501234567, interest "Rent a property", no message; send. | 1: "Please enter your phone number." 2: /thank-you "Thank you, Test!" with "Thanks for your interest — your enquiry is in.". Admin > Enquiries has the entry with page source "Home Page" and message starting "Interested in: …"; the admin notification email arrives. |
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
| PD-02 | Tabs per listing | Medium | 1. Open listings with and without floor plans, amenities, map point, cheques/deposit.<br>2. Click each tab; on a phone use the tab dropdown. | Only tabs with content appear; each scrolls to its section; the phone dropdown lists the same options. Mortgage & Investment / Rental Costs / Market Insights follow Module 26. |
| PD-03 | Gallery and floor plans | Medium | 1. Use the top slider arrows; open the Gallery tab and click "+N photos".<br>2. In Floor Plans click each plan and the drawing. | Slider moves (short galleries repeat). Lightbox opens with all photos. Each plan shows its size and price range in the selected currency; the drawing opens zoomed with its label as the caption. |
| PD-04 | Location and nearby places | Medium | 1. Open Location & Nearby on a listing with nearby places.<br>2. Click each type chip and "All". | Address line and map with the listing pin. Places of the chosen type are listed with distances and shown on the map. A listing without a map point but with an address shows the address only. |
| PD-05 | Enquiry form validation | High | 1. Clear the name and send.<br>2. Enter email "visitor@test" and send.<br>3. Enter phone "12ab".<br>4. Enter phone "123". | Messages in order: "Please enter your name.", "Please enter a valid email address.", "The phone number may only contain digits.", "The phone number must be 7–13 digits (without the country code)." Nothing is sent. |
| PD-06 | Enquiry reaches the right CRM | High | 1. On a listing owned by the test agent, send Name "Test Buyer", Email buyer.one@example.com, Phone 501234567 and the pre-filled message.<br>2. Check the thank-you page and the agent's CRM > Leads. | /thank-you: "Your enquiry about "{name}" has been sent.", "{agent name} will get in touch with you shortly.", "Sent to" card with Call / WhatsApp, **Back to Property**. The lead is in that agent's (or agency's) CRM with source property-detail. As a signed-in Customer it also appears in /profile > My Enquiries. |
| PD-07 | Book a Viewing | High | 1. Click **Book a Viewing** with no open-house days; submit without choosing.<br>2. Pick a day and Evening, fill details, Request Viewing.<br>3. On a listing with open-house days, click a date button. | 1: "Please pick a day." then "Please pick a time.". 2: "Viewing requested" with e.g. "Friday, 6 November 2026 · 4 PM – 7 PM" and **Done**; CRM lead message "Viewing request for "…" — …". The day list is the next 14 days. 3: only the open-house days are offered, the clicked one pre-selected. |
| PD-08 | Brochure and floor-plan downloads | High | 1. Click **Download Brochure**; send with an empty phone.<br>2. Fill all fields; **Continue to download**.<br>3. Click **Download Floor Plan** in Floor Plans. | 1: "Please enter your phone number.". 2: "Preparing…", "Downloading… N%", "Download complete — check your downloads folder."; the file saves; the popup closes by itself; a lead "Requested the brochure for …" is in the CRM. 3: the same popup titled "Get the floor plan" with an empty form. Buttons are absent when no brochure / downloadable plan exists. |
| PD-09 | Contact card and permit block | Medium | 1. Check "Listed by" on an agency agent's listing; click Call, WhatsApp, Email, View profile, View listings, the agency mini buttons.<br>2. Check Regulatory information on a listing with a permit. | Buttons appear only for filled details; WhatsApp opens wa.me with digits only. "Part of" shows the agency. Rows Reference, Listed, "{issuer} permit number", Broker License, Agency name, Zone name, Agent License; **Verify this ad** opens a new tab; the QR shows (or hides if broken). |
| PD-10 | Similar properties and not found | Medium | 1. Scroll to Similar Properties and use its arrows.<br>2. Switch the listing off in the CRM; reload its URL.<br>3. Open /property-details/no-such-listing. | 1: slider of related listings (Module 26 rules). 2 and 3: "Property Not Found" / "This property listing doesn't exist or is no longer available." with **Back to Properties**. |
| AG-01 | Agents directory search | Medium | 1. Open /agents; search by part of an agent's name, a preferred area, an agency name.<br>2. Search for "zzz". | Only approved, active agents, A–Z. Each search narrows the cards (case-insensitive). "zzz": "No agents match your search.". Grid / list toggle switches layouts. |
| AG-02 | Agent profile and listings | High | 1. Open an agent profile.<br>2. Check chips, contact buttons, share buttons, About Me / My Listing Properties tabs.<br>3. Click **View All**. | Rent / Sell counts match their live listings; "Listings by {name} (N)" shows up to 10 newest. Instagram share copies the link and shows "Link copied". View All opens /properties?agent={slug} with chip "Agent: {name} ×" and heading "Properties by {name}"; × clears it. |
| AG-03 | Agent Custom Property Request | High | 1. Click **Custom Property Request**; submit empty.<br>2. Fill First Name "Test", Email request.agent@example.com, Category Villa, Price Range "AED 2M - 3M", Timeline 1–3 Months; Submit Request.<br>3. Check the CRM. | 1: "First Name is required." 2: drawer closes; /thank-you "Your property request has been received." / "Your agent is already on it…". 3: independent agent: lead in their CRM; agency agent: lead in the agency's CRM with that agent preferred. Source "agent-profile-request"; message lists the answers. |
| AC-01 | Agencies directory search | Medium | 1. Open /agencies; search by name and by a word from an office address.<br>2. Compare a card's "Properties : N" with the profile counts. | Only approved, active agencies, A–Z. Search narrows the list; no match: "No agencies match your search.". Note any difference between the card count and the profile's total (section 7). |
| AC-02 | Agency profile | Medium | 1. Open an agency profile.<br>2. Click both **View All** buttons. | Logo, "Since {year}", contact icons, office address, counts (agents, total, for sale, for rent), bio, map, listings (10 newest), up to 6 agents. View All listings opens /properties?agency={slug}; View All agents opens /agents?agency={slug} with the Agency box filled and only that agency's agents. |
| AC-03 | Agency Custom Property Request | High | 1. Send the request form from an agency profile with valid fake details. | /thank-you shows "The agency is already on it and will share properties that match your brief." The lead is in the agency's CRM with source "agency-profile-request". |
| MI-01 | Market Insights list | Medium | 1. Open /market-insights.<br>2. Click a topic chip, choose a Market, go to page 2.<br>3. Copy the URL to a new tab; then pick filters with no posts. | Featured Report on top (first page only, not repeated below). Filters and page go into the URL and survive the new tab; 9 per page. No posts: "No insights found" / "Nothing matches these filters yet — try another topic or market." with **Clear filters**. |
| MI-02 | Insight report page | Medium | 1. Open a report.<br>2. Click Copy link, the topic chip, **Download full report**, **Contact our team**.<br>3. Open /market-insights/unknown. | "Copied!" appears; topic chip opens the filtered list; Download opens the file/link (hidden when none); Contact opens /contact. Related Insights shows up to 3. Unknown: "Insight Not Found". |
| CA-01 | Mortgage defaults (example 1) | High | 1. Open /mortgage-calculator; leave defaults.<br>2. Expand **Total purchase costs**. | Monthly payment AED 4,936 "at 3.75% over 25 years"; Total loan repayment AED 1,480,698; Total interest AED 520,698; fees 48,580 / 4,200 / 2,410 / 25,200 / 10,080 / 3,150 = 93,620; upfront AED 333,620. Same inputs on Property Finder's calculator give the same figures. |
| CA-02 | Mortgage residency, high value, limits | High | 1. Choose UAE National, price 2,000,000.<br>2. Choose Resident, type price 6,000,000.<br>3. Type down payment below the minimum; type rate 15; type price "1.5M"; open /mortgage-calculator?price=900000. | 1: down 15%, rate 4.24%, monthly AED 9,200, upfront AED 452,040. 2: down jumps to 30% with "(homes above AED 5M)", monthly AED 21,594. 3: values snap to the limits (minimum %, 10%); "1.5M" is read as 1,500,000; the URL price seeds the field. **Browse properties in budget** opens /properties?listing_type=sale&max_price={price}. |
| CA-03 | Eligibility lock and unlock | High | 1. Open /eligibility-calculator in a fresh tab.<br>2. Click **Unlock your results**; send with a bad email; then valid fake details.<br>3. Change income; click **Check eligibility**. Open a new tab. | 1: figures blurred, "AED 0,000,000". 2: "Please enter a valid email address."; then the results show and an Enquiry "Eligibility Calculator" with the figures is in Admin. 3: "You have changed your details — check again…", Check eligibility updates without the form. A new tab is locked again. |
| CA-04 | Eligibility maths | High | 1. Defaults (30,000, no debts, Resident, first home).<br>2. Liabilities 2,000, card limits 50,000.<br>3. Income 10,000, liabilities 5,000. | 1: price AED 3,646,929, loan AED 2,917,543, monthly AED 15,000, down payment (20%) AED 729,386. 2: price AED 2,552,850, loan AED 2,042,280. 3: "Your monthly liabilities already use 50% or more of your income…" with zero figures. First home "No" (case 1): price AED 4,488,528, down (35%). |
| CA-05 | Rent vs Buy | High | 1. Open /rent-vs-buy-calculator with defaults.<br>2. Set rent 60,000, loan period 5.<br>3. Open Assumptions; switch to Variable; set yearly growth values. | 1: "Buying is cheaper" by AED 2,561,576; "Buying pays off after" 2 years; monthly Rent 11,667 vs Buying 5,415; breakdown net costs 3,569,486 (rent) and 1,007,910 (buy). 2: Buying cheaper by AED 7,251, pays off after 5 years. 3: figures update; labels switch to "Lost if buying / Saved if renting" when renting wins; charts and View as table match. |
| CA-06 | Refinance validation | Medium | 1. Open /refinance-calculator; press Next with nothing filled.<br>2. Enter original loan 50,000, valuation 200,000, a future start date, rate 40.<br>3. On step 3 enter DOB making age 70 and a balance above the original loan. | Messages from section 4 appear under each field ("This field cannot be left empty.", "Please select your current lender.", "Minimum mortgage amount should be AED 100,000.", "Property value must be at least AED 300,000." while typing, "Mortgage start date cannot be in the future.", "Enter your interest rate (e.g. 5.25).", "Maximum age should be 64.", "The balance cannot be more than the original loan."). The step does not advance. |
| CA-07 | Refinance results (example 1) | High | 1. On 2 Nov 2026 enter example 1 from 2.8; on step 3 note the pre-filled balance, overwrite with 1,000,000, equity 0.<br>2. **See my savings**; fill the contact popup.<br>3. Repeat example 2. | Pre-filled balance 1,045,255 with the "Estimated from your loan details…" hint. Results: 5.43% → 3.75%, AED 6,855 → AED 5,141, saving AED 1,714/month, 20,565 first year, 61,696 over 3 years, cost 18,720, term left 19 years 11 months. Example 2: age note and "Refinancing would not lower your payments". An Enquiry "Refinance Calculator" is in Admin. |
| CA-08 | Calculator enquiry form | High | 1. Under any calculator fill Name, Email calc.tester@example.com, a message; **Get a mortgage quote**.<br>2. Check Admin > Enquiries and the admin inbox. | /thank-you (contact wording). Enquiry with page source "{Calculator} Calculator" and the message followed by "Label: value" lines in AED (e.g. "Monthly payment: AED 4,936"), even when the screen was in USD. Admin email sent. |
| CA-09 | Admin copy, FAQs and menu | Medium | 1. In Admin > Calculators change a page heading, add 2 FAQs, hide one calculator from the menu.<br>2. Reload the website; switch to Arabic. | Heading and FAQs show (FAQs open one at a time). The hidden calculator is gone from the header and the page tabs but its URL still opens. Arabic shows the Arabic copy when entered. |
| EX-01 | About Us | Low | 1. Open /about. | Sections show the Admin content; empty sections hidden; no placeholder text errors. |
| EX-02 | Contact form | High | 1. Send with no message.<br>2. Send Name "Contact Tester", Email contact.tester@example.com, Phone 501234567, a message. | 1: "Please enter a message." 2: /thank-you "Thank you, Contact!" and "Your message is on its way to our team.". Admin > Enquiries entry with source "Contact Page"; admin email; the visitor appears in CRM Website Leads (Module 04). Office details, map and social links come from Admin. |
| EX-03 | Blog list and article | Medium | 1. Open /blogs; click a category pill; page through.<br>2. Open an article; use Previous / Next post.<br>3. Open /blog-details/unknown. | 12 per page; category filtering also filters the paging. Article shows cover, category, author, date, reading time; first/last articles show "Earliest article" / "Latest article". Unknown slug shows the not-found text. |
| EX-04 | Careers list | Medium | 1. Open /careers; choose a Department, then a Location.<br>2. Open a job. | Stats "Open Positions" and "Teams Hiring" are shown; filters apply on change and go into the URL; job cards show location / type / base. The job page shows the details and the application form. |
| EX-05 | Career application | High | 1. On a job page submit with no CV.<br>2. Attach a .png, then a 6 MB PDF.<br>3. Attach a small PDF, leave privacy unticked; submit; then tick and submit. | 1: "Please attach your CV." 2: "Your CV must be a PDF or Word document." / "Your CV must be 5 MB or smaller." 3: "Please accept the privacy policy to continue."; then "Thanks for applying — our team will review your application and get back to you." and the form clears. Admin > Careers > Candidates lists it under the job title (General Application from /careers#apply); admin email sent. |
| EX-06 | Legal pages, landing pages and 404 | Medium | 1. Open each legal page and /cookie-settings.<br>2. Open an active landing page slug and submit its form.<br>3. Open /this-does-not-exist and use its search box. | Legal pages show Admin text (cookie settings is text only). The landing page renders; its form entry appears in Admin > Landing Pages > Enquiries. The 404 page shows "Page Not Found", the requested path, popular links, and search opens /properties?location=…; DevTools shows HTTP 404 on a direct load. |
| EX-07 | SEO and tracking visible to a tester | Low | 1. Check tab titles on 5 pages.<br>2. Open /sitemap.xml, /robots.txt, /llms.txt.<br>3. In DevTools open Application > Cookies and the Network tab while browsing. | Titles follow section 2.9 (property pages: the listing name followed by "MW Realty"). The three files load and reflect the last Admin > SEO generation. Cookie mw_vid exists (HttpOnly, about 2 years). POST /track/page fires about 1 s after each page change, with /time beacons on leaving; no visible change on screen. |
| AU-01 | Customer sign-up | High | 1. Open /signup, User tab; type a weak password, then a mismatched confirmation.<br>2. Use an email that already has an account.<br>3. Complete with a new fake email. | Strength shows Weak / Medium / Strong; each rule ticks off; "Passwords do not match"; **Create Account** stays disabled until all rules, match and the terms tick are met. An existing email gives the server's "already been taken" error. A new email opens /verify-email. |
| AU-02 | Verify email code | High | 1. Enter a wrong code.<br>2. Wait for the 30 s countdown; click Resend.<br>3. Enter the right code. | "Invalid or expired code."; Resend is disabled while "Resend in {seconds}s" counts down, then sends "A new code has been sent."; the correct code shows "Verified Successfully" and Continue to Account opens /profile. |
| AU-03 | Log in | High | 1. On /login User tab enter a wrong password.<br>2. Enter correct details.<br>3. Switch to Agent / Agency tab with a CRM account.<br>4. Open /agent-login. | "Invalid credentials."; correct details open /profile and the header shows the name. The Agent / Agency tab signs in to the CRM (Module 12). /agent-login redirects to /login. |
| AU-04 | Forgot and reset password | High | 1. /forgot-password with an unknown email; with an unverified email.<br>2. With the verified test email, set a new valid password.<br>3. Open /reset-password without a token. | "No account found with this email." / "This email is not verified yet — please complete sign-up first."; the verified email goes straight to the new-password step (no email sent), then /login with "Your password has been reset — sign in with your new password."; without a token: "Invalid reset link" and Request New Link. |
| AU-05 | My Account tabs | High | 1. As the Customer with saved items, open /profile.<br>2. Visit Overview, Wishlist, Saved Searches, My Enquiries.<br>3. Delete a saved search; View Results on another.<br>4. Open /profile signed out. | Counts match the lists; empty tabs show their empty texts. Delete removes the row; View Results opens /properties with the saved filters. My Enquiries lists property, sent to, date and status. Signed out: redirected to /login. |
| AU-06 | Account Settings and logout | Medium | 1. Change Full Name and upload a 1 MB JPG; Save Changes.<br>2. Upload a 3 MB image; save.<br>3. Set a new password with a wrong confirmation.<br>4. Click Log Out. | 1: "Settings updated."; header name and avatar update at once. 2: the image-size error. 3: the confirmation error; the password is unchanged. 4: back on /login, header shows Login. |

## 6. Expected results checklist

- Header order is Home, Buy, Rent, Commercial, Agents, Agencies, Market Insights, Calculators, Explore; the active item is highlighted; dropdowns close on Esc, outside click and navigation.
- Language switch translates the site and flips to right-to-left; currency switch converts every price, preset and calculator amount and is remembered.
- Every Home section shows Admin or listing data, hides when empty, and its links open correctly filtered lists.
- /properties filters, sort and paging (12 per page) agree with the results and survive a reload; /commercial (6 per page) filters work; map pins follow the filters and the visible area.
- Save Search and the wishlist need a Customer sign-in and show up in /profile.
- Property pages show only tabs with content; enquiries, viewings and downloads create leads in the right agency or agent CRM and go to the right thank-you page.
- Agent and agency directories search correctly; profile request forms reach the right CRM.
- Calculators reproduce the worked examples in section 2.8 and match Property Finder for the same inputs; enquiries carry the figures in AED.
- Contact, home, calculator and career forms land in the right Admin list with an admin email.
- Sign-up, verification, login, password reset and /profile work with the messages in section 4.
- No console errors and no sideways page scroll at 375 px on any page.

## 7. Known behaviour (not bugs)

- The language choice is not remembered; a reload returns to the default language. Every non-default language switches the layout to right-to-left, not only Arabic (per code; confirm if a left-to-right second language is ever added).
- Some texts are built-in English on every language: the brochure / floor-plan download popup ("Download Brochure", "Get the property brochure", "Continue to download", "Download complete — check your downloads folder."), client-side validation messages ("Please enter your name.", phone and email messages), list headings built from filters ("Properties for Rent in Dubai"), and Home fallback headings when Admin text is empty.
- Listing cards on Home, /properties, /commercial and /profile > Wishlist have Email / Call Us / WhatsApp buttons that go to MW Realty's fixed company contacts, not the listing's agent, and are not counted as lead clicks. Only the property page contact card uses the agent's details. Confirm with the product owner whether this is intended.
- /commercial clears the URL after any filter change, so a reload or shared link after filtering shows the unfiltered list. /properties keeps filters in the URL.
- Commercial cards have no wishlist heart.
- The ticker's "Invest in Off Plan Properties" links (1–4 "Off Plan Properties in Abu Dhabi") are placeholders that go nowhere. The "{N} Bedroom Properties for sale in dubai" links open /properties?bedrooms=N without a sale filter, so rentals appear too (per code; looks unfinished).
- The share icons on blog articles are placeholders and do not share.
- "Serves in Dubai" is shown for every agent, and the map on agency profiles always shows Dubai, whatever the agent's areas or agency's address (fixed in code).
- The "Properties : N" count on /agencies cards counts all the agency's listings, including offline ones, while the profile page counts only live listings, so the numbers can differ (per code; looks like a bug).
- An agent or agency profile can open for an approved account that has been deactivated, but its Custom Property Request then fails with the generic error (the request needs an active account).
- Saved searches made on /commercial reopen on /properties ("View Results" always goes to /properties), so the results do not match the commercial search (per code; looks like a bug).
- The Custom Property Request is no longer on /properties; it is on agent and agency profiles only (removed from the listing page in September 2026).
- New Projects on Home shows any live listings (not only off-plan); Home sliders and New Projects are shuffled on every load.
- Home content is cached up to 3 minutes; listing, agent and agency pages are also cached briefly, so CRM changes may need a short wait.
- Agents and agencies load in one go with no paging; search runs in the browser.
- Forgot password has no email step: any verified customer email can be given a new password straight away, and the page says whether an email is registered. The code says this was requested, but it is a security risk; confirm with the product owner before release.
- A customer account that never entered its email code can still sign in with its password on /login (the login does not check verification; looks like a bug).
- Eligibility and Refinance unlocks last for the browser tab session only; a new tab asks for contact details again and creates another enquiry.
- Refinance figures change slightly from day to day because the months left are counted from today.
- The Eligibility lead summary stores residency as a code (e.g. "non-resident") instead of the label.
- Spelling in the text files: "Comming Soon" (home banner badge fallback), "Properties for sale in dubai", page title "Our Agent".
- The project overview (Module 00) lists the Custom Property Request form on /properties; that is out of date.
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

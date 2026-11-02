# Website: Home, Header & Account

The home page, the header and footer on every page, the AI chat, language and currency, and customer sign-up, login and My Account.

**Where:** Public website: Home (/), the header, footer and mobile bottom bar on every page, the AI chat widget (bottom corner, every page), /signup, /login, /verify-email, /forgot-password, /reset-password and /profile. **Who:** Visitors, Customers (website "User" accounts); agencies and agents sign in at the same /login. **Locks:** None on browsing; wishlist, Save Search and /profile need a Customer sign-in. The AI chat needs an AI provider configured on the server.

## 1. What this page covers

Home shows Admin-managed sections and live listings. The header and footer appear on every page and hold the menu, language and currency switchers and the account menu. The AI chat answers visitors in 10 languages and turns them into leads. Customers sign up with an email code and manage their wishlist, saved searches and enquiries in My Account.

| Page / menu | URL | What it does |
|---|---|---|
| Header | every page | Logo, menu (Home, Buy, Rent, Commercial, Agents, Agencies, Market Insights, Calculators ▾, Explore ▾), Login or account menu, language and currency switchers, mobile menu button. |
| Footer and mobile bottom bar | every page | Link columns, contact details, newsletter, social links. On phones there is a bottom bar: Home, Buy, Search, Rent and Login / Profile. |
| Home | / | Banner search, partner logos, New Projects, Premium Properties, ad slider, Post your property, Popular Places, Luxury Project, Why Choose Us, Realty Property, Communities, Find Properties, Testimonials, "Get in touch" form. |
| Legal pages | /terms-and-conditions, /privacy-policy, /security-policy, /cookie-settings | Admin-managed text pages. |
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
  - Rates and the hourly sync are covered in W2 (Buy, Rent & Commercial).
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
- **AI chat:** a floating chat button is on every page. It is covered in W2 (Buy, Rent & Commercial).

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

### 2.3 Sign up, log in, verify email, forgot / reset password, My Account

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

### 2.5 Currency rates

- Prices are stored in AED. The switcher multiplies by each currency's rate (units per 1 AED) as before.
- **Automatic sync** runs only when the server setting CURRENCY_RATES_AUTO_SYNC=true. The default is off, so the rates Admin types in are not overwritten. When on, it runs every hour and updates every active currency except AED from the feed (default https://open.er-api.com/v6/latest/AED). A currency the feed does not list keeps its rate. Every updated currency gets a new "updated" time even when the rate did not change.
- A developer can also run the command `currencies:sync-rates` by hand. Messages: "Updated N currency rate(s).", "CODE: not in the feed, rate kept.", "Rate feed unreachable: …", "Rate feed returned an unexpected response (HTTP n)."
- The public currency list and the "rates as of" time are cached for up to 10 minutes. Saving or deleting a currency in Admin clears the cache. "Rates as of" is shown only on /compare, and only when a non-AED currency is selected.

### 2.6 Tracking

- When the Market Insights / Investment section comes into view (at least 25% visible), or the visitor clicks **Investment returns**, an "insight view" is counted for the listing. It is counted once per listing per page load, and bots and offline listings are ignored. The counter is stored in the listing's daily stats but is **not yet shown in any CRM report** (per code).
- Listing card impressions and contact-click tracking are unchanged.

## 3. Who can do what

| Action | Visitor | Customer | Agency / Agent |
|---|---|---|---|
| Change language and currency | Yes | Yes | Yes |
| Wishlist heart, Save Search | No (sent to /login) | Yes | No (a CRM sign-in is not a Customer session; sent to /login) |
| Custom Property Request on an agent / agency profile | Yes | Yes | Yes (as a visitor) |
| Receive the resulting leads | No | No | Yes: the listing's or profile's agency / agent in the CRM |
| Contact form, home "Get in touch", calculator enquiries | Yes | Yes | Yes |
| Apply for a job, newsletter | Yes | Yes | Yes |
| /profile (wishlist, saved searches, enquiries, settings) | No | Yes | No |
| Change website content, filters, calculators copy, SEO | No | No | No (Admin only; Marketing Properties and Premium choices come from the CRM) |

| Action | Visitor | Customer | Agency / Agent | Admin |
|---|---|---|---|---|
| Use AI chat and change chat language | Yes (one free question, then details form) | Yes | Yes | Yes |
| See chat "Looking for" chips | No | No | CRM Lead Insights for their own leads (plan/KYC rules as before) | Super Admin in CRM |
| Edit currency rates | No | No | No | Admin > General Settings > Currencies |
| Turn the hourly rate sync on, or run it by hand | No | No | No | Developer / server setting only |

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

- **/compare URL:** `slugs` holds comma-separated listing slugs. Blanks and duplicates are dropped, each slug can be up to 255 characters, and only the first 5 are used. Unknown or offline slugs are skipped. `lang` is the site language.
- **Show prices in:** a dropdown of the active currencies ("CODE — Name"). Choosing one also changes the site-wide currency, which is remembered in the browser.
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
- **Chat message:** required, up to 1,000 characters. The language code must be one of the 10 configured codes; anything else falls back to English.
- **Chat details form:** name, email and phone are all required, with the same validation as before.



## 5. Test scenarios

Priority: **High** core flow / data loss / access · **Medium** · **Low** cosmetic. Use fake names and example.com emails.

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| HD-01 | Header menu order and active item | High | 1. Open /.<br>2. Click each header link in turn: Home, Buy, Rent, Commercial, Agents, Agencies, Market Insights.<br>3. Open /properties with no query. | Order matches section 2.1. Each opens its page and is highlighted; Buy opens /properties?listing_type=sale and Rent ?listing_type=rent. On /properties with no purpose neither Buy nor Rent is highlighted. Home has the transparent header; other pages the light one. |
| HD-02 | Calculators and Explore dropdowns | Medium | 1. Hover **Calculators**, then **Explore**.<br>2. Click Explore, press Esc.<br>3. Open Explore, click **Blog**. | Each lists its links (4 calculators, or the Admin list; About Us, Contact Us, Blog, Careers). Only one is open at a time. Esc closes it and focuses its button. After clicking Blog the menu closes and stays closed while the pointer is still over it. The parent is highlighted on its pages. |
| HD-03 | Arabic and right-to-left | High | 1. Choose Arabic in the language dropdown.<br>2. Visit Home, /properties, a property page, /agents, /mortgage-calculator and /contact.<br>3. Reload the page. | Menus, labels and Admin content switch to Arabic and the layout flips right-to-left; nothing overlaps or is cut off. Some texts stay English (section 7). After the reload the site is back in English (the choice is not remembered). |
| HD-04 | Currency switcher | High | 1. Choose USD.<br>2. Check Home cards, /properties cards and price presets, a property page, /mortgage-calculator.<br>3. Reload; open a new tab. | All prices show "USD …" converted at the Admin rate; presets read e.g. "Under USD 136K"; calculator boxes show USD. The choice is kept after reload and in new tabs. Filtering by price still returns the same listings as in AED. |
| HD-05 | Login and account menu | High | 1. Signed out, check the header.<br>2. Sign in as the test Customer.<br>3. Open the account dropdown; click Profile; then Logout. | Signed out: **Login**. Signed in: avatar/icon and first name; dropdown Profile and Logout. Profile opens /profile and the menu closes. Logout ends on /login with the header back to Login. |
| HD-06 | Mobile menu and bottom bar | Medium | 1. Set the browser to 375 px wide.<br>2. Open ☰; check its contents; close with ×.<br>3. Use the bottom bar on Home and on /blogs. | Side panel lists primary, Explore and Calculator links in one list, Login (or "{name} — Profile" and Logout), language and currency. Bottom bar: Home, Buy, Search, Rent, Login/Profile; the current one is highlighted. Search on Home jumps to the banner search; on /blogs it opens /properties. No sideways page scroll. |
| HD-07 | Footer links and newsletter | Medium | 1. Click "Properties in Abu Dhabi", "Villas for Sale", "Off-Plan in Sharjah", each Legal link.<br>2. Newsletter: enter newsletter.tester@example.com without the tick box; then tick and send; send again. | Links open /properties with location / property_type / completion_status filters and headings such as "Properties in Abu Dhabi". Without the tick: "Please agree to the terms & conditions to subscribe." Then "Thanks for subscribing!", and the second time "You are already subscribed." The entry is in Admin > Newsletter Signups. |
| HD-08 | AI chat entry point | Low | 1. Check the bottom corner on several pages; open and close the chat. | The chat button is on every page and opens the chat panel. Behaviour is covered in W2 (Buy, Rent & Commercial). |
| HM-01 | Banner search | High | 1. On Home pick **Rent**, type "Mar" in Location and pick a Community suggestion, Bedroom 2, Property Type Apartment, move the price max down; **Search**.<br>2. Back on Home, type a listing title and pick an Address/Property suggestion; Search.<br>3. Type free text without picking; Search. | 1: /properties?listing_type=rent&community=…&bedrooms=2&property_type=apartment&max_price=… with matching results. 2: that property's page opens directly. 3: ?location={text}. Suggestions appear after 2 characters, labelled City / Community / Address / Property. Ready / Off-Plan tabs send completion_status. |
| HM-02 | Price range panel | Medium | 1. Open Price Range; click each Quick select preset; drag the slider.<br>2. Switch currency to USD and reopen. | Presets move the slider thumbs; Min / Max read-outs follow. In USD the read-outs and preset labels are in USD. Searching with both ends untouched adds no price to the URL. |
| HM-03 | New Projects city tabs | Medium | 1. In "Browse New Projects in the UAE" click a city tab, then **View All Properties**.<br>2. Click a city that has no listings. | Up to 8 cards for that city load. View All opens /properties?location={city}. An empty city shows no cards but the section and tabs stay. Order changes between reloads (shuffled). |
| HM-04 | Premium, Luxury and Realty sliders | High | 1. Check the Premium Properties slider and its button.<br>2. Check Luxury Project against Common Titles > Luxury Project Min/Max Price.<br>3. Check Realty Property and its button with a non-empty, then an empty Marketing Properties list (ask the Super Admin). | Premium shows only featured listings (up to 12); the button opens /premium-properties. Luxury shows residential listings within the price range. Realty shows the Marketing list in its order (up to 12) with the button to /marketing-properties; with an empty list it shows other live listings (not those in Luxury) and the button goes to /properties. Cards open their property pages; hearts work. |
| HM-05 | Communities, Popular Places, Find Properties | Medium | 1. In Communities click a row on each tab For Sale / For Rent / Off Plan.<br>2. Click a Popular Place card and a Find Properties card. | Rows open /properties?listing_type=sale or rent (or completion_status=off_plan)&location={community}. Place cards open ?location={place}; type cards ?property_type={type} and show "N properties". |
| HM-06 | Sections from Admin, hidden when empty, ad slider | Medium | 1. Admin switches off all testimonials (or another section's items).<br>2. Wait up to 3 minutes; reload Home.<br>3. Check the ad slider with 0, 1 and 2+ running home ads. | The empty section disappears entirely (no placeholder cards). Common Titles changes show at once; item changes within 3 minutes. Ad slider: hidden with no ads; one ad static; several rotate with progress bars and arrows, pause on hover. |
| HM-07 | Home "Get in touch" form | High | 1. Send with an empty phone.<br>2. Fill Name "Test Visitor", Email visitor.home@example.com, Phone 501234567, interest "Rent a property", no message; send. | 1: "Please enter your phone number." 2: /thank-you "Thank you, Test!" with "Thanks for your interest — your enquiry is in.". Admin > Enquiries has the entry with page source "Home Page" and message starting "Interested in: …"; the admin notification email arrives. |
| AU-01 | Customer sign-up | High | 1. Open /signup, User tab; type a weak password, then a mismatched confirmation.<br>2. Use an email that already has an account.<br>3. Complete with a new fake email. | Strength shows Weak / Medium / Strong; each rule ticks off; "Passwords do not match"; **Create Account** stays disabled until all rules, match and the terms tick are met. An existing email gives the server's "already been taken" error. A new email opens /verify-email. |
| AU-02 | Verify email code | High | 1. Enter a wrong code.<br>2. Wait for the 30 s countdown; click Resend.<br>3. Enter the right code. | "Invalid or expired code."; Resend is disabled while "Resend in {seconds}s" counts down, then sends "A new code has been sent."; the correct code shows "Verified Successfully" and Continue to Account opens /profile. |
| AU-03 | Log in | High | 1. On /login User tab enter a wrong password.<br>2. Enter correct details.<br>3. Switch to Agent / Agency tab with a CRM account.<br>4. Open /agent-login. | "Invalid credentials."; correct details open /profile and the header shows the name. The Agent / Agency tab signs in to the CRM (Module 12). /agent-login redirects to /login. |
| AU-04 | Forgot and reset password | High | 1. /forgot-password with an unknown email; with an unverified email.<br>2. With the verified test email, set a new valid password.<br>3. Open /reset-password without a token. | "No account found with this email." / "This email is not verified yet — please complete sign-up first."; the verified email goes straight to the new-password step (no email sent), then /login with "Your password has been reset — sign in with your new password."; without a token: "Invalid reset link" and Request New Link. |
| AU-05 | My Account tabs | High | 1. As the Customer with saved items, open /profile.<br>2. Visit Overview, Wishlist, Saved Searches, My Enquiries.<br>3. Delete a saved search; View Results on another.<br>4. Open /profile signed out. | Counts match the lists; empty tabs show their empty texts. Delete removes the row; View Results opens /properties with the saved filters. My Enquiries lists property, sent to, date and status. Signed out: redirected to /login. |
| AU-06 | Account Settings and logout | Medium | 1. Change Full Name and upload a 1 MB JPG; Save Changes.<br>2. Upload a 3 MB image; save.<br>3. Set a new password with a wrong confirmation.<br>4. Click Log Out. | 1: "Settings updated."; header name and avatar update at once. 2: the image-size error. 3: the confirmation error; the password is unchanged. 4: back on /login, header shows Login. |
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

- Header order is Home, Buy, Rent, Commercial, Agents, Agencies, Market Insights, Calculators, Explore; the active item is highlighted; dropdowns close on Esc, outside click and navigation.
- Language switch translates the site and flips to right-to-left; currency switch converts every price, preset and calculator amount and is remembered.
- Every Home section shows Admin or listing data, hides when empty, and its links open correctly filtered lists.
- Save Search and the wishlist need a Customer sign-in and show up in /profile.
- Sign-up, verification, login, password reset and /profile work with the messages in section 4.
- No console errors and no sideways page scroll at 375 px on any page.
- Currency rates are not overwritten unless auto sync is on; "rates as of" follows the last change.
- The chat replies in the chosen language, remembers it, offers switching once per language per page load, and the details form can be dismissed without losing the message.

## 7. Known behaviour (not bugs)

- The language choice is not remembered; a reload returns to the default language. Every non-default language switches the layout to right-to-left, not only Arabic (per code; confirm if a left-to-right second language is ever added).
- Some texts are built-in English on every language: the brochure / floor-plan download popup ("Download Brochure", "Get the property brochure", "Continue to download", "Download complete — check your downloads folder."), client-side validation messages ("Please enter your name.", phone and email messages), list headings built from filters ("Properties for Rent in Dubai"), and Home fallback headings when Admin text is empty.
- The ticker's "Invest in Off Plan Properties" links (1–4 "Off Plan Properties in Abu Dhabi") are placeholders that go nowhere. The "{N} Bedroom Properties for sale in dubai" links open /properties?bedrooms=N without a sale filter, so rentals appear too (per code; looks unfinished).
- New Projects on Home shows any live listings (not only off-plan); Home sliders and New Projects are shuffled on every load.
- Home content is cached up to 3 minutes; listing, agent and agency pages are also cached briefly, so CRM changes may need a short wait.
- Forgot password has no email step: any verified customer email can be given a new password straight away, and the page says whether an email is registered. The code says this was requested, but it is a security risk; confirm with the product owner before release.
- A customer account that never entered its email code can still sign in with its password on /login (the login does not check verification; looks like a bug).
- Spelling in the text files: "Comming Soon" (home banner badge fallback), "Properties for sale in dubai", page title "Our Agent".
- The chat's match reasons are English only. In other chat languages only "N% match" is shown.
- Language detection for the switch offer is a best guess. Short messages, mixed languages, or Latin-script languages other than English, French, German and Spanish do not trigger an offer.
- A declined language offer can come back after a page reload.
- The chat language is stored on the conversation but is not shown in the CRM transcript.
- AI answers vary from run to run. Check language, tone and that listings are real, not exact wording.
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

# Property Import & AI Copy

Add-on to Module 07 (Properties): bulk-import listings from an Excel / CSV file, and write a listing's title, key features and description with AI in the listing form.

**Where:** Properties (or Commercial) list > the caret next to Add Property > Import from Excel / CSV (`/crm/properties/import`, `/crm/commercial/import`); CRM sidebar > Integrations > tile "Excel / CSV import"; listing form > Property Details tab > Description > button Write with AI (`/crm/properties/create`, `/crm/properties/:id/edit`, same on Commercial). **Who:** Import: Agency and Agent accounts only (Super Admin has no listings of its own to import). Write with AI: everyone who can open the listing form (Super Admin, Agency, Agent). **Locks:** the account must be approved (KYC), as for Properties; importing needs free listings on the plan (credits are never used by an import); Write with AI needs the site's AI provider to be set up on the server; Import's AI and translate options need AI / auto-translate set up.

## 1. What this module does

**Property import** lets an agency or agent bring a whole inventory in at once. The tester uploads a spreadsheet as it is (their own sheet, another CRM's export or MW Realty's template), matches the file's columns to MW Realty fields, and starts the import. The import runs in the background; a progress bar shows on the Import page and on the Properties list, and a bell notification arrives when it ends. Every row gets a result (Added, Needs details, Duplicate, Skipped) in a downloadable "status file". Imported listings are always created **off the website** (Inactive) and follow the normal permit rules of Module 07.

**Write with AI** is a button in the listing form. It drafts (or polishes) the title, key features and description of the selected language from the details already filled in on the form. Nothing is saved until the user clicks Save Property / Update Property.

The release also changes how listing web addresses (slugs) are built and moves some Property Finder sync logic into shared code (section 2.11).

| Screen | What the tester sees |
| --- | --- |
| Import listings (`/crm/properties/import`) | "Back to Properties" link, title "Import listings", button Download template; three tiles (Excel / CSV import "You're here", Property Finder sync, Add Property); a step card with steps 1 Upload file, 2 Match columns, 3 Import; side card "What happens to each listing"; table "Previous imports". |
| Step 2: Match columns | Table "MW Realty field / Your column / Sample values", required fields marked *; two tick boxes (AI descriptions, Translate); buttons Back and "Import N listings". |
| Step 3: Progress / result | "Starting import..." / "Importing listings... N%" with a progress bar and counts; then "Import finished — N rows" with ready / need details / duplicates / skipped, Download status file and Refresh listings; or "Import failed". |
| Properties list banner | The same progress / result card above the listings while an import runs, for a day after it ends, or when opened from the notification (`?import=<id>`). |
| Integrations hub | New tile "Excel / CSV import" (category Listings) with status and footer link "Import". |
| Write with AI pop-up | Title "Write with AI", mode (Write new / Improve my description), Tone, optional notes, a Preview with three "Use the ..." tick boxes, buttons Cancel, Write / Try again, Use this. |

## 2. How it works (business rules)

### 2.1 Template and columns

- Download template gives `listing-import-template.xlsx`: one bold heading row with every field below (in this order) and two example rows (a Dubai Marina apartment for sale and an Arabian Ranches villa for rent).
- The user does not have to use the template. On upload, headings are matched automatically when they are a known name, e.g. "Ref", "Beds", "BUA", "Trakheesi", "Price AED", "Area", "Photos". Matching ignores case, spaces and punctuation. Each file column is pre-matched to one field only; the user can still pick the same column for two fields by hand (the drop-down then shows "(also <field>)").
- Fields on the mapping screen (label, then hint shown under it). **Required = \***:

| Field | Required | Hint shown |
| --- | --- | --- |
| Your reference | | Your own listing ID — used to spot duplicates |
| Title | | Written from the details when empty |
| Purpose (sale / rent) | * | Sale, Buy, Rent, Lease... |
| Property type | * | Apartment, Villa, Townhouse, Office... |
| Price (AED) | * | 1,250,000 · 1.25M · 95k |
| Community / area | * | e.g. Dubai Marina, JVC |
| Building / sub-community | | Shown before the community in the address |
| City | | Dubai, Abu Dhabi, Al Ain, Sharjah... |
| Emirate | | Read from the city / community when empty |
| Category | | Residential or Commercial — from this page when empty |
| Completion | | Ready or Off-plan (default Ready) |
| Rent frequency | | Yearly, Monthly... (rentals, default Yearly) |
| Bedrooms | | Number, or Studio |
| Bathrooms, Size (sq.ft) | | |
| Size (sq.m) | | Converted to sq.ft |
| Furnishing | | Furnished, Semi, Unfurnished, Yes / No |
| Description | | Plain text or HTML |
| Key features | | One per line, or separated by ; |
| Amenities | | Comma separated: Pool, Gym, Parking... |
| Photo URLs | | Links separated by commas, spaces or new lines |
| Permit number | | DLD (Trakheesi) / ADREC permit |
| Permit expiry date | | |
| Latitude | | Needed for the map pin |
| Longitude, Agent (email or name), View, Parking spaces, Developer, Unit number, Floor, Year built | | Agent: "Matched to your agents" |

- Although Emirate is not marked *, every row needs an emirate: it is taken from the Emirate column, otherwise read from the City / Community / Building text (contains "Dubai", "Abu Dhabi" / "Al Ain", or "Sharjah", "Ajman", "Ras Al Khaimah", "Umm Al Quwain", "Fujairah"). If none is found the row is skipped.

### 2.2 How values are read (matching rules)

- **Purpose:** text containing rent / lease / let = Rent; sale / sell / buy / resale / off plan = Sale; otherwise matched to the Offering type options.
- **Property type:** any wording is mapped, e.g. Flat, Duplex, Loft or Studio = Apartment; Penthouse; Townhouse; Villa; Office; Retail / Shop = Retail shop; Showroom; Warehouse; Land / Plot = Plot; Building = Whole building. Otherwise it must match a Property type option (Master > Property Options) by value or label.
- **Numbers:** commas, spaces, "AED", "sqft", "sq.ft", "sqm" are ignored; "k" = thousand, "M" / "mn" / "million" = million (e.g. 1.25M = 1,250,000; 95k = 95,000). Size in sq.m is converted to sq.ft (x 10.7639) when Size (sq.ft) is empty.
- **Bedrooms:** "Studio" = 0. **Furnishing:** semi / part = Semi-furnished; unfurnished, No, N, 0, false = Unfurnished; furnished, Yes, Y, 1, true = Furnished; empty = Unfurnished.
- **Completion:** Off-plan, offplan, under construction = Off-plan; empty or unknown = Ready. **Rent frequency:** empty or unknown = Yearly (rentals only).
- **Category:** from the Category column; else Commercial for Office, Retail shop, Showroom, Warehouse, Whole building, Co-working, Plot; else the page it was uploaded from (Properties = Residential, Commercial = Commercial). A Commercial listing uploaded from the Properties page lands in the Commercial menu.
- **Community:** matched to a Property location option; if none matches, **a new Location option is added** to Master > Property Options with that name.
- **Amenities:** only names matching existing Amenity options are kept; others are dropped silently.
- **City:** from the City column; if empty: Dubai, Abu Dhabi or Sharjah depending on the emirate.
- **Permit type:** always set as for a new form listing with default permit type: Dubai = RERA (DLD), Abu Dhabi = ADREC, Northern Emirates = ADREC if the city / community contains "Al Ain", otherwise no permit needed. DTCM or None (DIFC/JAFZA) cannot be set by the import.
- **Permit number:** spaces removed, upper-cased; allowed: letters, numbers, - and /, 3-64 characters. Anything else is left out with a remark.
- **Permit expiry date:** any readable date, or an Excel date number; unreadable = left empty.
- **Map pin:** both latitude and longitude must be valid (not 0,0); otherwise both are left empty.
- **Year built** is kept only if 1800-2200.
- **Text tidy-up:** titles in ALL CAPITALS become Title Case (short codes like JVC, BR kept), emoji removed, repeated "!!!" reduced to one and trailing "!" / "-" / "*" / "|" trimmed; key features one per line (bullets / numbering removed, max 20); plain-text descriptions become paragraphs, and "- " lines become a bullet list; HTML descriptions keep only basic formatting.
- **Empty title:** written from the details, e.g. "2 Bed Apartment for Sale in Dubai Marina", "Studio Apartment for Rent in JVC", "Villa for Sale in Arabian Ranches" (no beds given).
- **Agent column (agency accounts):** matched to the agency's agents by email, or by exact name (case ignored). If not found: the uploading agency agent is assigned, or, when the agency itself uploaded, no agent and a remark. An independent agent's listings are always their own.

### 2.3 Duplicates

A row is a **Duplicate** (not added) when:
- the account already has a listing with the same permit number — remark "Already listed as RERA... (same permit).";
- the account already imported a listing with the same "Your reference" — remark "Already listed as RERA... (same reference "AG-1001").";
- the account has a listing with the same purpose, property type, location, bedrooms, size within 3% and price within 2% — remark "Looks like RERA... — same type, community, bedrooms, size and price.";
- an earlier row of the same file has the same permit, reference or listing — remark "Same permit as row 4 of this file." (also "Same reference ..." / "Same listing ...").

A permit number already used by **another account's** listing makes the row **Skipped**: "This permit number is already used by another listing on MW Realty — each advertising permit covers one listing."

Row numbers in remarks are spreadsheet row numbers (the heading is row 1).

### 2.4 Photos

- Photo URLs must start with http:// or https://. Up to 30 photos per listing are downloaded, converted to JPEG and watermarked like uploads (Listing Settings).
- A photo that cannot be downloaded is skipped; the listing is still created. The remark says "2 of 5 photos could not be downloaded" or "the photos could not be downloaded".
- No photo stored = "photos" in the list of what is still needed.

### 2.5 What makes a row Added / Needs details / Skipped

- **Skipped:** a required value is empty or unreadable. Remark lists every problem, e.g. "Price is empty; property type "Hut" isn't one of ours." Other wordings: "purpose (sale / rent) is empty", "purpose "X" isn't sale or rent", "price "abc" isn't a number" (also 0 or negative), "community / area is empty", "emirate unknown — add an Emirate or City column". Also: "Empty row.", "Plan listing limit reached.", and on an unexpected error "Could not be saved — please check this row and import it again."
- **Needs details:** the listing was created, but something is still missing. Remark: "<reference no> · still needed: photos, description, map pin (latitude / longitude), size, bedrooms, amenities, AR title, <permit items>". Bedrooms is not required for Commercial. "<CODE> title" appears for each other site language not filled (translation off or failed). Permit items come from the normal permit check: e.g. "DLD advertising permit number", "Permit expiry date", "A valid permit — the recorded one has expired", "Permit QR code (Madmoun)" / "Permit QR code (ADREC)".
- **Added:** nothing missing. Remark: "<reference no> · ready — validate the permit to publish".
- Extra notes are appended after "·", e.g. agent "x" not found — no agent assigned, permit "..." left out (letters, numbers, - and / only), photos not downloaded, "AI description not available right now".
- Every created listing is Inactive. It can go live only through the normal permit flow (Module 07 / 09).

### 2.6 Plan limits and credits

- Upload is refused if the plan has no listings left: "Your plan's listing limit is reached — upgrade your plan to import more listings." (no plan or an inactive plan counts as 0 left).
- The limit is checked again on every row. When it runs out mid-file, the remaining new rows are Skipped "Plan listing limit reached." and the result shows in red: "Your plan's listing limit was reached — upgrade your plan, then import the file again to add the rest (rows already added are recognised as duplicates)."
- Imported listings count against the plan like added ones (Module 07, 2.4). Credits are never spent by an import. An agency agent's import uses the agency's plan; the listings belong to the agency.

### 2.7 Background import, progress and limits

- Step 1 reads the file at once; step 2's "Import N listings" queues it. A background worker (started by the scheduler every minute) picks it up, so "Starting import..." / "Waiting for the import worker — N rows" can show for up to about a minute.
- While running: "Importing listings... N%", "X of Y rows checked", and live counts "added / duplicates / skipped" (here "added" includes Needs details). Progress updates every 5 rows; the page checks every 2 seconds. Rows with photos or AI copy take seconds each.
- Only one import per account at a time. Uploading another meanwhile: "<file name> is still importing — please wait for it to finish before uploading another file." While one runs, the Import page shows its progress instead of the upload step.
- If a running import shows no progress for 15 minutes, or the worker crashes, it becomes failed: "The import stopped unexpectedly. Listings saved before it stopped are kept — upload the file again; rows already imported are recognised as duplicates, not added twice."
- Leaving the page does not stop the import. The Properties list shows the running import (or one finished within the last day) as a banner; it can be closed with X once finished.

### 2.8 Result, status file and history

- Finished card: "Import finished — N rows", file name and duration ("45s" or "3 min 5s"), chips "N ready", "N need details" (only if any), "N duplicates", "N skipped"; line "N% of rows imported with nothing left to fill in · N descriptions written by AI · N translated. New listings stay off the website until their permit is validated."; buttons Download status file and Refresh listings.
- **Status file** `<original name>-import-status.xlsx`: every uploaded row as it was, plus two columns "Import Status" and "Import Remarks". Status cells are coloured: Added green, Needs details amber, Duplicate blue, Skipped red. Files are kept 30 days; after that: "This status file is no longer available (files are kept for 30 days)."
- **Previous imports** table: File (with uploader name, "Failed" or "Importing N%"), Date, Rows, Ready, Need details, Duplicates, Skipped, Automated (% for finished imports, "—" otherwise), button Status file. 10 per page, "Show older imports". Empty: "No imports yet — your first one will show here, with its status file." Uploads never started (stopped at step 2) are not listed and are deleted after a day.
- History is per account: an agency and its agents see the same list.

### 2.9 Notification

When an import ends, the account and the uploader (once if the same person) get a **bell notification only** (no email):
- "Listing import finished" — "<file>: N listings added (N need details), N duplicates, N skipped."
- "Listing import failed" — "<file> could not be imported: <reason>"

Clicking it opens Properties (or Commercial) with `?import=<id>` and shows that import's result card.

### 2.10 Write with AI (listing form)

- Button **Write with AI** sits above the language tabs in Property Details > Description. It always shows; it works on the **language tab currently selected**.
- Pop-up "Write with AI" — "Title, key features and description in <language>, from this listing's details".
- **What should it do?** Write new / Improve my description. Improve is disabled when that language's description is empty; when a description exists, the pop-up opens on Improve.
- **Tone:** Professional (default), Luxury, Friendly, Short & concise.
- **Anything else to mention? (optional)**, max 2,000 characters. Note under it: "The AI only uses the details filled in on this form and what you write here — it won't invent amenities, views or prices."
- Inputs sent: purpose, rent frequency, property type, category, completion, bedrooms, bathrooms, size, price, currency, location, emirate, furnishing, view, floor, parking, year built, developer, amenities, community / address / city (this language, else the first language), the notes plus the current key features. The form's current values are used, saved or not.
- The AI is told: use only these facts, no emoji, no ALL CAPS, no exclamation marks, price not in the title, title max 80 characters, 4-8 key features, 3-4 paragraphs plus optional bullet list, written in the chosen language. Output is tidied like imported text (2.2).
- **Preview** shows title, key features and description (right-to-left for Arabic). Tick boxes: Use the title (ticked only if the title field is empty), Use the key features (ticked only if empty), Use the description (always ticked). **Use this** (disabled if nothing ticked) fills the ticked fields of that language and closes the pop-up; auto-translate, if on, then treats them as typed. **Try again** writes a new version. **Cancel** / X changes nothing.
- Nothing is saved until Save Property / Update Property.
- Errors (red box in the pop-up): AI not set up — "AI writing isn't set up yet (CHATBOT_PROVIDER and its API key). You can still write the description by hand."; AI failed or bad reply — "The AI couldn't write this right now — please try again in a moment."; Improve with nothing to improve — "There's no description to improve yet — write a few lines first, or choose "Write new"." Limit: 20 requests per minute per user.
- The same AI is used by the import's "Write a description with AI for listings that have none": only for rows with an empty Description; it also fills an empty title and empty key features.

### 2.11 Other changes testers will notice

- **Web address (slug):** new listings get "<title or typed slug>-<reference no>", e.g. `spacious-2-bed-apartment-rera70718`. The form hint now reads "Web address: /property-details/<slug> — the reference no is added so it is always unique." An existing listing keeps its web address unless the slug field is changed. Two listings may now have the same title without a "slug taken" error. Slug max 200 characters.
- **Property Finder sync:** imported listings get the same slug format (title + reference no, previously title + 5 random characters). Property type "Studio" now maps to Apartment. Emirate detection is shared with the spreadsheet import. Photo download (30 max, watermark) and amenity / location matching behave as before.
- **Integrations hub:** new tile "Excel / CSV import" (agency / agent only) with status "Not used yet", "Last import <time> ago" or "Importing...", and footer "Import", opening `/crm/properties/import`.
- **Properties list toolbar:** the Search box moved down to the filter row; Add Property has a caret menu (not for Super Admin) with Add one by one, Import from Excel / CSV, Sync from Property Finder.

## 3. Who can do what

| Action | Super Admin | Agency | Agent |
| --- | --- | --- | --- |
| Caret menu "Import from Excel / CSV" on the list | No (hidden) | Yes | Yes |
| Excel / CSV import tile in Integrations | No | Yes (1) | Yes (1) |
| Open the Import page by URL | Page opens, upload refused (2) | Yes | Yes |
| Download template | Yes | Yes | Yes |
| Upload and import | No | Into own account | Independent: own account. Agency agent: into the agency's account (3) |
| See Previous imports / status files | No | All imports of the account | Agency agent: all of the agency's imports |
| AI descriptions / translate during import | — | If set up on the site | If set up on the site |
| Write with AI in the listing form | Yes | Yes | Yes |
| KYC not approved | — | Refused: "Your account is pending Super Admin approval." | Same |

(1) The Integrations hub itself is behind the plan features Facebook / Property Finder (Module on Integrations); the Import page is not. (2) "Sign in as the agency or agent to import their listings." (3) The uploading agency agent is assigned to rows whose Agent column is empty or not found; a matching Agent column assigns another agency agent.

## 4. Fields and validation

**File (step 1)**
- Types: .xlsx, .xls, .csv (also .tsv, .txt accepted). Other types: "Upload an Excel (.xlsx, .xls) or CSV file."
- Size: max 10 MB (standard size message).
- CSV: comma, semicolon or tab separated (detected from the first line); UTF-8, UTF-16 or Windows-1252.
- Unreadable file: "This file could not be read. Please upload a valid Excel (.xlsx, .xls) or CSV file."
- Heading row only, or empty: "The file needs a heading row and at least one listing below it."
- Max 2,000 listing rows: "This file has 2,500 listings — import at most 2,000 per file (split it into several files)." Empty rows at the end are ignored.
- Only the first sheet of an Excel file is read.
- Button "Next: match columns" stays disabled until a file is chosen; shows "Reading file..." while busy. Upload limited to 20 per minute.

**Mapping (step 2)**
- Every * field needs a column. Otherwise the drop-downs turn red and: "Choose the column for: Price (AED), Community / area."
- If the file and mapping no longer match: "The column mapping doesn't match this file — please map the columns again."
- Starting an import that was already started: "This file has already been imported — upload it again to import it once more."
- The AI and Translate boxes are ticked by default when available; otherwise disabled with "AI writing isn't set up on this site yet." / "Auto-translate isn't set up on this site yet — add the other languages on each listing."
- Button shows "Starting..." while busy.

**Row values (during import)** — see 2.2 and 2.5. Length limits: reference 100, title 255, view / developer / floor 255, unit number 100 characters (longer text is cut).

**Write with AI** — notes max 2,000 characters; language must be an active site language; the draft (Improve) max 20,000 characters.

## 5. Test scenarios

Priority: High = core flow / data loss / access; Medium; Low = cosmetic. Use an Agency test account with a plan that has free listings (Module 07), and a test spreadsheet built from the template. Confirm with the project lead whether AI and auto-translate are set up on the test server and whether the scheduler (queue worker) runs.

| ID | Scenario | Priority | Steps | Expected result |
| --- | --- | --- | --- | --- |
| PI-01 | Entry points and page | High | 1. As Agency open Properties; click the caret next to Add Property.<br>2. Choose Import from Excel / CSV.<br>3. Open Integrations and click the Excel / CSV import tile. | Menu shows Add one by one, Import from Excel / CSV, Sync from Property Finder. Page "Import listings" with Download template, three tiles (Excel / CSV "You're here"), steps 1-3, side card, Previous imports. Tile status "Not used yet" (first time), footer "Import", opens the same page. |
| PI-02 | Super Admin cannot import | High | 1. As Super Admin open Properties; check the Add button.<br>2. Type `/crm/properties/import`, choose a file, click Next: match columns. | No caret menu; no Excel / CSV tile in Integrations. Upload shows "Sign in as the agency or agent to import their listings." |
| PI-03 | Download template | Medium | 1. Click Download template; open the file. | `listing-import-template.xlsx` with bold headings in the order of 2.1 and two example rows. |
| PI-04 | Upload template, columns auto-matched | High | 1. Fill the template with 5 test rows; drag it onto the drop area (or click to browse).<br>2. Click Next: match columns. | Step 2: "<file> — 5 listings, N columns." Every field is matched to its column, sample values shown (max 3 per column). Button "Import 5 listings". |
| PI-05 | Bad files | Medium | 1. Upload a .pdf.<br>2. Upload a file over 10 MB.<br>3. Upload a .xlsx with headings only.<br>4. Upload a renamed .txt full of random bytes as .xlsx. | Red box with: "Upload an Excel (.xlsx, .xls) or CSV file."; size message; "The file needs a heading row and at least one listing below it."; "This file could not be read. Please upload a valid Excel (.xlsx, .xls) or CSV file." Stays on step 1. |
| PI-06 | Over 2,000 rows | Medium | 1. Upload a file with 2,001 listing rows. | "This file has 2,001 listings — import at most 2,000 per file (split it into several files)." |
| PI-07 | Own export with other headings; required column missing | High | 1. Upload a CSV with headings "Ref, Beds, BUA, Area, Trakheesi, Asking Price, Unit Type, For".<br>2. Set Price (AED) to "-- Not in my file —"; click Import.<br>3. Map it again; click Import. | Step 1: Ref, Beds, BUA, Area, Trakheesi, Asking Price, Unit Type, For pre-matched. Step 2: red drop-down and "Choose the column for: Price (AED)." Step 3: import starts. |
| PI-08 | Import runs and finishes | High | 1. Import 5 rows with photos.<br>2. Watch the progress.<br>3. When done, check the bell. | "Starting import..." then "Importing listings... N%", "X of 5 rows checked" and counts. Then "Import finished — 5 rows", duration, chips, % line, Download status file, Refresh listings. Bell "Listing import finished" with "<file>: N listings added (N need details), N duplicates, N skipped." Previous imports gets a row. |
| PI-09 | Status file | High | 1. Click Download status file; open it. | `<name>-import-status.xlsx`: all original columns and rows plus Import Status and Import Remarks; colours per status (2.8); remarks start with the new reference no (RERA...). |
| PI-10 | Row errors -> Skipped | High | 1. Prepare rows: empty purpose; purpose "Swap"; type "Hut"; price "abc"; price empty; community empty; community "Marina" with no city / emirate.<br>2. Import; open the status file. | Each row Skipped with the matching remark from 2.5, several problems joined by ";". Last row: "Emirate unknown — add an Emirate or City column." Other rows are not affected. |
| PI-11 | Values tidied | Medium | 1. Rows with: price "1.25M"; price "95k"; Size (sq.m) 100 only; Bedrooms "Studio"; title "STUNNING 2BR IN JVC!!!"; empty title; Furnishing "Yes"; description with "- " lines.<br>2. Import; open the listings. | Price 1,250,000 / 95,000; size 1,076 sq.ft; Studio (0 beds); title "Stunning 2BR in JVC"; auto title like "2 Bed Apartment for Sale in Dubai Marina"; Furnished; description shows a bullet list. |
| PI-12 | Re-import the same file | High | 1. Import the same file a second time. | All previously added rows are Duplicate ("Already listed as ... (same permit)." / "(same reference ...)" / "Looks like ..."). No new listings; plan usage unchanged. |
| PI-13 | Duplicates in file / other account's permit | Medium | 1. File with two rows of the same reference (and two with the same unit and price).<br>2. A row using a permit number that another test account already uses. | Second row: Duplicate "Same reference as row N of this file." (or "Same listing ..."). Other account's permit: Skipped "This permit number is already used by another listing on MW Realty — each advertising permit covers one listing." |
| PI-14 | Photos | Medium | 1. Row with 3 valid image URLs and 1 broken URL.<br>2. Row with no URLs.<br>3. Open the listings' Images tab. | Row 1: 3 photos (JPEG, watermark if set); remark "1 of 4 photos could not be downloaded". Row 2: Needs details "still needed: photos, ...". |
| PI-15 | Plan limit | High | 1. Use an account with 0 listings left; upload.<br>2. Use an account with 2 left; import 5 new rows. | 1: "Your plan's listing limit is reached — upgrade your plan to import more listings." 2: 2 rows created; others Skipped "Plan listing limit reached."; red line "Your plan's listing limit was reached — upgrade your plan, then import the file again to add the rest ...". No credits charged. |
| PI-16 | One import at a time | Medium | 1. Start a large import (100+ rows with photos).<br>2. In another tab open Import listings; try to upload another file. | The page shows the running import's progress instead of the upload step. A forced upload gets "<file> is still importing — please wait for it to finish before uploading another file." |
| PI-17 | Agents | Medium | 1. As Agency: Agent column with an agent's email, an agent's name, and "nobody@x.com".<br>2. As an agency agent: import with the Agent column empty. | 1: listings assigned to the matched agents; unknown one: remark 'agent "nobody@x.com" not found — no agent assigned'. 2: listings belong to the agency, assigned to the uploading agent; the agency sees the import in its history. |
| PI-18 | AI descriptions and translation | Medium | 1. (AI and auto-translate set up) Rows without description and title; keep both boxes ticked; import.<br>2. Repeat with both boxes unticked. | 1: descriptions, titles and key features written; card "N descriptions written by AI · N translated"; other languages filled. 2: "still needed: description, <CODE> title"; no AI count. If AI fails for a row: remark "AI description not available right now". |
| PI-19 | Imported listings stay offline | High | 1. Import a Dubai row with permit number and expiry, and a Sharjah row (not Al Ain) with all details and photos.<br>2. Open both in Properties and on the website. | Both Inactive, not on the website. Dubai row: Needs details with "Permit QR code (Madmoun)"; permit type RERA. Sharjah row: Added "ready — validate the permit to publish" (no permit needed). Permit flow then follows Module 07 / 09. |
| PI-20 | Banner, notification link, history | Medium | 1. Start an import, go to the Properties list.<br>2. After it ends, close the banner with X.<br>3. Click the bell notification.<br>4. Click Status file in Previous imports. | 1: progress banner above the listings. 2: banner gone. 3: Properties opens with `?import=<id>` and that import's result card. 4: the same status file downloads. Refresh listings reloads the list with the new listings. |
| PI-21 | Commercial page and Category | Medium | 1. From Commercial > caret > Import, upload rows with Office and with Apartment (Category empty).<br>2. From Properties, import a row with Category "Commercial". | Office rows in Commercial; Apartment row from the Commercial page also in Commercial (page default). Category "Commercial" row from Properties lands in Commercial. Back link reads "Back to Commercial". |
| PI-22 | Property Finder sync regression | Medium | 1. (PF test connection) Run a sync with new listings including a "Studio" type.<br>2. Open the new listings' web address. | Listings imported as before (Module on Property Finder). Slug ends with "-<reference no>" (e.g. -rera70718). Studio listed as Apartment. Photos up to 30, watermarked. |
| AC-01 | Write with AI pop-up | High | 1. Open Add Property > Property Details; fill purpose, type, beds, size, price, location.<br>2. Click Write with AI. | Pop-up "Write with AI" with "in English" (current tab), Write new selected, Improve disabled (no description), Tone Professional, notes box and its help text. Buttons Cancel and Write. |
| AC-02 | Write new and use it | High | 1. Click Write.<br>2. Check the preview; click Use this.<br>3. Leave the form without saving; reopen.<br>4. Write again, Use this, then Save Property. | "Writing..." then a preview (title, key features, paragraphs). Fields filled only after Use this. Not saved when leaving; saved after Save Property. No emoji / ALL CAPS / "!" / price in title; no amenities or views that are not on the form. |
| AC-03 | Improve my description | Medium | 1. Type a short rough description; click Write with AI.<br>2. Click Write. | Opens on Improve my description. Preview keeps the facts of the draft, better written. |
| AC-04 | Use tick boxes | Medium | 1. With a title and key features already typed, open Write with AI and Write.<br>2. Untick everything.<br>3. Tick only Use the description; Use this. | Use the title and Use the key features start unticked, Use the description ticked. With all unticked, Use this is disabled. Only the description changes. |
| AC-05 | Tones, notes, Try again | Medium | 1. Write with Luxury, then Short & concise.<br>2. Add notes "Vacant on transfer, upgraded kitchen"; click Try again. | Text style changes with tone; the notes appear in the copy; Try again replaces the preview. |
| AC-06 | Other language | Low | 1. Switch the form to the Arabic tab (if active); Write with AI; Write; Use this. | Pop-up says "in Arabic"; preview right-to-left and in Arabic; only the Arabic fields are filled. |
| AC-07 | AI errors | Medium | 1. (Server without AI set up) Click Write.<br>2. Click Write 21 times within a minute (or ask a developer to simulate a provider failure). | 1: "AI writing isn't set up yet (CHATBOT_PROVIDER and its API key). You can still write the description by hand." 2: a "too many requests" style error after 20; on failure "The AI couldn't write this right now — please try again in a moment." The form is unchanged. |
| AC-08 | Web address hint (same release) | Low | 1. In Add Property type a title; read the Slug hint.<br>2. Save; edit another field and Update.<br>3. Change the slug and Update. | Hint "Web address: /property-details/<slug>-<ref> — the reference no is added so it is always unique." Step 2: web address unchanged. Step 3: new slug with "-<reference no>" added once. |

## 6. Expected results checklist

- Only Agency and Agent accounts can upload and import; Super Admin is refused; the caret menu and Integrations tile are hidden from Super Admin.
- Template downloads; own files with common headings are matched automatically; required fields are enforced before the import starts.
- File type, size, empty-file and 2,000-row limits show the exact messages in section 4.
- The import runs in the background, shows live progress, survives leaving the page, and ends with a result card, a history row and a bell notification.
- The status file has every original row plus Import Status / Import Remarks with correct colours and remarks.
- Duplicates (permit, reference, same unit and price, earlier row) are never added twice; another account's permit is refused.
- Plan limits stop new listings; no credits are spent.
- All imported listings are Inactive and follow the normal permit rules.
- Write with AI fills only the ticked fields of the selected language, only after Use this, and saves only with the form.
- AI and translation errors are shown clearly and never break the form or the import.

## 7. Known behaviour (not bugs)

- Dubai and Abu Dhabi rows almost always end as **Needs details**: the file has no column for the permit QR code, so "Permit QR code (Madmoun)" / "(ADREC)" is always listed. Only rows needing no permit (Northern Emirates outside Al Ain) can be "Added" with full details.
- When other site languages are active and translation is off (or fails), every row lists "<CODE> title" as still needed, so it is Needs details.
- "N added" while running includes Needs details rows; the finished card splits them into "ready" and "need details". "Automated" % = ready rows / all rows.
- Communities not yet in Master > Property Options are added there automatically. Unknown amenities are dropped silently.
- Import cannot set DTCM or None (DIFC/JAFZA) permit types; change them in the form afterwards.
- Empty rows in the middle of a file are counted and reported as Skipped "Empty row."; the step 2 count ("N listings") excludes them, so the final row total can be higher.
- More than 30 photo URLs: only the first 30 are downloaded, and the remark still says "N of M photos could not be downloaded" for the rest.
- Imported listings also go through the new Listing Review screening (duplicate / risk checks, separate module), which can hold a listing for Super Admin review.
- The queued step can take up to about a minute before progress starts (worker starts every minute).
- Write with AI button shows even when AI is not set up; the message appears only after clicking Write.
- An agency agent sees and can download the status files of every import of their agency, including other agents' imports.

**Test data cautions:** use only test accounts, fake permit numbers and test image URLs; imports add real listings to the plan count and add new Location options to Master data on a shared server, so use clearly named test communities and tell the team. Do not import other brokers' real permits. AI requests may cost money with the provider — avoid long repeated runs.

## 8. Reporting a bug

Include: account type and email (Agency / Agent / Super Admin), page (Properties or Commercial), the uploaded file (or a copy with private data masked) and the status file, import ID (from the notification link `?import=`), the column mapping used, AI / translate options, plan and listings left, rows affected (spreadsheet row numbers), status and remark expected vs actual, exact message, time and a screenshot. For Write with AI: language tab, mode, tone, notes, the listing details filled in, and the AI text received.

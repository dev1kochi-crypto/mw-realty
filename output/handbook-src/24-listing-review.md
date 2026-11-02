# Listing Review

Super Admin's queue of listings the system flagged automatically on save (the same unit listed by another agency, or risk signals), to approve or reject one by one.

**Where:** CRM sidebar > Approvals > Listing Review (/crm/listing-review and /crm/listing-review/:id). The sidebar item has a badge with the number of open cases (both tabs together). **Who:** Super Admin only (open the CRM from Admin). Agencies and agents never see this menu; the pages refuse them (the server answers 403). **Locks:** none for Super Admin.

## 1. What this module does

Every listing (Properties and Commercial) is screened automatically each time it is saved. Nothing is blocked at submission: the listing is saved as usual, and if the screening finds something it opens a review case for Super Admin.

There are two kinds of case. **Same unit, another agency** (duplicate): another agency already lists the same unit (same location and unit number for the same purpose, or the same photos). The listing that was added first stays on the website; every newer copy is held off the website until Super Admin decides. **Risk signals**: one listing with something that needs a human check (price far off similar listings, details that don't add up, advertiser authority not confirmed, ad differs from its permit record, resubmitted after a rejection). Some risk signals leave the listing live with a flag; others hold it off the website.

For each waiting listing Super Admin clicks **Approve** (it may go on the website) or **Reject** (it stays off the website with a reason the agency sees; once the agency fixes it and saves, it comes back here). The screening only flags; it never confirms or rules out ownership.

Listing Review is separate from Listing Permits (Module 09), but the two screens link to each other: a listing goes live only when its permit is approved on Listing Permits **and** it is not held by Listing Review.

| List (/crm/listing-review) | Case page (/crm/listing-review/:id) |
|---|---|
| Title "Listing Review" with intro text; five measure tiles (Duplicate rate, Flagged · last 30 days, Permit verification, Time saved · last 30 days, Avg. time to resolve); three tab cards with counts (Same unit, another agency / Risk signals / Resolved); a hint line per tab; search box "Title, ref, agency or agent"; rows with Listings (up to 3 thumbnails, title, count and agencies or ref and agency), Why flagged (type chip, signals, status chips), Price (range and "% apart", or the single price), Flagged / Resolved date, and a Review (open) or Open (resolved) button. 20 per page, "Showing a–b of N". | "Back to listing review"; header "Same unit listed by N agencies" or "Listing to check", "Flagged <date-time> · <time ago>", chip "N waiting for you" or the resolution; a plain-words hint (open) or a resolved banner; price agree / differ banner; "What was flagged" (risk signals); "Compared with" table (price risks only); "Side by side" / "The listing" comparison table with photos, Price, facts, Permit, Advertiser KYC, Listed, Why matched (duplicates) and Decision (Approve / Reject per listing); Approve / Reject pop-up with a note or reason. |

## 2. How it works (business rules)

### 2.1 What triggers screening

- **Every save of a listing** (add or update) runs the screening after the permit status is worked out. Sold / rented listings are never screened.
- If the screening itself fails, the error is logged and the listing is still saved ("Screening must never break saving a listing").
- **Photos**: when the gallery changed since the last check, a background job (queue) fingerprints the photos and then screens the listing again. This needs the queue worker running; without it, photo matches are not found (unit-number matches still work). The job tries twice, up to 180 seconds each.
- **Existing listings** (saved before this module existed) are only screened when a developer runs the command in section 2.8, or when the listing is next saved.
- Each save is recorded for the "Time saved" measure (the photo re-check and the command are not counted).

### 2.2 Same unit, another agency (duplicate)

- A listing matches another **available** (not sold) listing from a **different** account (a different agency / agent, or MW Realty vs an agency) when:
  - **Same unit**: same location, same purpose (sale / rent) and the same unit number (case and spaces at the ends ignored); or
  - **Same photos**: at least 2 of its photos match photos of the other listing (or its only photo, when it has just one fingerprinted photo).
- Photo matching: up to the first 15 gallery photos per listing are fingerprinted; the outer 10% of each edge is cropped first (where watermarks and agency banners usually sit); two photos count as the same when at most 8 of 64 fingerprint bits differ. Almost blank / flat images are ignored.
- The "Why matched" line explains each match, e.g. "<ref>: same unit number (1203) in the same location, both for sale" and/or "<ref>: 3 matching photos".
- **The oldest listing stays on the website** ("Listed first · stays live"); every newer listing in the case that is not decided yet is **held** off the website ("Waiting for your decision").
- If a new listing matches listings in two separate open cases, the cases are merged into one case for the unit.
- Two listings Super Admin approved side by side are **never flagged against each other again**.
- The same agency listing the same unit twice is not a Listing Review case: the listing form refuses it on save with "Unit <unit> in this location is already listed for <sale/rent> as <ref>. Edit that listing instead of adding it again." (existing behaviour).

### 2.3 Risk signals

| Signal (label on screen) | When it is raised | Effect |
|---|---|---|
| Price far below similar listings / Price far above similar listings | Price per sq.ft is below 60% or above 200% of the median of similar listings. Similar = live, available listings with the same purpose, property type and location, with a size and price. Needs at least 5 similar listings, otherwise no price check. | Stays live with a flag |
| Listing details don't add up | The title says a different number of bedrooms ("The title says 2 bedrooms, the listing says 3."), says studio but has bedrooms, names a type that doesn't fit the property type ("The title says villa, the property type is Apartment."), names another community ("The title says Dubai Marina, the location is JVC." — title must say "in" or "at" the place), or the size is below the minimum for the type (villa 1,000; townhouse 900; penthouse 800; apartment / condo 250; warehouse 500; office 100 sq.ft). | Stays live with a flag |
| Advertiser authority not confirmed | The owner account isn't approved (KYC); a Dubai listing whose agency has no ORN, or whose individual owner has no BRN; the assigned agent has no BRN (Dubai); the assigned agent isn't an active member of the agency. MW Realty's own listings are skipped. | Stays live with a flag |
| Ad differs from the permit record | Only when the permit was verified online and the DLD / ADREC record has the value: price differs by more than 1%, bedrooms differ, or size differs by more than 3%. | **Held** off the website |
| Resubmitted after rejection | The agency changed a rejected listing and saved it again (not raised if the listing is already waiting in a same-unit case). The detail repeats "You rejected it because: <reason>". | **Held** off the website |

- A listing has at most one open risk case; on each save its signals are updated.
- **Fixed by the agency**: if a save leaves no signal at all, the open risk case closes by itself with resolution "Fixed by the agency" and note "Resolved by itself — the listing was changed and nothing is flagged any more."
- After Super Admin approves a risk case, the same signals with the same price, size and bedrooms are not flagged again. A different problem, or a change of price / size / bedrooms that still triggers a signal, opens a new case.
- On a price-risk case the page shows "Compared with": the similar listings (cheapest per sq.ft first) with a median divider, this listing highlighted, and "Normal range: <60% of median> – <200% of median> / sq.ft".

### 2.4 Case and listing statuses

- **Case**: open or resolved. Resolution: **Approved**, **Rejected** (at least one listing in the case was rejected), or **Fixed by the agency** (auto-cleared).
- A duplicate case closes when no held listing is left undecided (the first listing does not need a decision). A risk case closes when its listing is decided.
- **Website** chip per listing on the case page: "Live", "Waiting for review — off the website", "Rejected — off the website", "Offline · <permit status>", or "Sold" / "Rented".
- Tabs: **Same unit, another agency** and **Risk signals** show open cases; **Resolved** shows closed cases (newest resolved first). Open tabs are sorted by last activity, newest first. The list always opens on Same unit, another agency.

### 2.5 Approve

- Available on each listing marked "Waiting for your decision" (button **Approve** in the Decision row). Pop-up "Approve <ref>": "It goes on the website[, next to the other agency's listing of this unit]. The agency gets a notification." If the permit is not approved yet, a warning: "Its permit isn't approved yet (<status>) — it goes live only after it's approved on Listing Permits too." Optional note (max 1,000 characters).
- Result toast: "Approved — the listing can be on the website." or, when the permit is not approved on Listing Permits, "Approved here — it goes on the website once its permit is approved on Listing Permits (<status>)."
- The hold is lifted. The listing is switched back on only if it was Active before it was held **and** it can go live (permit approved, not expired). An Inactive listing stays Inactive.
- In a duplicate case, the approved listing is remembered as distinct from the other (not rejected) listings, so they are not matched again.

### 2.6 Reject

- Available on each waiting listing, and also on the first listing of a duplicate case ("Already on the website — nothing to decide. Reject it only if this listing is the wrong one.").
- Pop-up "Reject <ref>": "It stays off the website" (held) or "It goes off the website" (live), "The agency sees this reason; once they fix the listing and click Update, it comes back here for another check." **Reason (sent to the agency)** is required (max 1,000 characters); the Reject button stays disabled until something is typed.
- Result toast: "Rejected — the listing stays off the website and the agency was told why."
- The listing is switched off and stays off. The owner can't switch it on: "MW Realty rejected this listing. Open it, fix what was flagged and click Update to send it for review again." The edit form shows a red banner "MW Realty rejected this listing: <reason> Fix it and click Update — it goes back to MW Realty for review."
- When the agency / agent saves the listing again, the rejection is cleared and the listing is screened again: it returns to Listing Review as "Resubmitted after rejection" (held) or, if it still matches another agency's unit, into a same-unit case.
- A decided listing cannot be decided twice: "This listing has already been decided."

### 2.7 Notifications

All are **bell notifications only** (no email), sent to the listing's owner and its assigned agent; clicking opens the listing's edit page.

| Event | Title | Message |
|---|---|---|
| Listing goes on hold | Listing waiting for review | "<title> is waiting for MW Realty's review (the unit is already listed by another agency, or a detail needs checking). It goes on the website once approved." |
| Rejected | Listing rejected | "<title>: <reason, up to 160 characters> Fix it and click Update to send it for review again." |
| Hold lifted (approved or fixed) | Listing approved | "MW Realty reviewed <title> — it can be on the website now." |

- The "held" and "approved" bells are sent only when the hold actually changes. A listing that was flagged but stayed live (price, details, advertiser) gets no bell when the case opens or when it is approved.
- Super Admin gets no bell for new cases; the sidebar badge shows open cases.
- While held, the owner's edit form shows "Not on the website yet: this unit is already listed by another agency, or a detail (price, permit) needs checking. MW Realty is reviewing it — you'll get a notification." and switching it on is refused with "This listing is being reviewed by MW Realty — it goes live once it is approved."

### 2.8 Jobs and commands (developer)

- **Queued job** ScreenListingPhotos: photo fingerprinting after a gallery change (needs the queue worker).
- **Command** `php artisan properties:screen` — screens all available listings, oldest first. Without options it is a **dry run**: prints "Dry run — nothing is changed. ..." and a table of what would be flagged plus up to 10 examples. `--apply` opens the cases (flagged listings go off the website until reviewed). `--photos` also queues photo fingerprinting for every gallery (needs the queue worker).
- **No scheduled job** belongs to this module.
- Setting `LISTING_REVIEW_MINUTES_PER_CHECK` (default 10) sets the minutes per manual check used by "Time saved".

### 2.9 Measures (top of the list)

| Tile | What it counts |
|---|---|
| Duplicate rate | Listings in an open same-unit case (including the first listing) out of all available listings, %, with the number of listings. |
| Flagged · last 30 days | Cases (both types, any status) created in the last 30 days. |
| Permit verification | Available listings whose permit is approved on Listing Permits, %. |
| Time saved · last 30 days | (saves screened − saves left waiting) × minutes per check, in hours; sub-line "N checked automatically · N needed no one · N min each". |
| Avg. time to resolve | Average hours from case opened to resolved over 90 days (auto-cleared cases excluded), "—" if none; sub-line "N reviewed · N fixed by the agency (90 days)". |

### 2.10 Links with Listing Permits

- **Listing Permits list**: rows in a case show a red chip "Same unit as another agency" or "Risk flag" (plus " · off the website" when held) that opens the case; a rejected listing shows "Rejected on Listing Review".
- **Listing Permits review page**: a red card "Rejected on Listing Review" with the reason and "Off the website until the agency fixes it and saves it — it then goes back to Listing Review."; one card per open case ("Same unit listed by another agency" with the other listings, price, "listed first" and Live / Off; or "Flagged on Listing Review" with the signals), buttons **Compare side by side**, and, when this listing is waiting and not sold, **Approve listing** / **Reject** (same rules as 2.5 / 2.6; pop-up titles "Approve listing" / "Reject listing").
- **Approve & publish on Listing Permits** also approves every Listing Review case the listing is waiting in (note "Approved on Listing Permits."). The card adds "It also approves its Listing Review check above." and the toast ends " Its Listing Review check was approved too."
- Approve & publish is refused for a rejected listing: "This listing was rejected on Listing Review — the agency has to fix it and save it first."
- On the case page each listing shows "Listing Permits: <status>" (link to its permit page); the list shows "N also on Listing Permits" when listings in the case are not yet approved there.
- A held or rejected listing can't go live and is not shown on the website, whatever its permit status.

## 3. Who can do what

| Action | Super Admin | Agency | Agent |
|---|---|---|---|
| See Listing Review menu and badge | Yes | No | No |
| Open /crm/listing-review or a case by URL | Yes | Refused (403, error shown) | Refused (403, error shown) |
| Approve / Reject a listing in a case | Yes | Refused (403) | Refused (403) |
| Approve / Reject from the Listing Permits page | Yes | No | No |
| See why own listing is held / rejected | — | Edit form banner, bell, refusal when switching on | Same, for listings they own or are assigned to (bell) |
| Resubmit a rejected listing | Not by Super Admin's own save (see section 7) | Fix and click Update | Fix and click Update |
| Run the screening command | Developer only | No | No |

## 4. Fields and validation

- **Search** (list): max 100 characters (longer input is cut). Searches reference no, RERA ID, title, address, community and city (all active languages) and the owner account's name / company name, across every listing in the case. No match: "No cases match your search." The × button clears it.
- **Note** on Approve: optional, max 1,000 characters (the box stops at 1,000). Saved on the listing's decision and shown in the Decision row.
- **Reason** on Reject: required, max 1,000 characters. The Reject button is disabled while empty; if the server still receives an empty reason it answers with the standard "The note field is required." message.
- Server refusals: "This listing has already been decided." (listing already approved / rejected, or case closed); a case id that doesn't exist shows an error with the link "Back to listing review"; a failed list load shows the error with "Try again".
- Tab values other than duplicate / risk / resolved open the Same unit, another agency tab.
- There are no other inputs; the listing data itself is edited in the listing form (Module 07).

## 5. Test scenarios

Priority: High core flow / data loss / access · Medium · Low cosmetic. Use test accounts for two different agencies (Agency A, Agency B). Photo scenarios need the queue worker running.

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| LR-01 | Access — Super Admin | High | 1. Sign in to Admin, open the CRM.<br>2. Sidebar > Approvals > Listing Review. | List opens with intro text, five measure tiles, three tab cards with counts, Same unit, another agency selected. Sidebar badge = open cases of both tabs. |
| LR-02 | Access — Agency / Agent | High | 1. Sign in as Agency; check the sidebar.<br>2. Type /crm/listing-review and /crm/listing-review/<id>.<br>3. Repeat as Agent. | No Listing Review menu. Pages show an error instead of data; no Approve / Reject possible. |
| LR-03 | Demo data and tabs | High | 1. Prepare open and resolved cases with test listings (LR-04 onwards).<br>2. Open Listing Review; click each tab card.<br>3. Compare counts with the rows. | URL gets ?tab=duplicate / risk / resolved; hint text and empty text change; Resolved shows approved and rejected examples; counts match rows. |
| LR-04 | Same unit by another agency | High | 1. Agency A saves a sale listing in location X, unit number 1203.<br>2. Agency B saves a sale listing in the same location with unit " 1203 " (or "1203").<br>3. Check Listing Review, both listings, and Agency B's bell. | New case under Same unit, another agency. A's listing "Listed first · stays live"; B's "Waiting for your decision", website "Waiting for review — off the website", switched off. Why matched: "<ref>: same unit number (1203) in the same location, both for sale". Agency B gets "Listing waiting for review"; A gets nothing. |
| LR-05 | Not a duplicate | Medium | 1. Agency B saves the same unit in the same location but for rent (A's is for sale).<br>2. Agency B saves the same unit number in a different location. | No case is opened; both listings behave as normal. |
| LR-06 | Same agency, same unit | Medium | 1. Agency A adds a second listing with the same location, purpose and unit number as its own. | Save refused on Unit number: "Unit 1203 in this location is already listed for sale as <ref>. Edit that listing instead of adding it again." No Listing Review case. |
| LR-07 | Approve the newer copy | High | 1. Open the LR-04 case.<br>2. Click Approve on B's listing; type a note; Approve.<br>3. Check website, B's bell, Resolved tab.<br>4. Re-save both listings. | Toast "Approved — the listing can be on the website." (or "Approved here — it goes on the website once its permit is approved on Listing Permits (...)."). Case moves to Resolved "Approved by <admin> on ...". B's listing back on (if it was Active and permit approved); B gets "Listing approved". Re-saving does not open a new case for this pair. |
| LR-08 | Reject the newer copy | High | 1. Create another same-unit case.<br>2. Click Reject; leave reason empty; then type a reason; Reject.<br>3. As Agency B, open the listing and try to switch it on. | Reject button disabled while reason empty. Toast "Rejected — the listing stays off the website and the agency was told why." Case Resolved with "Rejected". B gets "Listing rejected" with the reason; edit form red banner "MW Realty rejected this listing: ..."; switching on refused: "MW Realty rejected this listing. Open it, fix what was flagged and click Update to send it for review again." |
| LR-09 | Resubmit after rejection | High | 1. As Agency B, edit the rejected listing (e.g. remove / change the unit number) and click Update.<br>2. Check Listing Review. | Listing back in Listing Review: under Risk signals with "Resubmitted after rejection" and the old reason ("You rejected it because: ..."), off the website; or, if it still matches A's unit, in a same-unit case again. |
| LR-10 | Same photos | Medium | 1. Queue worker running.<br>2. Agency B creates a listing (different unit / no unit number) using 2 or more of the same photos as Agency A's listing (may be resized or watermarked).<br>3. Wait for the background job; reload Listing Review. | Same-unit case appears with Why matched "<ref>: N matching photos". Only one shared photo (when the listing has several) does not open a case. |
| LR-11 | Three listings of one unit | Medium | 1. Agencies A, B and C list the same unit (C last).<br>2. Open the case; narrow the browser. | Header "Same unit listed by 3 agencies"; A listed first, B and C waiting; chip "2 waiting for you". When columns don't fit: "3 listings — slide to see them all; the row names stay in place." with ‹ › buttons. Rows that are the same / different are coloured per the legend. |
| LR-12 | Price far off the area | High | 1. Location with at least 5 live listings of the same type and purpose.<br>2. Agency saves a listing there at under 60% (or over 200%) of their typical price per sq.ft.<br>3. Open the case under Risk signals. | Signal "Price far below similar listings" with detail "AED x / sq.ft — N% below the median of AED y / sq.ft across N similar listings in this area." Listing stays live (row chip "Still live"); "Compared with" table with median line and this listing highlighted; Normal range shown. No bell to the agency. |
| LR-13 | Price check needs 5 comparables | Low | 1. Save a very cheap listing in a location with fewer than 5 similar live listings. | No price signal. |
| LR-14 | Details don't add up | Medium | 1. Agency saves "Spacious 2 BR apartment in Dubai Marina" with 3 bedrooms, type Villa, location JVC, size 400 sq.ft. | Risk case "Listing details don't add up" listing each problem (bedrooms, type, location, size under 1000 for a villa). Listing stays live. |
| LR-15 | Advertiser authority | Medium | 1. Dubai listing by an agency with no ORN, or with an assigned agent who has no BRN / isn't a member of the agency, or an owner whose KYC isn't approved. | Risk case "Advertiser authority not confirmed" with the matching sentences; listing stays live; case page "Advertiser KYC" shows Approved / Not approved. MW Realty's own listings never get this signal. |
| LR-16 | Ad differs from permit | High | 1. (DLD / ADREC validation connected) Agency validates a test permit, then changes the price by more than 1% (or bedrooms, or size by more than 3%) and saves. | Risk case "Ad differs from the permit record" e.g. "Price 1,500,000 on the permit vs 1,700,000 in the ad."; listing held off the website; agency gets "Listing waiting for review". |
| LR-17 | Fixed by the agency | Medium | 1. On an open risk case (e.g. LR-14), the agency corrects the listing so nothing is flagged and saves.<br>2. Check Resolved. | Case leaves Risk signals and appears under Resolved as "Fixed by the agency" with note "Resolved by itself — the listing was changed and nothing is flagged any more." If it was held, it comes back on and the agency gets "Listing approved". |
| LR-18 | Approved risk not re-flagged | Medium | 1. Approve a price-risk case.<br>2. Agency re-saves without changing price, size or bedrooms.<br>3. Agency changes the price (still far off) and saves. | Step 2: no new case. Step 3: a new risk case opens. |
| LR-19 | Approve a live flagged listing | Medium | 1. Approve the listing in an open price / details / advertiser case (listing was live). | Toast shown; case Resolved "Approved"; listing stays live. Note the bell: per the code no "Listing approved" bell is sent because the listing was never held (see section 7). |
| LR-20 | Reject the first listing | Medium | 1. In a same-unit case, click Reject on the "Listed first · stays live" listing; give a reason. | It goes off the website and the owner gets "Listing rejected". The newer copies keep waiting for a decision; the case stays open until they are decided. |
| LR-21 | Already decided | Low | 1. Open the same case in two tabs.<br>2. Approve a listing in tab 1; then Approve / Reject the same listing in tab 2. | Tab 2 shows error toast "This listing has already been decided." |
| LR-22 | Listing Permits link-up | High | 1. Open Listing Permits; find a row with "Same unit as another agency · off the website" or "Risk flag".<br>2. Open it; use Compare side by side.<br>3. On a waiting listing with a pending permit, click Approve & publish. | Chip and card link to the case. Approve & publish toast ends " Its Listing Review check was approved too."; the case shows the listing Approved with note "Approved on Listing Permits."; listing live. |
| LR-23 | Rejected blocks Approve & publish | High | 1. Reject a listing on Listing Review that is pending on Listing Permits.<br>2. On Listing Permits click Approve & publish. | Listing Permits shows "Rejected on Listing Review" (row chip and card with the reason). Approve refused: "This listing was rejected on Listing Review — the agency has to fix it and save it first." |
| LR-24 | Approve with permit not approved | Medium | 1. In a case, open Approve on a listing whose chip shows "Listing Permits: <status>" (not approved). | Pop-up warns "Its permit isn't approved yet (...) — it goes live only after it's approved on Listing Permits too." Toast "Approved here — it goes on the website once its permit is approved on Listing Permits (...)." Listing stays off until approved on Listing Permits. |
| LR-25 | Search, pagination, command dry run | Low | 1. Search by ref, title, community and agency name; then nonsense; clear with ×.<br>2. On a tab with more than 20 cases go to page 2; refresh.<br>3. Developer runs `php artisan properties:screen` (no options). | Matching cases; "No cases match your search."; page kept in the URL. Command prints "Dry run — nothing is changed..." with a summary table and changes nothing (no new cases). |

## 6. Expected results checklist

- Only Super Admin sees and can open Listing Review and approve / reject; others get an error.
- A unit listed by a second agency opens one same-unit case; the first listing stays live, newer ones are off the website until decided.
- Same-agency duplicates are refused on save, not sent to Listing Review.
- Price, details and advertiser signals leave the listing live with a flag; permit mismatch and resubmission hold it off the website.
- Approve lifts the hold (live only if it was Active and its permit is approved); Reject keeps it off with the reason shown to the agency.
- A rejected listing comes back to Listing Review when the agency fixes and saves it.
- Approved pairs and approved risk signals are not flagged again unless something relevant changes.
- Bells "Listing waiting for review", "Listing rejected", "Listing approved" reach owner and assigned agent.
- Listing Permits shows the review chips / cards, and its Approve & publish also approves waiting review cases; it refuses rejected listings.
- Tabs, counts, measures, search and pagination return the right cases.

## 7. Known behaviour (not bugs)

- By design: nothing is blocked when the listing is saved; the screening only flags for a human and never confirms ownership.
- The first listing of a unit is the one created earliest, not the cheapest or the one with the best permit.
- Photo matching runs in the background and needs the queue worker; right after a save a photo duplicate may not appear yet.
- Existing listings are not screened until they are saved again or a developer runs `properties:screen --apply`.
- The price check is skipped when fewer than 5 similar live listings exist in that location, or the listing has no price, size or location.
- "Ad differs from the permit record" is only possible for permits verified online with DLD / ADREC.
- After rejecting the first listing of a unit, the newer copies are not promoted to "listed first" automatically; they still wait for your decision.
- Approving does not switch on a listing the owner had Inactive.
- The approve pop-up says "The agency gets a notification", but the code sends the "Listing approved" bell only when the listing comes off hold. Approving a flagged listing that stayed live sends no bell. Report it if the team expects a bell here.
- The search placeholder says "agency or agent", but the search matches the listing owner's account name / company name, not the name of an agent assigned to an agency's listing.
- A rejected listing returns to review only when the agency / agent saves it (the code checks the partner login); it is unclear from the code whether a save by Super Admin also clears the rejection, so test and report what you see.
- Bells only — Listing Review sends no emails, and Super Admin gets no bell for new cases (use the sidebar badge).
- "Flagged · last 30 days" counts cases of any status opened in the last 30 days; merged cases are not counted separately.

Test data cautions: use only test agencies and test listings. `properties:screen --apply` can take many listings off the website; never run it on production without agreement. Do not reject real client listings on a shared server.

## 8. Reporting a bug

Include: Super Admin account, case ID and tab, listing IDs / references and their agencies, case type and signal labels, which listing was listed first, website chip and Listing Permits status before and after, the action taken and the note / reason, whether the queue worker was running (for photo cases), steps, expected vs actual, exact message or toast, time, and a screenshot. Mask real permit numbers, prices of client listings and contact details.

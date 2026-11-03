# Property Insights

Shows how every listing of the account performs on the website in one table: impressions, listing clicks, lead clicks, leads and quality score, for a chosen period, so listings nobody sees stand out.

**Where:** CRM sidebar > Insights > Property Insights (`/crm/property-insights`). A row (or Details) opens the listing's Listing Performance panel, the same panel as on the Properties page. **Who:** every account (Agent, Agency) for its own listings, and an agency agent also for the agency listings assigned to them; Super Admin sees every listing. **Locks:** the account must be approved (KYC); the plan must include **Property insights** (plan key `property_insights`, the same feature that unlocks Listing Performance on Properties).

## 1. What this module does

Until now the website numbers of a listing were only visible one listing at a time (Properties > a card's chart icon). Property Insights puts all listings side by side for a period of 7, 30 or 90 days:

- **impressions**: how often the listing was shown on the website (search results, home page sections);
- **clicks**: how often its details page was opened;
- **lead clicks**: Call, WhatsApp and Email button clicks on the listing;
- **leads**: leads created for the listing in the period;
- **quality**: the listing's quality score (0–100), the same ring as on property cards.

### 1.1 The Insights menu group

The CRM sidebar now has its own group **Insights**, in this order: Property Insights, Agent Insights (Agency and Super Admin only, see Module 26), Lead Insights (moved here from the Leads group; the page itself is unchanged, see Module 03) and Reports. Each item keeps its own plan lock.

| Screen | What the tester sees |
| --- | --- |
| Property Insights | Title and intro; six cards (Listings · N live, Impressions, Listing clicks, Lead clicks, Leads, No impressions); chart "Impressions & clicks · period" (two lines, day by day); filter bar (search "Title, reference or address", listings All / Residential / Commercial, period, sort, Clear); table with columns Listing (photo, title, reference, Active / Inactive, Premium, owner for Super Admin), Agent, Impressions, Clicks, Lead clicks, Leads, Quality, Details; pager (20 per page). |
| Listing Performance panel | Opens from the right: funnel, last 30 days chart, Overview (quality score and what to improve, people, tiles) and Leads tabs. Same as Properties. |
| Locked | Plan without the feature: padlock on the menu item and the card "Unlock property insights" with View Plans. |

## 2. How it works (business rules)

### 2.1 Which listings

- The same listings the account sees under Properties and Commercial: its own, plus (agency agent) the agency listings assigned to it. Super Admin: all listings.
- Only listings still on the market. Sold and rented listings are not shown (they are in Sold Listings).
- Active and Inactive listings are both shown; the status is under the title.

### 2.2 The numbers

| Measure | Rule |
| --- | --- |
| Listings | All listings in the list (after the Residential / Commercial filter, before search); small text: how many are Active. |
| Impressions, Listing clicks, Lead clicks | Sum of the daily counters of these listings for the days in the period (today included). |
| Leads | Leads created in the period for these listings, whoever they are assigned to. |
| No impressions | Listings with zero impressions in the period. In the table the impressions figure is red when it is 0. |
| Quality | The listing's current quality score (not limited to the period). Ring colour: green 80+, orange 50–79, red under 50. |
| Chart | One point per day of the period: total impressions and total clicks of the listings in the list. |

Clicks can be higher than impressions: a listing opened from a direct link, a shared link or the Compare page gets a click without an impression.

### 2.3 Filters, sort and paging

- Period: Last 7, 30 or 90 days (default 30). It changes the cards, the chart and every row.
- Listings: All (default), Residential, Commercial.
- Search: title, address, community or city (any active language), reference or permit number; Super Admin also the owner's name or company.
- Sort: **Most impressions** (default), Most clicks, Most leads, **No impressions first**, Newest first. Ties: newest listing first.
- Clear resets search, listings, period and sort.
- 20 listings per page. The cards and the chart count all listings in the list, not only the current page.

## 3. Who can do what

| Role | Menu | Page | Notes |
| --- | --- | --- | --- |
| Super Admin | Yes (never plan-locked) | Every listing; owner name under the title | |
| Agency / Agent, plan with the feature | Yes | Own listings (+ assigned agency listings for an agency agent) | |
| Plan without the feature | Yes, with padlock | "Unlock property insights" card | API answers 403 with `plan_locked` |
| KYC not approved | Locked "Unlocks after KYC approval" | Refused | |

## 4. Fields and validation

| Input | Rule |
| --- | --- |
| Period (`days`) | 7, 30 or 90; any other value is treated as 30. |
| Sort (`sort`) | impressions, clicks, leads, no_views, newest; anything else is treated as impressions. |
| Listings (`segment`) | residential, commercial or empty (all); anything else is treated as all. |
| Search (`search`) | Up to 100 characters, partial match. |
| Page | Whole number; a page past the end shows an empty table. |

## 5. Test scenarios

Prepare: an agency on a plan with Property insights and at least five listings (one Inactive, one Commercial, one never viewed); an agency on a plan without it; an agency agent with one assigned agency listing. Use test accounts only.

| ID | Scenario | Priority | Steps | Expected result |
| --- | --- | --- | --- | --- |
| PI-01 | Menu group | High | 1. Sign in as the agency.<br>2. Look at the sidebar. | Group Insights with Property Insights, Agent Insights, Lead Insights, Reports. Lead Insights is no longer under Leads. |
| PI-02 | Access with the feature | High | 1. Sidebar > Insights > Property Insights. | Cards, chart, filter bar and the listings table open. |
| PI-03 | Plan lock | High | 1. Sign in as the agency without the feature.<br>2. Open Property Insights. | Padlock on the menu; "Unlock property insights" card with View Plans. |
| PI-04 | Impressions and clicks | High | 1. In a private window, search the website so listing X shows in the results; open its details page.<br>2. Reload Property Insights, period 7 days. | X: Impressions and Clicks go up; the cards and today's point in the chart go up too. |
| PI-05 | Lead click and lead | High | 1. On X's details page click WhatsApp, then send an enquiry.<br>2. Reload Property Insights. | X: Lead clicks +1, Leads +1; cards match. |
| PI-06 | No impressions | Medium | 1. Sort No impressions first. | Listings with 0 impressions on top, their figure in red; the No impressions card equals the number of such rows across all pages. |
| PI-07 | Period | Medium | 1. Switch 7 / 30 / 90 days. | Cards, chart (7, 30 or 90 points) and rows change; Quality does not. |
| PI-08 | Residential / Commercial | Medium | 1. Choose Commercial, then Residential. | Only that kind of listing; Listings card changes. |
| PI-09 | Search | Medium | 1. Search part of a reference, then part of a title.<br>2. Clear. | Matching listings only; Clear restores all defaults. |
| PI-10 | Sold listing hidden | Medium | 1. Mark a listing sold.<br>2. Reload Property Insights. | The listing is gone from the table and the Listings card drops by 1. |
| PI-11 | Details panel | High | 1. Click a row (or Details). | Listing Performance panel opens for that listing; its numbers are all-time and last 30 days, so they can differ from a 7- or 90-day row. Close returns to the table. |
| PI-12 | Agency agent | Medium | 1. Sign in as the agency agent.<br>2. Open Property Insights. | Own listings plus the assigned agency listing; no other agency listings. |
| PI-13 | Privacy | High | 1. As agency A, search the reference of agency B's listing. | Not found in the table. |
| PI-14 | Super Admin | Medium | 1. Admin > CRM > Property Insights. | Every account's listings with the owner under the title; no padlock. |
| PI-15 | Help guide | Low | 1. Click "!" in the top bar. | Help guide "Property Insights" opens. |
| PI-16 | Mobile | Low | 1. Open at phone width. | Cards in two columns; chart fits; table scrolls sideways; panel covers the screen. |

## 6. Expected results checklist

- Insights group: Property Insights, Agent Insights, Lead Insights, Reports; each with its own padlock.
- Only listings on the market that the account can see; Super Admin sees all.
- Cards and chart count all listings in the list; table pages 20 at a time.
- Period changes everything except Quality.
- A row opens the same Listing Performance panel as on Properties.

## 7. Known behaviour (not bugs)

- Counters are per day; today's numbers grow during the day.
- Clicks can exceed impressions (direct or shared links, Compare).
- Only Active listings collect impressions, so an Inactive listing always shows 0 and counts under No impressions.
- Leads counts every lead of the listing in the period, including leads assigned to another agent of the agency.
- The panel shows all-time and last-30-day figures, not the period chosen on the page.

## 8. Reporting a bug

Include: account (name and plan, or Super Admin), period, filters and sort, the listing reference and its row values, the card values, what you did on the website (search, open, button) and when, expected vs actual, and a screenshot.

# Business Rules & Expected Results

Where to find the business rules and the expected application behaviour for every module, and how they are written.

**Scope:** All modules of the website, Admin and CRM. The rules and expected results themselves live in the module test guides (00–26); this page is the index to them. About 900 test scenarios exist across the guides.

## 1. How each module guide is organised

Every module guide (01–26) follows the same eight sections, so a tester always knows where to look.

| Section | What it holds | Use it for |
| --- | --- | --- |
| 1. What this module does | Purpose, screens and their URLs | Understanding the module before testing |
| 2. How it works (business rules) | Statuses, calculations, limits, automatic jobs, notifications | The business rules to verify |
| 3. Who can do what | Action × role table (Super Admin, Agency, Agent) | Role and permission tests |
| 4. Fields and validation | Inputs, limits, exact validation messages | Negative and boundary tests |
| 5. Test scenarios | ID, scenario, priority, steps, expected result | Test case execution |
| 6. Expected results checklist | Short list of what must hold true | Sign-off and regression |
| 7. Known behaviour (not bugs) | Behaviour by design, test data cautions | Avoiding false bug reports |
| 8. Reporting a bug | What to include in a bug report | Bug reports |

Priority in section 5: **High** = core flow, data loss or access; **Medium** = important but with a workaround; **Low** = cosmetic.

## 2. Index by module

| No. | Module | Scenario IDs | Scenarios | Key business rules (section 2 of the guide) |
| --- | --- | --- | --- | --- |
| 00 | Project overview | — | — | User types, website pages, how leads reach agencies, listing visibility rules |
| 01 | Dashboard | DB-xx | 32 | What each KPI card, chart and alert counts, for which account; won / lost / win rate; trend badges |
| 02 | Leads | LD-xx | 58 | Lead sources, duplicates, assignment and round robin, notifications, status / stage / closing, import |
| 03 | Lead Insights | LI-xx | 26 | How a lead gets website activity, ranking, the Insights tab on a lead |
| 04 | Website Leads | WL-xx | 41 | How a visitor becomes a website lead, automatic routing from the Lead Pool, manual transfer |
| 05 | Master Data | MD-xx | 31 | Lead stages, tags and sources (shared and own), unique names, deleting, Property Options |
| 06 | Integrations | IN-xx | 38 | Facebook Lead Ads, Property Finder import with Super Admin review, hub status chips |
| 07 | Properties | PR-xx | 52 | Permit status, which permit is needed, Active switch and visibility, plan limits and credits, quality score |
| 08 | Commercial | CM-xx | 26 | Same rules as Properties for commercial types; separate list and website page |
| 09 | Listing Permits | LP-xx | 28 | Permit statuses, approval modes A/B, take down, expiry job |
| 10 | Premium | PR-xx (Premium) | 35 | Booking, scheduling, editing and stopping Premium placement, paid from the plan or credits |
| 11 | Advertisements | AD-xx | 35 | Banner ad booking, review by MW Realty, placements and pricing, impressions / clicks report |
| 12 | Sold Listings | SL-xx | 27 | Marking sold / rented with proof documents, the register, putting a listing back on the market |
| 13 | Marketing Properties | MK-xx | 19 | Super Admin hand-picked list behind the home page section and /marketing-properties |
| 14 | Agents | AG-xx | 45 | How agents join, membership statuses, agent slots, lead eligibility, suspend / remove / leave |
| 15 | Nearby Places | NP-xx | 27 | Landmarks listings are tagged with and how they show on the property page |
| 16 | Listing Settings | LS-xx | 27 | Photo watermark: which one applies, position, size, opacity, plan lock |
| 17 | Reports | RP-xx | 42 | Leads, Properties, Sales and Agents reports: date range, scope, won / lost, export, plan lock |
| 18 | Help Desk | — | — | In-app quick guides for each screen; short guide only, detailed scenarios to be added |
| 19 | Contact Us (support tickets) | CU-xx | 31 | Support tickets: statuses, transitions, badge and sorting, notifications, Admin side |
| 20 | Credits | CR-xx | 40 | Credit balance, history, calculator, buying by card, spending on listings, Premium and ads |
| 21 | My Profile | PF-xx | 38 | Account details, public page, KYC documents and approval, login email change, document expiry emails |
| 22 | Security | SEC-xx | 40 | Two-factor authentication, new-device email check, recovery codes, recognised devices |
| 23 | Plans | PL-xx | 38 | Payment modes, upgrade / downgrade / cancel, coupons, limits and what a plan unlocks, automatic behaviour |
| 24 | Listing Review | LR-xx | 25 | Duplicate and risk screening, approve / reject, holds |
| 25 | Property Import & AI Copy | PI-xx, AC-xx | 30 | Excel import rules, duplicates, Write with AI |
| 26 | Compare, Insights & AI Chat | CP-, IN-, CU-, CH-, OT-xx | 35 | Compare limits and fees, market insight formulas, currency sync, chat languages |

> Scenario counts for guides 01–23 are taken from the published PDFs and may be off by one or two where a table spans pages.

## 3. Cross-module business rules

These rules affect several modules. Test them end to end, not only inside one module.

| Rule | Modules involved | Expected result |
| --- | --- | --- |
| A listing is on the website only when it is Active, its permit status allows it (Module 09) and it is not held by Listing Review (Module 24) | 07, 08, 09, 24, website | Hidden listings do not appear in search, Premium, Marketing or compare |
| Every website enquiry, viewing request, property request and chat contact becomes a lead for the listing's owner or the profile owner | Website, 02, 04, 26 | Lead appears in the right account with its source |
| Plan features lock modules and limits (listings, Premium quota, integrations) | 06, 07, 10, 11, 23, 25 | Locked modules show the plan card; limits refuse with a message |
| Credits pay for Premium and ads when the plan quota is used up | 10, 11, 20 | Balance falls by the stated amount; history records it |
| Agency agents work with the agency's plan and credits; independent agents have their own | 14, 20, 23 | Agent sees only own and assigned records |
| Super Admin sees all accounts and the admin-only modules (04, 09, 13, 24, options, ad review) | All CRM modules | Agencies and agents are refused (403) on those pages |
| Prices are stored in AED; the currency switcher only converts the display | Website, 26 | Converted prices follow Admin rates |
| Times and daily jobs use UAE time (Asia/Dubai) | 02, 09, 11, 23 | Reminders and expiry run on the UAE date |

## 4. Writing new expected results

When a module changes, update its guide in the same structure: add the rule to section 2, the role change to section 3, the message to section 4, and at least one scenario to section 5 with a quoted expected result (exact toast, message or status label).

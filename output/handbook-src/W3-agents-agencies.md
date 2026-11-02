# Website: Agents & Agencies

The agent and agency directories, their public profiles, and the Custom Property Request form that sends a lead to the agent or agency.

**Where:** /agents, /agent-details/{slug}, /agencies, /agency-details/{slug}. **Who:** Everyone; no sign-in needed. **Locks:** Only approved partner accounts are listed.

## 1. What this page covers

The directories list approved agents and agencies with a search box. Each profile shows the partner's details and live listings, and has a Custom Property Request ("find me a property") form that creates a lead in that agent's or agency's CRM.

| Page / menu | URL | What it does |
|---|---|---|
| Agents | /agents, /agent-details/{slug} | Agent directory with search; agent profile with listings and the **Custom Property Request** form. |
| Agencies | /agencies, /agency-details/{slug} | Agency directory with search; agency profile with listings, agents and the **Custom Property Request** form. |

## 2. How it works (business rules)

### 2.1 Agents (/agents, /agent-details/{slug})

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

### 2.2 Agencies (/agencies, /agency-details/{slug})

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

## 3. Who can do what

| Action | Visitor | Customer | Agency / Agent |
|---|---|---|---|
| Custom Property Request on an agent / agency profile | Yes | Yes | Yes (as a visitor) |
| Receive the resulting leads | No | No | Yes: the listing's or profile's agency / agent in the CRM |

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
| Custom Property Request (agent / agency) | First Name *, Last Name, Email *, Phone, Property Category (Apartment, Villa, Townhouse, Penthouse, Commercial), Specification, Price Range, Area (sqft), Preferred Location, Additional Details, Move-in Timeline (Immediate, 1–3, 3–6, 6–12 Months, Flexible), Furnishing Status (Furnished, Unfurnished, Semi-Furnished, No Preference) | Missing first name: "First Name is required." Server: category, area and timeline up to 100; details up to 2,000; others up to 255. Button "Submit Request" ("Submitting…"). |

**Rate limits (per IP address):**
- property enquiry, viewing, downloads and agent / agency requests: 20 per hour together (lead-capture);
- contact, home and calculator forms, newsletter, career applications and forgot / reset password: 20 per hour each (form-submit);
- sign-up: 10 per hour;
- email code verify / resend: 8 per minute and 30 per day.
A blocked request shows the server's "Too Many Attempts." text or the generic error.

## 5. Test scenarios

Priority: **High** core flow / data loss / access · **Medium** · **Low** cosmetic. Use fake names and example.com emails.

| ID | Scenario | Priority | Steps | Expected result |
|---|---|---|---|---|
| AG-01 | Agents directory search | Medium | 1. Open /agents; search by part of an agent's name, a preferred area, an agency name.<br>2. Search for "zzz". | Only approved, active agents, A–Z. Each search narrows the cards (case-insensitive). "zzz": "No agents match your search.". Grid / list toggle switches layouts. |
| AG-02 | Agent profile and listings | High | 1. Open an agent profile.<br>2. Check chips, contact buttons, share buttons, About Me / My Listing Properties tabs.<br>3. Click **View All**. | Rent / Sell counts match their live listings; "Listings by {name} (N)" shows up to 10 newest. Instagram share copies the link and shows "Link copied". View All opens /properties?agent={slug} with chip "Agent: {name} ×" and heading "Properties by {name}"; × clears it. |
| AG-03 | Agent Custom Property Request | High | 1. Click **Custom Property Request**; submit empty.<br>2. Fill First Name "Test", Email request.agent@example.com, Category Villa, Price Range "AED 2M - 3M", Timeline 1–3 Months; Submit Request.<br>3. Check the CRM. | 1: "First Name is required." 2: drawer closes; /thank-you "Your property request has been received." / "Your agent is already on it…". 3: independent agent: lead in their CRM; agency agent: lead in the agency's CRM with that agent preferred. Source "agent-profile-request"; message lists the answers. |
| AC-01 | Agencies directory search | Medium | 1. Open /agencies; search by name and by a word from an office address.<br>2. Compare a card's "Properties : N" with the profile counts. | Only approved, active agencies, A–Z. Search narrows the list; no match: "No agencies match your search.". Note any difference between the card count and the profile's total (section 7). |
| AC-02 | Agency profile | Medium | 1. Open an agency profile.<br>2. Click both **View All** buttons. | Logo, "Since {year}", contact icons, office address, counts (agents, total, for sale, for rent), bio, map, listings (10 newest), up to 6 agents. View All listings opens /properties?agency={slug}; View All agents opens /agents?agency={slug} with the Agency box filled and only that agency's agents. |
| AC-03 | Agency Custom Property Request | High | 1. Send the request form from an agency profile with valid fake details. | /thank-you shows "The agency is already on it and will share properties that match your brief." The lead is in the agency's CRM with source "agency-profile-request". |

## 6. Expected results checklist

- Agent and agency directories search correctly; profile request forms reach the right CRM.

## 7. Known behaviour (not bugs)

- "Serves in Dubai" is shown for every agent, and the map on agency profiles always shows Dubai, whatever the agent's areas or agency's address (fixed in code).
- The "Properties : N" count on /agencies cards counts all the agency's listings, including offline ones, while the profile page counts only live listings, so the numbers can differ (per code; looks like a bug).
- An agent or agency profile can open for an approved account that has been deactivated, but its Custom Property Request then fails with the generic error (the request needs an active account).
- The Custom Property Request is no longer on /properties; it is on agent and agency profiles only (removed from the listing page in September 2026).
- Agents and agencies load in one go with no paging; search runs in the browser.
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

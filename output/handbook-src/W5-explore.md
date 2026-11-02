# Website: Explore & Market Insights

Market Insights reports and the Explore menu: About, Contact, Blog and Careers, plus legal pages, the thank-you page, landing pages and the 404 page.

**Where:** /market-insights, /market-insights/{slug}, Explore menu: /about, /contact, /blogs, /blog-details/{slug}, /careers, /careers/{slug}; legal pages /terms-and-conditions, /privacy-policy, /security-policy, /cookie-settings; /thank-you; landing pages /{slug}; any unknown URL (404). **Who:** Everyone. **Locks:** None.

## 1. What this page covers

These pages show content managed in Admin. The Contact form goes to Admin > Enquiries with an email to the admin; career applications go to Admin > Careers > Candidates.

| Page / menu | URL | What it does |
|---|---|---|
| Market Insights | /market-insights, /market-insights/{slug} | Research reports: featured report, topic and market filters, report pages. |
| Explore > About Us | /about | About content managed in Admin. |
| Explore > Contact Us | /contact | Contact form and office details. |
| Explore > Blog | /blogs, /blog-details/{slug} | Blog list with categories; article pages. |
| Explore > Careers | /careers, /careers/{slug} | Job openings with filters; job page; application form with CV upload. |
| Legal pages | /terms-and-conditions, /privacy-policy, /security-policy, /cookie-settings | Admin-managed text pages. |
| Thank-you page | /thank-you | Shown after the Contact, home, calculator, property-enquiry and agent/agency request forms. |
| Landing pages | /{slug} | Admin landing pages (Blog design or custom HTML). |
| 404 page | any unknown URL | "Page Not Found" with a search box and popular links. |

## 2. How it works (business rules)

### 2.1 Market Insights (/market-insights, /market-insights/{slug})

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

### 2.2 Explore menu, legal pages, thank-you, landing pages and 404

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

## 3. Who can do what

| Action | Visitor | Customer | Agency / Agent |
|---|---|---|---|
| Send property enquiry, Book a Viewing, download brochure / floor plan | Yes (contact details required) | Yes (linked to the account when signed in) | Yes (as a visitor) |
| Contact form, home "Get in touch", calculator enquiries | Yes | Yes | Yes |
| Unlock Eligibility / Refinance results | Yes, after sending contact details | Same | Same |

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
| Career application | Full Name *, Email Address *, Phone *, Country, Experience (Less than 1 year … 10+ years), Designation, Additional information, CV *, Privacy * | CV: PDF, DOC or DOCX, up to 5 MB, drag and drop or browse. Messages: "Please attach your CV.", "Your CV must be a PDF or Word document.", "Your CV must be 5 MB or smaller.", "Please accept the privacy policy to continue." Phone up to 50, Additional information up to 2,000. |

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
| MI-01 | Market Insights list | Medium | 1. Open /market-insights.<br>2. Click a topic chip, choose a Market, go to page 2.<br>3. Copy the URL to a new tab; then pick filters with no posts. | Featured Report on top (first page only, not repeated below). Filters and page go into the URL and survive the new tab; 9 per page. No posts: "No insights found" / "Nothing matches these filters yet — try another topic or market." with **Clear filters**. |
| MI-02 | Insight report page | Medium | 1. Open a report.<br>2. Click Copy link, the topic chip, **Download full report**, **Contact our team**.<br>3. Open /market-insights/unknown. | "Copied!" appears; topic chip opens the filtered list; Download opens the file/link (hidden when none); Contact opens /contact. Related Insights shows up to 3. Unknown: "Insight Not Found". |
| EX-01 | About Us | Low | 1. Open /about. | Sections show the Admin content; empty sections hidden; no placeholder text errors. |
| EX-02 | Contact form | High | 1. Send with no message.<br>2. Send Name "Contact Tester", Email contact.tester@example.com, Phone 501234567, a message. | 1: "Please enter a message." 2: /thank-you "Thank you, Contact!" and "Your message is on its way to our team.". Admin > Enquiries entry with source "Contact Page"; admin email; the visitor appears in CRM Website Leads (Module 04). Office details, map and social links come from Admin. |
| EX-03 | Blog list and article | Medium | 1. Open /blogs; click a category pill; page through.<br>2. Open an article; use Previous / Next post.<br>3. Open /blog-details/unknown. | 12 per page; category filtering also filters the paging. Article shows cover, category, author, date, reading time; first/last articles show "Earliest article" / "Latest article". Unknown slug shows the not-found text. |
| EX-04 | Careers list | Medium | 1. Open /careers; choose a Department, then a Location.<br>2. Open a job. | Stats "Open Positions" and "Teams Hiring" are shown; filters apply on change and go into the URL; job cards show location / type / base. The job page shows the details and the application form. |
| EX-05 | Career application | High | 1. On a job page submit with no CV.<br>2. Attach a .png, then a 6 MB PDF.<br>3. Attach a small PDF, leave privacy unticked; submit; then tick and submit. | 1: "Please attach your CV." 2: "Your CV must be a PDF or Word document." / "Your CV must be 5 MB or smaller." 3: "Please accept the privacy policy to continue."; then "Thanks for applying — our team will review your application and get back to you." and the form clears. Admin > Careers > Candidates lists it under the job title (General Application from /careers#apply); admin email sent. |
| EX-06 | Legal pages, landing pages and 404 | Medium | 1. Open each legal page and /cookie-settings.<br>2. Open an active landing page slug and submit its form.<br>3. Open /this-does-not-exist and use its search box. | Legal pages show Admin text (cookie settings is text only). The landing page renders; its form entry appears in Admin > Landing Pages > Enquiries. The 404 page shows "Page Not Found", the requested path, popular links, and search opens /properties?location=…; DevTools shows HTTP 404 on a direct load. |
| EX-07 | SEO and tracking visible to a tester | Low | 1. Check tab titles on 5 pages.<br>2. Open /sitemap.xml, /robots.txt, /llms.txt.<br>3. In DevTools open Application > Cookies and the Network tab while browsing. | Titles follow section 2.2 (property pages: the listing name followed by "MW Realty"). The three files load and reflect the last Admin > SEO generation. Cookie mw_vid exists (HttpOnly, about 2 years). POST /track/page fires about 1 s after each page change, with /time beacons on leaving; no visible change on screen. |

## 6. Expected results checklist

- Contact, home, calculator and career forms land in the right Admin list with an admin email.

## 7. Known behaviour (not bugs)

- The share icons on blog articles are placeholders and do not share.
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

# Website: Calculators

The mortgage, eligibility, rent-vs-buy and refinance calculators: inputs, formulas, worked examples, unlock popups and enquiry forms.

**Where:** Calculators menu: /mortgage-calculator, /eligibility-calculator, /rent-vs-buy-calculator, /refinance-calculator. **Who:** Everyone; no sign-in needed. **Locks:** Eligibility and Refinance results unlock after the visitor enters contact details.

## 1. What this page covers

Each calculator runs in the browser and is meant to give the same figures as Property Finder's calculators for the same inputs. Copy, FAQs and enquiry-form text come from Admin > Calculators. Enquiries go to Admin > Enquiries with the figures in AED.

| Page / menu | URL | What it does |
|---|---|---|
| Calculators menu | /mortgage-calculator, /eligibility-calculator, /rent-vs-buy-calculator, /refinance-calculator | The four calculators, each with FAQs and an enquiry form. |

## 2. How it works (business rules)

### 2.1 Calculators

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

## 3. Who can do what

| Action | Visitor | Customer | Agency / Agent |
|---|---|---|---|
| Contact form, home "Get in touch", calculator enquiries | Yes | Yes | Yes |
| Unlock Eligibility / Refinance results | Yes, after sending contact details | Same | Same |
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
| Calculator enquiry / unlock popup | Name *, Email *, Phone, Message (enquiry only) | Message up to 2,000. Up to 20 calculation lines of up to 150 characters each. Buttons "Get a mortgage quote" / "Continue" ("Sending…"). |
| Refinance steps | every field except tenure months | Empty: "This field cannot be left empty." Lender: "Please select your current lender." Original loan below AED 100,000: "Minimum mortgage amount should be AED 100,000." Valuation below 300,000: "Property value must be at least AED 300,000." (shown while typing). Term: "Please enter the full loan term." Start date: "Mortgage start date cannot be in the future." / "… cannot be before 1950." / "With this start date and term, the loan is already paid off." Rate (0–30): "Enter your interest rate (e.g. 5.25)." Employment: "Please choose your employment status." Income 10,000 or less: "Total monthly income must be more than AED 10,000." Tenure: "Please enter how long you have been in your job." Date of birth: future / before 1950 / "Minimum age should be 18." / "Maximum age should be 64." Balance above the original loan: "The balance cannot be more than the original loan." Amounts are typed in the selected currency (up to 12 digits); minimums are converted for display. |

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
| CA-01 | Mortgage defaults (example 1) | High | 1. Open /mortgage-calculator; leave defaults.<br>2. Expand **Total purchase costs**. | Monthly payment AED 4,936 "at 3.75% over 25 years"; Total loan repayment AED 1,480,698; Total interest AED 520,698; fees 48,580 / 4,200 / 2,410 / 25,200 / 10,080 / 3,150 = 93,620; upfront AED 333,620. Same inputs on Property Finder's calculator give the same figures. |
| CA-02 | Mortgage residency, high value, limits | High | 1. Choose UAE National, price 2,000,000.<br>2. Choose Resident, type price 6,000,000.<br>3. Type down payment below the minimum; type rate 15; type price "1.5M"; open /mortgage-calculator?price=900000. | 1: down 15%, rate 4.24%, monthly AED 9,200, upfront AED 452,040. 2: down jumps to 30% with "(homes above AED 5M)", monthly AED 21,594. 3: values snap to the limits (minimum %, 10%); "1.5M" is read as 1,500,000; the URL price seeds the field. **Browse properties in budget** opens /properties?listing_type=sale&max_price={price}. |
| CA-03 | Eligibility lock and unlock | High | 1. Open /eligibility-calculator in a fresh tab.<br>2. Click **Unlock your results**; send with a bad email; then valid fake details.<br>3. Change income; click **Check eligibility**. Open a new tab. | 1: figures blurred, "AED 0,000,000". 2: "Please enter a valid email address."; then the results show and an Enquiry "Eligibility Calculator" with the figures is in Admin. 3: "You have changed your details — check again…", Check eligibility updates without the form. A new tab is locked again. |
| CA-04 | Eligibility maths | High | 1. Defaults (30,000, no debts, Resident, first home).<br>2. Liabilities 2,000, card limits 50,000.<br>3. Income 10,000, liabilities 5,000. | 1: price AED 3,646,929, loan AED 2,917,543, monthly AED 15,000, down payment (20%) AED 729,386. 2: price AED 2,552,850, loan AED 2,042,280. 3: "Your monthly liabilities already use 50% or more of your income…" with zero figures. First home "No" (case 1): price AED 4,488,528, down (35%). |
| CA-05 | Rent vs Buy | High | 1. Open /rent-vs-buy-calculator with defaults.<br>2. Set rent 60,000, loan period 5.<br>3. Open Assumptions; switch to Variable; set yearly growth values. | 1: "Buying is cheaper" by AED 2,561,576; "Buying pays off after" 2 years; monthly Rent 11,667 vs Buying 5,415; breakdown net costs 3,569,486 (rent) and 1,007,910 (buy). 2: Buying cheaper by AED 7,251, pays off after 5 years. 3: figures update; labels switch to "Lost if buying / Saved if renting" when renting wins; charts and View as table match. |
| CA-06 | Refinance validation | Medium | 1. Open /refinance-calculator; press Next with nothing filled.<br>2. Enter original loan 50,000, valuation 200,000, a future start date, rate 40.<br>3. On step 3 enter DOB making age 70 and a balance above the original loan. | Messages from section 4 appear under each field ("This field cannot be left empty.", "Please select your current lender.", "Minimum mortgage amount should be AED 100,000.", "Property value must be at least AED 300,000." while typing, "Mortgage start date cannot be in the future.", "Enter your interest rate (e.g. 5.25).", "Maximum age should be 64.", "The balance cannot be more than the original loan."). The step does not advance. |
| CA-07 | Refinance results (example 1) | High | 1. On 2 Nov 2026 enter example 1 from 2.8; on step 3 note the pre-filled balance, overwrite with 1,000,000, equity 0.<br>2. **See my savings**; fill the contact popup.<br>3. Repeat example 2. | Pre-filled balance 1,045,255 with the "Estimated from your loan details…" hint. Results: 5.43% → 3.75%, AED 6,855 → AED 5,141, saving AED 1,714/month, 20,565 first year, 61,696 over 3 years, cost 18,720, term left 19 years 11 months. Example 2: age note and "Refinancing would not lower your payments". An Enquiry "Refinance Calculator" is in Admin. |
| CA-08 | Calculator enquiry form | High | 1. Under any calculator fill Name, Email calc.tester@example.com, a message; **Get a mortgage quote**.<br>2. Check Admin > Enquiries and the admin inbox. | /thank-you (contact wording). Enquiry with page source "{Calculator} Calculator" and the message followed by "Label: value" lines in AED (e.g. "Monthly payment: AED 4,936"), even when the screen was in USD. Admin email sent. |
| CA-09 | Admin copy, FAQs and menu | Medium | 1. In Admin > Calculators change a page heading, add 2 FAQs, hide one calculator from the menu.<br>2. Reload the website; switch to Arabic. | Heading and FAQs show (FAQs open one at a time). The hidden calculator is gone from the header and the page tabs but its URL still opens. Arabic shows the Arabic copy when entered. |

## 6. Expected results checklist

- Calculators reproduce the worked examples in section 2.1 and match Property Finder for the same inputs; enquiries carry the figures in AED.

## 7. Known behaviour (not bugs)

- Eligibility and Refinance unlocks last for the browser tab session only; a new tab asks for contact details again and creates another enquiry.
- Refinance figures change slightly from day to day because the months left are counted from today.
- The Eligibility lead summary stores residency as a code (e.g. "non-resident") instead of the label.
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

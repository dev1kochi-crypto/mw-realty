# Agent Insights

Shows an agency how well each of its agents handles the leads and listings the agency assigns to them, with an Agent score out of 100 and the exact items that need work.

**Where:** CRM sidebar > Insights > Agent Insights (`/crm/agent-insights`); one agent: click a row or Details (`/crm/agent-insights/<agent id>`); also the button "Agent insights" on an agent's page in Agents (`/crm/agents/<membership id>`). **Who:** Agency (company) accounts see their own current agents; Super Admin sees the agents of every agency. Agent accounts do not have this menu. **Locks:** the account must be approved (KYC); the plan must include the feature **Agent insights (team performance)** (plan key `agent_insights`). At release it is on the Business and Enterprise plans; Super Admin can switch it on or off for any plan in CMS > Plans > Plan Features.

## 1. What this module does

An agency hands leads (round-robin, by listing, or manually) and listings to its agents. Agent Insights answers: "Are my agents working what I give them?" For every current agent (Active or Suspended) it shows:

- how many new leads they received in the chosen period and how many are still open;
- **response rate**: the share of those new leads they acted on within 24 hours;
- **response time**: the median time from receiving a lead to the first action;
- **untouched leads**: open leads with no action for more than 24 hours since they got them;
- **overdue follow-ups**: scheduled follow-ups past their due time;
- **live listings**: how many of their agency listings on the market are live on the website;
- **listing quality**: the average quality score of those listings (the same score as the ring on property cards);
- **closed**: leads closed in the period and listings sold / rented by them in the period;
- an **Agent score** out of 100 built from these.

The agent page lists the actual untouched leads, overdue follow-ups and listings needing work, with links to open each one.

This is different from Reports > Agents (Module 17), which counts volumes (leads, closed, conversion). Agent Insights is about how the work is handled.

| Screen | What the tester sees |
| --- | --- |
| Agent Insights list | Title and intro; five cards (Agents, Leads received · period, Untouched leads, Overdue follow-ups, Live listings); filter bar (search "Agent name or email", period, sort, Clear); table with columns Agent, Agent score, Leads received, Response rate, Response time, Untouched, Overdue follow-ups, Live listings, Listing quality, Closed, Details; pager (20 per page). |
| Agent page | Header card (same style as a lead's page): "All agents" back link and the period drop-down; photo or initials, name, Suspended badge, email, phone, agency (Super Admin), Member since; links Their leads, Their listings, Agent profile; on the right the Agent score ring with its one-line verdict; under it six figures (Leads received, Response rate, Response time, Closed, Live listings, Listing quality). Below, four tabs: **Score breakdown** (two cards, Leads and Listings, each part with points, a coloured bar and a tip), **Untouched leads**, **Overdue follow-ups**, **Listings needing work** (each tab shows its count, red when above 0; tables 10 per page). |
| Locked | Plan without the feature: padlock on the menu item and the card "Unlock agent insights" with View Plans (agency). |

## 2. How it works (business rules)

### 2.1 Which records count

- Only the **agency's own** records: leads owned by the agency and assigned to the agent, and agency listings assigned to the agent. An agent's personal leads and listings never count.
- Agents listed: current members of the agency, status Active or Suspended (Suspended shows a grey badge). Pending, invited, requested and former agents are not listed.
- Deleted (trashed) leads do not count.
- Listings: only listings still on the market (not sold / rented).

### 2.2 What "acting on a lead" means

A lead is acted on when, **after it reached the agent**, any of these happens:

- a team note is added to the lead;
- the stage or the status is changed;
- a follow-up is added (scheduled or logged as done).

System entries do not count: repeat enquiries, assignment changes, source / tags / details changes. "Reached the agent" = the lead's assigned time; for a lead the agent created themself (no assignment time), its created time. If a lead is reassigned, the clock restarts for the new agent.

### 2.3 Period (Last 7 / 30 / 90 days, default 30)

The period applies to: Leads received, Response rate, Response time, Closed (leads closed and listings sold / rented). It does **not** apply to Untouched leads, Overdue follow-ups, Live listings and Listing quality, which always show the situation right now.

### 2.4 The numbers

| Measure | Rule |
| --- | --- |
| Leads received | Agency leads assigned to the agent whose assigned time is in the period. Small text: open leads (not closed) the agent holds now. |
| Response rate | Of the leads received in the period, the % acted on within 24 hours. Leads younger than 24 hours that have not been acted on yet are not judged (left out). "—" when nothing can be judged. Bar colour: green 80%+, orange 50–79%, red under 50%. |
| Response time | Median minutes from receiving a lead to the first action, over the leads received in the period that were acted on (at any time). Shown as "< 1 min", "N min", "N h" or "N days". "—" when none. |
| Untouched | Open leads (not closed) assigned to the agent, received more than 24 hours ago, with no action since they got them. All time, not limited to the period. Red when above 0. |
| Overdue follow-ups | Scheduled follow-ups on the agent's agency leads whose due time has passed. Small text: all scheduled (open) follow-ups. |
| Live listings | Agency listings on the market that are Active and allowed to be live (permit approved, not expired, not on review hold), "of" all their agency listings on the market. |
| Listing quality | Average quality score (0–100) of those listings. Pill colour: green 80+, orange 50–79, red under 50. "—" when the agent has no listings. |
| Closed | Leads closed in the period; small text: listings sold / rented in the period with this agent as the selling agent ("deals"). |

### 2.5 Agent score (0–100)

| Part | Max points | How points are given | Left out when |
| --- | --- | --- | --- |
| Response rate | 25 | 25 × response rate | nothing to judge in the period |
| Response time | 15 | median ≤ 15 min: 15; ≤ 1 h: 12; ≤ 4 h: 8; ≤ 24 h: 4; slower: 0 | no lead acted on in the period |
| No untouched leads | 10 | 10 × (1 − untouched ÷ open leads) | the agent has no open leads |
| Follow-ups on time | 15 | 15 × (1 − overdue ÷ scheduled follow-ups) | no scheduled follow-ups |
| Listing quality | 20 | 20 × average quality ÷ 100 | no listings |
| Live listings | 15 | 15 × live ÷ all listings | no listings |

- Points are rounded to whole numbers. A part that is "left out" shows **Not applicable** and does not count; the score = points earned ÷ maximum of the parts that apply × 100, rounded.
- No part applies (no leads, follow-ups or listings): the score shows "—" and the card says "Not enough leads or listings to score yet."
- Ring colour and card text: 80+ green "Handling assigned work well."; 50–79 orange "Some things need attention."; under 50 red "Needs follow-up from you."
- Each part without full points shows a tip under it, e.g. "3 open leads have had no activity since they were assigned." or "Complete or reschedule 2 overdue follow-ups."

Worked example: 10 leads received (all older than 24 h), 8 acted on within 24 h, median 40 min, 6 open leads of which 1 untouched, 4 scheduled follow-ups of which 1 overdue, 5 listings, 4 live, average quality 70. Points: 25 × 0.8 = 20; 12; 10 × 5/6 = 8; 15 × 3/4 = 11; 20 × 0.7 = 14; 15 × 4/5 = 12. Total 77 of 100 → score 77, orange.

### 2.6 Sort, search, paging

- Sort **Needs attention first** (default): agents with the most untouched leads + overdue follow-ups first, then by name. Sort **Name**: A to Z.
- Search matches the agent's name or email (typing waits briefly, then searches).
- Clear resets search, period (30 days) and sort.
- 20 agents per page. The five cards always count all agents the viewer can see, not only the current page.

### 2.7 Agent page lists

| List | Shows | Order | Link |
| --- | --- | --- | --- |
| Untouched leads | Name, email or phone, stage, received (time ago, exact time on hover) | Oldest first | Open → lead page |
| Overdue follow-ups | Lead name, type (Call / WhatsApp / Email), note, was due (time ago, red) | Most overdue first | Open lead → lead page |
| Listings needing work | Ref, title, quality pill, what to fix | Lowest quality first | Edit → listing form (Properties or Commercial) |

"What to fix" labels: Changes requested, Permit expired, Permit not approved yet, On hold for review, Inactive, Low quality (quality under 50). A listing appears when it has at least one label. Each list is its own tab with its own pager (10 per page); the open tab (`tab`), the page numbers (`leads_page`, `follow_ups_page`, `listings_page`) and `days` are kept in the address, so a reload or a shared link opens the same view. Switching tabs does not reload the data. Score breakdown is the default tab.

Buttons: Their leads → Leads filtered by this agent (`/crm/leads?agent_id=<id>`); Their listings → Properties filtered by this agent (`/crm/properties?agent=<id>`); Agent profile → the agent's page in Agents (agency only).

### 2.8 Plans

- New plan feature line on plan cards (CRM > Plans, website pricing, CMS > Plans): "Agent insights (team performance)", ticked or crossed out.
- CMS > Plans > edit plan > Plan Features: new switch "Agent insights (team performance)" with the help text "Agencies see how each agent handles assigned leads and listings, with an agent score."
- At release the feature was added automatically to every paid plan that already had all other gated features (Business, Enterprise). Free, Basic and Pro do not have it unless Super Admin switches it on.
- The agency's own plan decides access. Agents never see the menu.

## 3. Who can do what

| Role | Menu | List | Agent page | Notes |
| --- | --- | --- | --- | --- |
| Super Admin | Yes (never plan-locked) | Agents of every agency; the agency name shows under each agent | Any current agency agent | No Agent profile button |
| Agency, plan with the feature | Yes | Own current agents | Own agents only; another agency's agent ID gives "not found" | |
| Agency, plan without the feature | Yes, with padlock | "Unlock agent insights" card | Same card | API answers 403 with `plan_locked` |
| Agency, KYC not approved | Locked "Unlocks after KYC approval" | Refused | Refused | |
| Agent | No menu | Refused ("Agent insights are for agency accounts.") | Refused | |

## 4. Fields and validation

| Input | Rule |
| --- | --- |
| Period (`days`) | 7, 30 or 90; any other value is treated as 30. |
| Sort (`sort`) | attention or name; any other value is treated as attention. |
| Search (`search`) | Up to 100 characters; name or email, partial match. |
| Pages | Whole numbers; a page past the end shows an empty table. |

## 5. Test scenarios

Prepare: one agency on Business (or Enterprise) with at least three approved agents (A, B, C) and one suspended agent (D); a second agency (Agency 2) with its own agent; an agency on Pro. Give agent A leads and listings as described per scenario. Use test accounts only.

| ID | Scenario | Priority | Steps | Expected result |
| --- | --- | --- | --- | --- |
| AI-01 | Access — Agency with the feature | High | 1. Sign in as the Business agency.<br>2. Sidebar > Insights > Agent Insights. | Page opens with cards, filter bar and one row per Active / Suspended agent; D has a Suspended badge. |
| AI-02 | Plan lock | High | 1. Sign in as the Pro agency.<br>2. Open Agent Insights and type /crm/agent-insights/<agent id>. | Menu item has a padlock; both pages show "Unlock agent insights" with View Plans. |
| AI-03 | Switch the feature on in CMS | High | 1. CMS > Plans > edit Pro > Plan Features: switch on Agent insights; save.<br>2. Reload the Pro agency's CRM. | Padlock gone; page opens. Pro plan card now shows the line ticked. Switch it back off afterwards. |
| AI-04 | Agent cannot see it | High | 1. Sign in as agent A.<br>2. Check the sidebar; type /crm/agent-insights. | No menu item; the page shows an error (or the "Unlock agent insights" card if the agency's plan lacks the feature), never any data. |
| AI-05 | Privacy between agencies | High | 1. As the Business agency open /crm/agent-insights/<Agency 2 agent id>. | "Not found" error; no data of Agency 2. |
| AI-06 | Super Admin view | Medium | 1. Sign in to Admin, open the CRM > Agent Insights. | Agents of all agencies; agency name under each agent; cards count all agencies; no padlock. |
| AI-07 | Response within 24 h | High | 1. Assign a new lead to A (Leads > Assign).<br>2. As A (or the agency), add a note on it within a few minutes.<br>3. Open Agent Insights, period 7 days. | A's Leads received +1; response rate counts it as answered; response time shows minutes. |
| AI-08 | Young lead not judged | Medium | 1. Assign a new lead to B; do nothing.<br>2. Check B's response rate right away. | Leads received +1, but the response rate does not drop (lead under 24 h is not judged). After 24 h without action it counts as not answered and appears under Untouched. |
| AI-09 | Untouched lead | High | 1. Use a lead assigned to C more than 24 h ago with no note / stage / status change / follow-up since.<br>2. Open C's agent page. | C's Untouched ≥ 1 (red); the lead is in Untouched leads with Open link. After adding a note, it disappears from the list. |
| AI-10 | System entries don't count | Medium | 1. On an untouched lead of C, change only tags or source, or send a repeat enquiry from the website. | The lead stays untouched. Changing the stage removes it. |
| AI-11 | Reassignment restarts the clock | Medium | 1. Reassign a lead A had worked to B.<br>2. Check B. | For B the lead is new; it becomes untouched for B after 24 h with no action by B. A's numbers no longer include it. |
| AI-12 | Overdue follow-up | High | 1. On a lead of A, schedule a follow-up due in the past (or wait until it is overdue).<br>2. Open the list and A's page. | Overdue follow-ups +1 (red) on the row and the card; the follow-up is in A's Overdue follow-ups with type, note and "was due". Completing it removes it. |
| AI-13 | Listings needing work | High | 1. Assign A a listing that is Inactive, one with permit Changes requested, and one with quality under 50.<br>2. Open A's agent page. | All appear in Listings needing work with the right labels, lowest quality first; Edit opens the listing form. Live listings counts only live ones. |
| AI-14 | Score and breakdown | High | 1. Set up agent A as in the worked example (section 2.5).<br>2. Open A's agent page. | Score 77, orange ring, "Some things need attention."; each part shows the points of the example and a tip. |
| AI-15 | Not applicable parts | Medium | 1. Agent with listings but no leads or follow-ups.<br>2. Open their page. | Lead parts show "Not applicable"; the score comes from the listing parts only. Agent with nothing: score "—" and "Not enough leads or listings to score yet." |
| AI-16 | Period | Medium | 1. Switch between 7, 30 and 90 days on the list and on the agent page. | Leads received, response rate / time and Closed change; Untouched, Overdue, Live and Quality do not. The agent page keeps the period in the address. |
| AI-17 | Sort and search | Medium | 1. Sort Needs attention first, then Name.<br>2. Search part of an agent's email.<br>3. Clear. | Attention: the agent with most untouched + overdue on top. Name: A to Z. Search finds the agent. Clear restores defaults. |
| AI-18 | Only agency records | Medium | 1. As agent A (personal account), create a personal lead / listing.<br>2. Check A in the agency's Agent Insights. | Personal records are not counted. |
| AI-19 | Links | Low | 1. On an agent page click Their leads, Their listings, Agent profile.<br>2. In Agents open an agent and click Agent insights. | Leads filtered by the agent; Properties filtered by the agent; the agent's Agents page; the agent's insights page. |
| AI-20 | Help guide | Low | 1. Click "!" in the top bar on Agent Insights. | Help guide "Agent Insights" opens. |
| AI-21 | Mobile | Low | 1. Open both pages at phone width. | Cards in two columns; the score box goes full width under the name; tabs wrap two per line; tables scroll sideways; nothing overlaps. |
| AI-22 | Agent page tabs | Medium | 1. Open an agent page.<br>2. Click each tab; go to page 2 of a list; reload the page. | Score breakdown opens first; each tab shows its count (red above 0) and its list; the address keeps `tab` and the page; after reload the same tab and page show. |

## 6. Expected results checklist

- Menu Insights > Agent Insights for Agency and Super Admin only (the Insights group also holds Property Insights, Lead Insights and Reports); padlock when the plan lacks the feature.
- Business and Enterprise include the feature after the release; plan cards show the new line.
- Cards count all visible agents; the table pages 20 at a time.
- Response rate uses 24 hours; young unanswered leads are not judged.
- Untouched leads and overdue follow-ups match the agent page lists exactly.
- Score = applicable points scaled to 100; Not applicable parts don't lower it.
- No data from another agency is ever shown.

## 7. Known behaviour (not bugs)

- Notes and follow-ups are not stored per author, so an action by the agency owner (or anyone else) on the agent's lead also counts as "acted on" for that agent.
- Leads closed counts any lead of the agent closed in the period, whenever it was received.
- Deals count listings sold / rented in the period where this agent is the selling agent, even if the listing is now assigned to someone else.
- The numbers are calculated live when the page opens; nothing is stored, so a page reload shows changes at once.
- Former agents (left / removed) are not listed; their history stays in Reports.

## 8. Reporting a bug

Include: account (agency name and plan, or Super Admin), agent name and ID, period and sort, the row values and the agent page values you compared, the lead / follow-up / listing IDs involved with their assigned, created and due times, what you did (note, stage change, follow-up), expected vs actual, time, and a screenshot. Mask real client contact details.

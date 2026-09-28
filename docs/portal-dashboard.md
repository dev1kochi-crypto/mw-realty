# Portal (CRM) Dashboard

`/portal/dashboard` — shared by agents, companies and the Super Admin (global view via the admin's
"CRM" button). All numbers are scoped to the signed-in agent/company; Super Admin sees everything.
Design: **"Luxe Estate"** (chosen by the client), compact density.

| Part | File |
|---|---|
| Data | `app/Http/Controllers/Portal/PortalDashboardController.php` |
| View | `resources/views/portal/dashboard/index.blade.php` |
| Charts / count-up numbers | `resources/views/portal/dashboard/_charts.blade.php` |

## Layout

1. **Photo hero** (site skyline image) with greeting, 30-day summary, alert chips, and a
   **membership-card** style plan card (plan, listings used / limit, reports, agents). Super Admin gets
   a "Top Partners" card instead. A floating **shortcut dock** sits on the hero's bottom edge —
   shortcuts are filtered to pages that account type may open (`shortcuts()` in the controller).
2. **6 KPIs**: Total enquiries, Deals in progress, Deals won, Need follow-up (leads in the default
   stage for 2+ days), Listings, Featured.
3. **Performance** (weekly enquiries vs listings) · **Conversion** (win-rate gauge, won/lost/open) ·
   **Buyer Insights** (busiest weekday over 90 days + lead sources).
4. **Deal Pipeline** strip (chevrons per open stage, won/lost).
5. **Latest Enquiries** · **Most Desired Properties** (gallery, photo listings first) · **Inventory**.

## Controller outputs worth knowing

- `planUsage` — plan name, limit, used, remaining, %, agents (companies), reports access.
- `alerts` — stale leads, active leads, featured expiring in 7 days, inactive listings, plan limit reached.
- `sourceRows` — lead sources with fixed colours ("Unknown" last, grey).
- `weekdayLeads` — Mon..Sun counts, last 90 days.
- `deltas` — 30-day change badges for leads and listings.

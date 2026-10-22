# Admin Dashboard (CMS)

The super-admin overview at `/admin/dashboard`. Non-superadmins see `cms-kit::dashboard-restricted`.

| Part | File |
|---|---|
| Data | `app/Http/Controllers/CmsKit/DashboardController.php` (`dashboardData()`, cached 30 s under `admin.dashboard.summary`) |
| View | `resources/views/vendor/cms-kit/dashboard.blade.php` (styles + Chart.js inline) |
| "CRM" button in the header → portal dashboard | `resources/views/vendor/cms-kit/layouts/cms.blade.php` |

## Layout (top to bottom)

1. **Welcome banner** (navy) with the last-30-days summary, Est. MRR / approval rate / open leads,
   and a "N items need your decision" link — next to **Quick Links** (8 shortcut tiles).
2. **6 KPI cards** (solid colour): Partners, Awaiting Approval, Listings, CRM Leads, Collected (30 d),
   Paid Subscribers — each with a 30-day change badge (hover = exact comparison) and a sparkline or
   progress bar.
3. **Platform Activity** (weekly leads / listings / sign-ups, 12 weeks) + **Needs Attention**
   (pending accounts, upgrade requests, failed payments, active leads, enquiries, inactive listings;
   only non-zero items; latest 3 pending accounts).
4. **Lead Pipeline** (by stage, merged across owners by stage name; Won/Lost; win rate) +
   **Lead Sources** + **Account Status** donut.
5. **Collected Revenue** (actual paid invoices, daily/weekly/monthly) + **Plan Distribution** +
   **Inventory Mix** (sale/rent split, property types).
6. **Top Performers / Recent Leads / Latest Listings** tabs (full width).

## Design rules agreed with the client

- Formal palette: navy `#1e3a5f`, blue, teal, green, amber, maroon (`.tone-*` classes).
- KPI cards are solid colour; section cards are white with a **light shade** of their tone
  (`--tone-shade`) — no solid header bands.
- Compact density: small paddings/fonts so most information fits on one screen.

## Notes

- Revenue chart = `PlanPayment::paid()` by `paid_at` (not the MRR estimate).
- 30-day deltas show "New" when the previous period was 0.
- Win rate = won ÷ (won + lost), where "lost" = closed stages whose name contains "lost".

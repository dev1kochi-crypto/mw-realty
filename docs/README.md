# MW Realty — Developer Docs

One file per area. Start with the one for the part you're changing.

| Doc | Covers |
|---|---|
| [search-and-filters.md](search-and-filters.md) | Admin > Filters, the filters API (options from the properties table), query parameters, Home search, Properties listing, Find Properties cards, saved searches, adding a filter |
| [location-autocomplete.md](location-autocomplete.md) | City / community / address suggestions on Home, Properties and Commercial; what Search does for each |
| [admin-dashboard.md](admin-dashboard.md) | `/admin/dashboard` layout, data, design rules |
| [portal-dashboard.md](portal-dashboard.md) | `/portal/dashboard` (agents, companies, Super Admin global view) |
| [portal-listings.md](portal-listings.md) | Portal Properties / Commercial menus (`segment`), search, pagination, reorder + Move to, Featured menu and date-based featuring |
| [listing-compliance.md](listing-compliance.md) | Dubai DLD rules: advertising permit, Madmoun QR, Form A, Listing Approvals, auto-unpublish on permit expiry |
| [wishlist.md](wishlist.md) | Favourite hearts on property cards |
| [translations.md](translations.md) | Static UI text, missing-key fallback, which files are live, deploying |
| [frontend-legacy-script.md](frontend-legacy-script.md) | How Vue pages and the legacy `script.js` widgets work together; deploy notes |

## Deploy checklist (frontend changes)

1. `npm run build` → deploy `public/build`.
2. Deploy changed files under `public/frontend/assets/` (`script.js`, `scss/style.css`, icons).
3. Deploy `resources/lang/cms-static/*.json` if new UI text keys were added.
4. Hard refresh (Ctrl+Shift+R) to check.

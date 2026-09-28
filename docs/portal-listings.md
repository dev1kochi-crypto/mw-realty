# Portal Listings (Properties, Commercial, Featured)

`/portal/properties`, `/portal/commercial` and `/portal/featured`. Agents and companies see their own
listings; Super Admin (browsing the portal) sees everyone's.

## Properties vs Commercial

Both menus use the same `properties` table, form and screens. The `segment` column tells them apart:
`residential` (Properties menu) or `commercial` (Commercial menu). It's set from the menu the listing
was created in.

| | Properties | Commercial |
|---|---|---|
| CRM routes | `portal.properties.*` | `portal.commercial.*` (index, create, store, edit, update, show, reorder, move) |
| Controller | `PortalPropertyController` | `PortalCommercialController` (extends it, only `segment()` differs) |
| Website | `/properties`, Home search, filters API, location suggestions | `/commercial`, `scope=commercial` suggestions |

- **Plan property limit counts both menus together.**
- Per-listing AJAX actions (feature, unfeature, status, delete, gallery, bulk) always use the
  `portal/properties/{id}/…` URLs, whichever menu the listing is in.
- If you open a listing through the other menu's URL (e.g. a dashboard link to
  `/portal/properties/{id}/edit` for a commercial listing), it redirects to its own menu.
- The migration marked existing `office`, `retail-shop`, `warehouse` and `showroom` listings as
  commercial. That's what `/commercial` used to filter on.
- `/commercial` filters use the same server code as `/properties`
  (`PropertiesPageService::applyFilters`). They cover type, purpose, price, area, completion,
  bathrooms, amenities and sort, plus the keyword and location boxes. The type options and the
  price range come from `/api/property-filters?scope=commercial`, so only types with live
  commercial listings are offered.
- Demo data: `php artisan db:seed --class=MoreCommercialPropertySeeder` adds 12 varied commercial
  listings. It also runs as part of `DatabaseSeeder`.

## Listing page

- **Search** (`?q=`): title, address, community and city in any active language, plus reference no
  and RERA. Super Admin can also search by agent/agency name. Not case-sensitive.
- **16 per page** (a full 4-column row). The sort ends with `id` so pages never repeat or skip a
  listing when `order_index` / `created_at` are equal.

## Display order

- **Drag** a card's grip handle to reorder the current page (`reorder`). Drag is turned off while
  searching.
- **Move to** (the sort icon on each card) → Top / Bottom / Position N (`move`). This works across
  pages. The whole list is renumbered 1…n, then the page opens where the listing landed and the
  card is highlighted.
- Order is per menu, and per owner (Super Admin reorders the global list).

## Featured

Booked with a **start and end date** in the popup (`properties/_feature_modal.blade.php`). The same
popup is used on the listing cards and the Featured menu (which adds a listing picker).

The picker doesn't load any listings with the page. It calls
`GET /portal/featured/eligible?q=&page=` (`PortalFeaturedController::eligible`): 20 listings at a
time, the next 20 load as you scroll, and search runs in the database. It uses `simplePaginate`, so
there's no count over the whole table. Tested with 50,000 listings: the first page takes about
80 ms, a deep page about 130 ms, a search about 0.8 s.

- Start = today → live now. Start in the future → **scheduled** (`featured = false`,
  `featured_from` set). Both dates count: 1 Oct → 7 Oct is 7 days.
- Agents/companies: limited by the plan's `featured_per_month`, `featured_period` and
  `featured_max_days`.
  - `concurrent`: no more than N features overlapping the chosen period.
  - `month`: no more than N features starting in that calendar month.
- Super Admin: no quota, up to 365 days, or no end date.
- **Stop** a live feature: it still counts for the month. **Cancel** a scheduled one: the booking is
  deleted and the slot comes back.
- `FeaturedListingService::sync()` makes scheduled features live and ends expired ones. It runs from
  `properties:expire-featured` (every 15 min), and whenever the listing or Featured pages load.
- The property form has no Featured switch. Featuring is done only from the popup.

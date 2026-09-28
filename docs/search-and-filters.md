# Search & Filters

How property search works end to end: **Admin > Filters** decides which filters exist and how they
are labelled, the **properties table** decides which option values are offered, and the Home search,
the Properties listing and the Commercial page all send the same query parameters to the API.

## 1. Where filters are defined (admin)

**Admin > Filters** (`resources/views/filters/*`, `App\Http\Controllers\FilterController`,
models `App\Models\Filter` + `App\Models\FilterValue`).

| Field | Meaning |
|---|---|
| Key | The property column the filter searches. Must be one of `Filter::SELECT_KEYS` (`listing_type`, `completion_status`, `property_type`, `category`, `location`) or `Filter::NUMBER_KEYS` (`bedrooms`, `bathrooms`, `sqft`, `price`). Other keys are ignored by the website. |
| Type | `select` (option values) or `range` (min/max, e.g. price, sqft). |
| Label (per language) | Field / section title on the website. |
| Values | For select filters: each value's label + order + status. |
| Show on | `home` and/or `listing` — which page gets the filter. |
| Status / Order | Off = hidden on the website. Order = order of sections in the listing's "More filters" panel. |

## 2. The filters API

`GET /api/property-filters?page=home|listing&lang=en` → `App\Http\Controllers\PropertyFilterController`

```json
{ "filters": [
  { "key": "property_type", "label": "Property Type", "type": "select",
    "options": [ { "value": "apartment", "label": "Apartment", "count": 8 } ] },
  { "key": "price", "label": "Price Range", "type": "range", "min": 0, "max": 19000000, "step": 50000 }
] }
```

**Option values come from the properties table**, not only from the admin list:

- For `listing_type`, `completion_status`, `property_type`, `category`, `location`: options are the
  distinct values used by **active** properties (with a `count`). An admin value nobody uses (e.g.
  "Plot" with 0 listings) is **not** offered, so a visitor never picks an option that returns nothing.
  The admin value supplies the label and order; values not defined in the admin are shown humanised
  (`retail-shop` → "Retail Shop"); values the admin switched **off** stay hidden.
- `bedrooms` / `bathrooms` keep the admin's fixed list (1, 2, 3, 4, 5+).
- Ranges (`price`, `sqft`): `max` is the highest value on active listings, rounded up (price to the
  next 1M, sqft to the next 500); `step` is 50,000 / 50.

Frontend access: `resources/js/composables/usePropertyFilters.js` (`usePropertyFilters('home'|'listing')`
→ `filters`, `loaded`, `byKey(key)`, `options(key)`), cached per page + language.

## 3. Query parameters (shared by every search)

`GET /api/properties` (`Api\PropertiesController` → `App\Services\PropertiesPageService`)
and the `/properties` page URL use the same names:

| Parameter | Example | Notes |
|---|---|---|
| select filters | `listing_type=rent`, `property_type=villa`, `category=commercial`, `location=dubai-marina`, `completion_status=off_plan`, `bedrooms=2`, `bathrooms=5+` | the filter's key = the parameter name |
| ranges | `min_price=1000000&max_price=2000000`, `min_sqft=2000` | `min_{key}` / `max_{key}` |
| location | `city=Dubai`, `community=Dubai Marina`, `location=marina` | see [location-autocomplete.md](location-autocomplete.md) |
| amenities | `amenities[]=furnished&amenities[]=gym` (URL: `amenities=furnished,gym`) | not an admin filter (free-text on each property) |
| other | `floor_plans=1`, `sort=price_asc\|price_desc\|popular\|recent`, `page=2` | |

**Backwards compatible** (old links / saved searches): `category=sale|rent|off_plan` (old meaning:
purpose), `completion=`, `min_area=` / `max_area=` are still understood and mapped to the new names.

Commercial (`/api/commercial`, `App\Services\CommercialPageService`) accepts `location`, `city`,
`community`, `property_type`, `search`, `category`.

## 4. Where each page uses it

### Home banner search (`resources/js/pages/Home.vue`)
- **Fixed layout** — always 4 tabs (Buy, Rent, Ready, Off-Plan) and 4 fields (Location, Bedroom,
  Price Range, Property Type). Only the *values* come from the admin: tab labels, Bedroom and
  Property Type options, the price slider's range. If a filter is off in the admin the field keeps
  working on built-in defaults.
- Search (`submitHeroSearch`) → `/properties?listing_type=…&property_type=…&bedrooms=…&min_price=…`,
  or the property page when an address was picked.

### Properties listing (`resources/js/pages/PropertiesDubai.vue`)
- Top bar: Purpose (`listing_type`), Location, Property Type, Bedrooms, Bathrooms; toolbar tabs
  All / Ready / Off-Plan (`completion_status`), sort. A top-bar field is hidden when the admin turns
  its filter off.
- **More filters panel is generated from the admin filters** (in admin order): every select filter
  becomes a row of options, `price` the slider, `sqft` min/max inputs. A new admin filter with a
  supported key appears automatically. Amenities and floor plans are fixed sections below.
- Top-bar dropdowns apply immediately; panel choices apply on **Apply**; **Reset** / **Clear Filters**
  clear everything.
- **Clear Filters** shows the number of applied filters as a badge (tooltip lists them).
- The URL always mirrors the applied filters (shareable, Back works). A plain `/properties` visit
  applies **no** filter (all properties).
- Applied filter fields show a red label + bold value (`.is-filled`).
- The heading follows the filters ("Penthouse Properties for Rent in Dubai").

### Home "Find Properties" cards
Each card links to `/properties?property_type=<card's type>` (all purposes, matching the card count).

### Saved searches
"Save Search" stores the listing's exact query string (`currentQuery()`); Profile > Saved Searches >
"View results" reopens `/properties` with it (`Api\CustomerController` returns `criteria`).

## 5. Adding a new filter (checklist)

1. Admin > Filters > New: key = an existing property column from the lists above, type, labels,
   values (select), Show on, Status on.
2. Done — the listing panel and the API pick it up. For a **new column**, also add it to
   `Filter::SELECT_KEYS` / `NUMBER_KEYS` (and `PropertiesController` for anything that isn't a plain
   `column = value` match).

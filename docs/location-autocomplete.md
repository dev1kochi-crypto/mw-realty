# Location Autocomplete

The Location box on the **Home search**, the **Properties listing** and the **Commercial page**
suggests matching **cities, communities and full addresses** as you type. Suggestions come live from
the properties table, so a newly added property's address/community/city is suggested immediately.

## Behaviour

| You pick… | The box shows | Search does |
|---|---|---|
| **Address** (red "Address" tag, with the property title under it) | the address | opens that property: `/property-details/{slug}` |
| **Community** (teal tag, with city + listing count) | the community | listing filtered by `community=<name>` (exact) |
| **City** (blue tag, with listing count) | the city | listing filtered by `city=<name>` (exact) |
| nothing — just typed text | your text | listing filtered by `location=<text>` (partial match on address, community, city and the location field) |

- Starts after 2 characters, 250 ms after you stop typing; ↑/↓ to move, Enter to pick (or search when
  nothing is highlighted), Esc to close. Typing again after a pick turns it back into free text.
- Arabic works: typing Arabic matches the Arabic address/community/city, and labels are shown in the
  visitor's language (English fallback).
- On the Commercial page suggestions are limited to Commercial-menu listings (`scope=commercial`, `segment = commercial`); Home and Properties suggest everything else.

## Pieces

| Part | File |
|---|---|
| Component (input + dropdown, keyboard, emits `select` / `enter`) | `resources/js/components/LocationAutocomplete.vue` |
| Suggestions API: `GET /api/location-suggestions?q=mar&lang=en[&scope=commercial]` (throttled 120/min, cached 60 s) | `app/Http/Controllers/Api/LocationSuggestionController.php`, route in `routes/api.php` |
| Matching + suggestion building (shared) | `app/Support/LocationFilter.php` — `suggest()` and `apply()` |
| Listing filter (`city`, `community`, `location`) | `PropertiesPageService` and `CommercialPageService` both call `LocationFilter::apply()` |
| Styles | `public/frontend/assets/scss/style.css` → "Location autocomplete" (`.mw-loc*`) |

Response shape:

```json
{ "suggestions": [
  { "type": "city", "label": "Dubai", "sub": null, "count": 31 },
  { "type": "community", "label": "Dubai Marina", "sub": "Dubai", "count": 5 },
  { "type": "address", "label": "Dubai Marina, Dubai", "sub": "Marina View 2BR Apartment", "slug": "marina-view-2br-apartment-prop022" }
] }
```

Data used: `properties.translations[en|ar].address / community / city` (+ the `location` slug column).
At most 3 cities, 4 communities and 5 addresses are returned (8 in total).

## Using it on another page

```vue
<LocationAutocomplete v-model="locationInput" scope="all" :placeholder="…" @select="onPlacePicked" @enter="submitSearch" />
```

In `onPlacePicked(place)` set `city` / `community` from `place.type`, or keep the text as `location`
when `place` is null; in `submitSearch()` open `/property-details/{place.slug}` when an address was
picked. `PropertiesDubai.vue` and `Commercial.vue` both have this pattern.

## Gotcha

The home hero styles every `.mw-hero__field span` as a full-width transparent box and every
descendant as white text. The `.mw-loc__menu …` rules at the end of the stylesheet undo that — keep
them if you restyle the hero.

// Filters travelling between a listing page and its map view (/properties ⇄ /properties/map, etc.).
// The listing pages keep filters in memory rather than in the URL, and the list and map are separate
// routes (App.vue keys the page by route name, so switching remounts it) — so the toggle leaves the
// current filters here and the other view picks them up once on mount.
const carried = {};

/** Leave `query` (the page's filter query object) for the next view of `listing` to pick up. */
export function carryFilters(listing, query) {
    carried[listing] = { ...query };
}

/** Take (and clear) whatever the previous view of `listing` left; null if nothing. */
export function takeCarriedFilters(listing) {
    const query = carried[listing] || null;
    delete carried[listing];
    return query;
}

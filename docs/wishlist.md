# Wishlist (favourite hearts)

Logged-in customers can heart a property on any card; the Profile's Wishlist tab lists them.

| Part | File |
|---|---|
| Shared state (one `/customer/session` fetch, `isWishlisted(id)`, `toggleWishlist(id)`) | `resources/js/composables/useWishlist.js` |
| Toggle endpoint | `POST /customer/wishlist/{propertyId}` |
| Filled heart icon | `public/frontend/assets/images/icons/heart-filled.svg` |

## Look

- Not saved: outline heart. **Saved: solid red heart on a white circle**, with a short "pop"
  animation (disabled for reduced-motion users).
- Image-based hearts swap `heart.svg` → `heart-filled.svg`; inline-SVG hearts fill red via CSS
  (`.is-saved`).

## Where the hearts are

Home (New Projects, Premium, Luxury, Realty Property), Properties listing, Agent and Agency detail
pages, Profile wishlist. Every card passes the property `id` and binds
`:class="{ 'is-saved': isWishlisted(id) }"` + `@click.stop="toggleWishlist(id)"`.
Not logged in → clicking a heart goes to `/login`.

The Realty Property card heart sits top-right as a white circle (it used to be a grey circle at the
bottom and wasn't connected at all).

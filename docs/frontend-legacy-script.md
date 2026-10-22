# Frontend: Vue pages + legacy `script.js`

The public site is a Vue SPA (`resources/js/pages/*.vue`, built with Vite into `public/build`), but
many widgets (sliders, dropdowns, price range, tabs, reveal-on-scroll) come from the older
`public/frontend/assets/js/script.js`, which scans the DOM.

## Rules of thumb

- **After async data renders, call** `nextTick(() => window.MWRealty && window.MWRealty.refresh())`
  — `refresh()` re-runs every init. Pages already do this after their API data arrives.
- **Each init runs on its own** (`runAllInits` wraps each in try/catch): one failing widget can no
  longer stop the dropdowns, price sliders and forms after it. Failures log
  `[MWRealty] initX failed:` as a console warning.
- **Dropdowns** (`[data-dropdown]`): bound once (guarded); option clicks are delegated, so options
  rendered later still close the menu. If Vue renders the label text, mark it
  `data-dropdown-label-reactive` so the script doesn't overwrite it (otherwise Vue loses the label).
- **Price slider** (`[data-price-slider]`, only inside a `[data-dropdown]`): bound once; reads its
  min/max/step live from the inputs, so Vue can change the range later. Outside a dropdown (e.g. the
  listing's filter panel) the slider is driven by Vue instead.
- **Tabs**: the script only handles `[data-tab-group]` containers. Vue-driven pill tabs keep the
  `data-tab` attribute (for styling) but drop `data-tab-group`.
- **Slick sliders**: skip empty/already-initialised tracks (slick throws on an empty track).
- **Reveal animation** (`[data-reveal]`, starts at opacity 0): content rendered after the scan needs
  `refresh()`, or it stays invisible (this was the "About page blank on the server" bug).

## Deploy

After changing any `.vue`/`.js` under `resources/js`, run `npm run build` and deploy `public/build`.
`script.js` and `style.css` are served as-is from `public/frontend/assets/` (cache-busted by file
time), so a hard refresh is enough after deploying them.

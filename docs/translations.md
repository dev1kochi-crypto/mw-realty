# Static Text / Translations

UI copy (buttons, labels, badges…) is loaded per language from JSON files edited in
**Admin > Languages > Translations**.

| Part | File |
|---|---|
| Frontend `t('home.badges.verified')` | `resources/js/composables/useStaticText.js` |
| API | `GET /api/static-translations?lang=en` → `Api\StaticTranslationController` |
| Files (the ones actually used) | `resources/lang/cms-static/en.json`, `ar.json` |
| Reader/writer | `vendor/mightywarnerskochi/cms/src/Services/StaticTranslationService.php` |

Laravel uses `resources/lang` when that folder exists, so **`resources/lang/cms-static/*.json` are
the live files**. `lang/cms-static/` is an old, unused 17-key copy.

## Missing keys

If a key is missing from the file, `t()` shows a readable version of the key's last part
(`home.badges.verified` → "Verified") instead of the raw key, and logs
`[i18n] Missing translation: …` once in the browser console. Pass an explicit fallback when the
default wording matters: `t('properties_listing.filter_bar.any_purpose', 'Any')`.

## Deploying

When new keys are added in code, deploy the updated `resources/lang/cms-static/en.json` and
`ar.json` too — otherwise the server shows the humanised fallback. If translations were edited on
the server through the admin, back up the server copy before overwriting.

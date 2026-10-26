# Static Text / Translations

UI copy (buttons, labels, badges…) is loaded per language from JSON files edited in
**Admin > Languages > Translations**.

| Part | File |
|---|---|
| Frontend `t('home.badges.verified')` | `resources/js/composables/useStaticText.js` |
| API | `GET /api/static-translations?lang=en` → `Api\StaticTranslationController` |
| Files (the ones actually used) | `resources/lang/cms-static/en.json`, `ar.json` |
| Admin edits (git-ignored) | `storage/app/private/cms-static/en.json`, `ar.json` — only changed values |
| Reader/writer | `app/Services/StaticTextOverrides.php` (extends the package's `StaticTranslationService`) |

Laravel uses `resources/lang` when that folder exists, so **`resources/lang/cms-static/*.json` are
the live files**. `lang/cms-static/` is an old, unused 17-key copy.

## Missing keys

If a key is missing from the file, `t()` shows a readable version of the key's last part
(`home.badges.verified` → "Verified") instead of the raw key, and logs
`[i18n] Missing translation: …` once in the browser console. Pass an explicit fallback when the
default wording matters: `t('properties_listing.filter_bar.any_purpose', 'Any')`.

## Deploying

`resources/lang/cms-static/*.json` are the **defaults** and live in git: add new keys there and
deploy as usual. Saving in **Admin > Languages > Translations** never changes them. Only values
that differ from the defaults are written to `storage/app/private/cms-static/{code}.json`, and the
site shows the defaults with those edits on top. So `git pull` on the server never conflicts, and
admin edits survive deploys. An admin edit wins over a later change to that key's default; a key
removed from the defaults drops out of the edits too.

One-off for a server edited before this split (its tracked files show as modified):

```bash
mkdir -p storage/app/private/cms-static-backup
cp resources/lang/cms-static/*.json storage/app/private/cms-static-backup/
git checkout -- resources/lang/cms-static && git pull
php artisan static-texts:import-edits storage/app/private/cms-static-backup --base=ORIG_HEAD
```

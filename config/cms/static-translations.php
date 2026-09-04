<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Static frontend translations (JSON)
    |--------------------------------------------------------------------------
    |
    | JSON files live under Laravel's lang directory, in a subdirectory named
    | below. English ({master_code}.json) is the master file; other languages
    | are kept in sync with its key structure when edited in the admin panel.
    |
    */
    'subdirectory' => 'cms-static',

    'master_code' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Vue (SPA) static texts editor URL
    |--------------------------------------------------------------------------
    |
    | When non-empty, the file icon opens this URL instead of the Blade editor.
    | Placeholders: {code} lowercase locale, {CODE} uppercase, {id} language row id
    | (matches /languages/{id}/translations).
    |
    | Define this in PHP—no env needed. Examples after merging config in your app:
    |
    |   'vue_editor_url' => rtrim(config('app.url'), '/') . '/admin#/static-texts/{code}',
    |
    | Or reuse your CMS path prefix:
    |
    |   'vue_editor_url' => url(config('cms-kit.common.auth.prefix', 'admin') . '#/static-texts/{code}'),
    |
    | Leave null or '' to use the built-in Blade editor at /languages/{id}/translations.
    |
    */
    'vue_editor_url' => null,
];

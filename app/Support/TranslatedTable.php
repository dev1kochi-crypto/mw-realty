<?php

namespace App\Support;

class TranslatedTable
{
    public static function column($table, string $column, string $attribute)
    {
        // Locale and field names are application-controlled, never SQL from the request.
        $locale = preg_replace('/[^a-zA-Z0-9_-]/', '', app()->getLocale());
        $fallback = preg_replace('/[^a-zA-Z0-9_-]/', '', config('app.fallback_locale'));
        $table->filterColumn($column, function ($query, $keyword) use ($attribute, $locale, $fallback) {
            $query->where(function ($q) use ($attribute, $locale, $fallback, $keyword) {
                $q->where("translations->{$locale}->{$attribute}", 'like', '%'.$keyword.'%')
                    ->orWhere("translations->{$fallback}->{$attribute}", 'like', '%'.$keyword.'%');
            });
        });
        $table->orderColumn($column, fn ($query, $direction) => $query->reorder()->orderBy("translations->{$locale}->{$attribute}", $direction));
        return $table;
    }
}

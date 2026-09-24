<?php

if (!function_exists('media_url')) {
    /**
     * Public URL for a stored media value. Media now lives on Cloudinary and the database holds
     * full URLs, which are returned as-is; a legacy relative path (e.g. "blogs/abc.jpg") still
     * resolves to local public storage so nothing breaks for files not migrated yet.
     */
    function media_url(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        return asset('storage/' . ltrim($value, '/'));
    }
}

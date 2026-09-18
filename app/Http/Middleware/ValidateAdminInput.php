<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ValidateAdminInput
{
    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName() ?? '';
        if ($request->isMethodSafe() || !preg_match('/^(cms|portal)\./', $name)) return $next($request);
        $rules = [];
        if ($request->has('translations')) {
            $rules['translations'] = 'array|max:30';
            foreach (Arr::dot((array) $request->input('translations')) as $key => $value) {
                $rules['translations.'.$key] = 'nullable|string|max:100000';
            }
        }
        foreach (Arr::dot($request->allFiles()) as $field => $file) {
            $isDocument = str_contains($field, 'document') || str_contains($field, 'resume');
            $isVideo = str_contains($field, 'video');
            $isSpreadsheet = str_ends_with($name, '.import');
            $rules[$field] = match (true) {
                $isDocument => 'file|mimes:jpg,jpeg,png,pdf|extensions:jpg,jpeg,png,pdf|max:4096',
                $isVideo => 'file|mimetypes:video/mp4,video/quicktime,video/x-msvideo|extensions:mp4,mov,avi|max:51200',
                $isSpreadsheet => 'file|mimes:xlsx,xls,csv|extensions:xlsx,xls,csv|max:10240',
                // 8192KB ceiling (not 4096) so it never undercuts a controller's own, larger
                // config limit (e.g. ads.image allows up to 8192KB for a wide banner) — this
                // check is just an outer safety net; each controller still enforces its own.
                default => 'image|extensions:jpg,jpeg,png,gif,bmp,webp|max:8192',
            };
        }
        if (str_ends_with($name, '.bulk-action')) {
            $rules['action'] = ['required', Rule::in(['delete', 'active', 'activate', 'inactive', 'deactivate'])];
        }
        if (str_contains($name, '.bulk-')) {
            $rules['ids'] = 'required|array|min:1|max:100';
            $rules['ids.*'] = 'required|integer|min:1|distinct';
        }
        $request->validate($rules);
        return $next($request);
    }
}

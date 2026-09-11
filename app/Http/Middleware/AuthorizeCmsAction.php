<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Additional checks for package routes, kept in the application so upgrades retain them. */
class AuthorizeCmsAction
{
    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName() ?? '';
        if (!str_starts_with($name, 'cms.') || !$user = auth('cms')->user()) {
            return $next($request);
        }
        $parts = explode('.', $name);
        $module = $parts[1] ?? '';
        $action = end($parts);
        if (in_array($module, ['admins', 'roles', 'permissions'], true) && !$request->isMethod('GET')) {
            // Assigning permissions is itself a privileged security operation.
            abort_unless($user->hasRole('superadmin'), 403);
        }
        $module = ['admins' => 'users', 'newsletter-signups' => 'newsletter'][$module] ?? $module;
        $ability = match ($action) {
            'store', 'create' => 'create',
            'destroy' => 'delete',
            'update', 'toggle-status', 'reorder', 'update-section', 'set-default' => 'edit',
            'bulk-action' => $request->input('action') === 'delete' ? 'delete' : 'edit',
            default => null,
        };
        if ($ability && $module !== 'portal-accounts') {
            abort_unless($user->can($module.'.'.$ability), 403);
        }
        if ($module === 'permissions') {
            abort_unless($user->can('permissions.'.($ability ?? 'view')), 403);
        }

        return $next($request);
    }
}

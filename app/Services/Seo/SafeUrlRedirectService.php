<?php

namespace App\Services\Seo;

use CMS\SiteManager\Models\CmsKit\UrlRedirect;
use CMS\SiteManager\Services\UrlRedirectService;
use Illuminate\Validation\ValidationException;

/**
 * The package's redirect service, made loop- and chain-safe. Bound over UrlRedirectService
 * (AppServiceProvider), so slug-change / delete redirects, the admin URL redirects screen and
 * the request middleware all use it.
 *
 * Without this, renaming a slug A → B → A left both "A → B" and "B → A" (an infinite loop —
 * "too many redirects"), and A → B → C made visitors hop twice. Now:
 *   - an automatic redirect's destination is live again, so any rule *from* that destination is
 *     removed (A → B → A just leaves "B → A");
 *   - rules that pointed at the old path are re-pointed straight at the new destination
 *     (A → B then B → C gives "A → C" and "B → C");
 *   - a manual rule that targets itself or would loop is refused with a validation error.
 */
class SafeUrlRedirectService extends UrlRedirectService
{
    private const MAX_HOPS = 10;

    public function upsertRedirect(
        string $oldPath,
        ?string $newUrl,
        int $statusCode,
        ?int $createdBy,
        ?string $source = null,
        ?string $notes = null,
        bool $isActive = true,
    ): UrlRedirect {
        $oldPath = self::normalizePath($oldPath);
        $target = $this->normalizeTarget($statusCode, $newUrl);

        if ($source === 'manual') {
            $this->assertSafe($oldPath, $target);
        } elseif ($target !== null && !$this->isExternal($target)) {
            // Automatic (slug change / delete): the destination is a live page now.
            if ($target === $oldPath) {
                UrlRedirect::query()->where('old_path', $oldPath)->delete();
                return new UrlRedirect(['old_path' => $oldPath]);
            }
            UrlRedirect::query()->where('old_path', $target)->delete();
        }

        $redirect = parent::upsertRedirect($oldPath, $target, $statusCode, $createdBy, $source, $notes, $isActive);
        $this->collapseChainsInto($oldPath, $redirect->new_url, $redirect->id);

        return $redirect;
    }

    /**
     * Throws a validation error when "$oldPath → $target" would point at itself or end up back at
     * $oldPath through other rules. $ignoreId = the rule being edited.
     */
    public function assertSafe(string $oldPath, ?string $target, ?int $ignoreId = null): void
    {
        $oldPath = self::normalizePath($oldPath);
        if ($target === null || $this->isExternal($target)) {
            return;
        }
        $target = self::normalizePath($target);

        if ($target === $oldPath) {
            throw ValidationException::withMessages(['new_url' => 'The destination is the same as the old path — that would redirect the page to itself.']);
        }

        $seen = [$oldPath => true];
        $current = $target;
        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            if (isset($seen[$current])) {
                throw ValidationException::withMessages(['new_url' => "This would create a redirect loop: {$current} already redirects back to {$oldPath}. Edit or delete that rule first."]);
            }
            $seen[$current] = true;

            $next = UrlRedirect::query()->where('is_active', true)->where('old_path', $current)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->value('new_url');
            if (!$next || $this->isExternal($next)) {
                return;
            }
            $current = self::normalizePath($next);
        }

        throw ValidationException::withMessages(['new_url' => 'The destination goes through too many other redirects. Point it at the final page instead.']);
    }

    /**
     * Re-point every rule that leads to $oldPath straight at $target (no multi-hop chains); a rule
     * that would then point at itself is removed.
     */
    public function collapseChainsInto(string $oldPath, ?string $target, ?int $exceptId = null): void
    {
        if ($target === null) {
            return;
        }

        UrlRedirect::query()->where('new_url', self::normalizePath($oldPath))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get()
            ->each(function (UrlRedirect $rule) use ($target) {
                $rule->old_path === $target ? $rule->delete() : $rule->forceFill(['new_url' => $target])->save();
            });
    }

    private function normalizeTarget(int $statusCode, ?string $newUrl): ?string
    {
        $newUrl = trim((string) $newUrl);
        if ($statusCode === 410 || $newUrl === '') {
            return null;
        }

        return $this->isExternal($newUrl) ? $newUrl : self::normalizePath($newUrl);
    }

    private function isExternal(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }
}

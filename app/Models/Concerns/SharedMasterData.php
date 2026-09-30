<?php

namespace App\Models\Concerns;

use App\Services\Crm\AdminOwnerResolver;

/**
 * CRM master data (Stage / Tag / Source) = Super Admin's global items + each account's own.
 * Global items belong to the shared "Admin" owner (AdminOwnerResolver): every agency and agent
 * sees and uses them, but only Super Admin edits / deletes them. An account's own item with the
 * same name as a global one takes its place in that account's lists (no duplicates).
 */
trait SharedMasterData
{
    /**
     * No duplicate names: once a global item exists (created, renamed, or added by the system for
     * a website form), any account's own item of the same name is folded into it.
     */
    public static function bootSharedMasterData(): void
    {
        static::saved(function ($item) {
            if ($item->isGlobal() && ($item->wasRecentlyCreated || $item->wasChanged('name'))) {
                $item->absorbOwnCopies();
            }
        });
    }

    /**
     * Moves the leads of every account's own same-named item onto this global one, then deletes
     * those copies. A quiet data clean-up — not logged as a stage / source change on the lead.
     */
    public function absorbOwnCopies(): int
    {
        $table = $this->getTable();
        $copyIds = static::query()
            ->where("{$table}.portal_user_id", '!=', $this->portal_user_id)
            ->whereRaw("LOWER(TRIM({$table}.name)) = ?", [mb_strtolower(trim($this->name))])
            ->pluck('id')->all();

        if (!$copyIds) {
            return 0;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($copyIds) {
            $this->moveLeadsFrom($copyIds);
            static::whereKey($copyIds)->delete();
        });

        return count($copyIds);
    }

    /** Points the leads using $fromIds at this item instead (leads.stage_id / leads.source_id). */
    protected function moveLeadsFrom(array $fromIds): void
    {
        \Illuminate\Support\Facades\DB::table('leads')
            ->whereIn($this->leads()->getForeignKeyName(), $fromIds)
            ->update([$this->leads()->getForeignKeyName() => $this->id]);
    }

    /** Portal user id that owns the global (Super Admin) items — resolved once per request. */
    public static function globalOwnerId(): int
    {
        $key = 'crm.master.global-owner-id';
        if (!app()->bound($key)) {
            app()->instance($key, AdminOwnerResolver::resolve()->id);
        }

        return app($key);
    }

    /**
     * What an account can see and pick: its own items plus the global ones it hasn't overridden.
     * Null = Super Admin's all-accounts view (no scoping).
     */
    public function scopeForOwner($query, ?int $ownerId)
    {
        if (!$ownerId) {
            return $query;
        }
        $globalId = static::globalOwnerId();
        if ($ownerId === $globalId) {
            return $query->where($this->qualifyColumn('portal_user_id'), $globalId);
        }
        $table = $this->getTable();

        return $query->where(fn ($q) => $q
            ->where("{$table}.portal_user_id", $ownerId)
            ->orWhere(fn ($global) => $global
                ->where("{$table}.portal_user_id", $globalId)
                ->whereNotExists(fn ($own) => $own->selectRaw('1')->from("{$table} as own_copy")
                    ->where('own_copy.portal_user_id', $ownerId)
                    ->whereRaw("LOWER(own_copy.name) = LOWER({$table}.name)"))));
    }

    /** Only the account's own items — what it may edit, delete, reorder. */
    public function scopeOwnedBy($query, int $ownerId)
    {
        return $query->where($this->qualifyColumn('portal_user_id'), $ownerId);
    }

    /** Ids an account may attach to its leads: own + global (for Rule::exists()->whereIn()). */
    public static function usableOwnerIds(?int $ownerId): array
    {
        return array_values(array_unique(array_filter([$ownerId, static::globalOwnerId()])));
    }

    public function isGlobal(): bool
    {
        return (int) $this->portal_user_id === static::globalOwnerId();
    }
}

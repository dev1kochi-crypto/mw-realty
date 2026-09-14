<?php

namespace App\Services\Crm;

use App\Models\LeadStage;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for creating a Stage master-data row — used by the
 * Master > Stages page and by the Lead create/edit forms' "+ Add Stage"
 * quick-add modal alike, so any other CRM module that needs to create a
 * Stage on the fly should call this instead of hand-rolling the same
 * dedup/order_index logic again.
 */
class LeadStageService
{
    public function createStage(int $ownerId, string $name, ?string $color = null, bool $isClosed = false): LeadStage
    {
        $name = trim($name);

        if ($this->nameExists($ownerId, $name)) {
            throw ValidationException::withMessages([
                'name' => 'A stage with this name already exists.',
            ]);
        }

        $nextOrder = (int) LeadStage::forOwner($ownerId)->max('order_index') + 1;

        return LeadStage::create([
            'portal_user_id' => $ownerId,
            'name' => $name,
            'color' => $color ?: '#4f46e5',
            'order_index' => $nextOrder,
            'is_closed' => $isClosed,
        ]);
    }

    public function nameExists(int $ownerId, string $name, ?int $ignoreId = null): bool
    {
        return LeadStage::forOwner($ownerId)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}

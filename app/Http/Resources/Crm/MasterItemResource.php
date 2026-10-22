<?php

namespace App\Http\Resources\Crm;

use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Services\Crm\OwnerContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Stage / Tag / Source in Master. `is_global` = one of MW Realty's shared items: usable on
 * leads, but `editable` only for Super Admin.
 */
class MasterItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStage = $this->resource instanceof LeadStage;
        $hasOrder = $isStage || $this->resource instanceof LeadSource;
        $isGlobal = $this->isGlobal();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'order_index' => $this->when($hasOrder, fn () => (int) $this->order_index),
            'is_closed' => $this->when($isStage, fn () => (bool) $this->is_closed),
            'is_default' => $this->when($isStage, fn () => (bool) $this->is_default),
            'leads_count' => (int) ($this->leads_count ?? 0),
            'is_global' => $isGlobal,
            'editable' => !$isGlobal || app(OwnerContext::class)->isAdmin(),
        ];
    }
}

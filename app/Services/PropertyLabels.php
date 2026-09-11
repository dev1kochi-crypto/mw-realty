<?php

namespace App\Services;

use App\Models\Filter;

class PropertyLabels
{
    private ?array $labels = null;
    public function values(): array
    {
        if ($this->labels === null) {
            $this->labels = [];
            foreach (Filter::with('values')->whereIn('key', Filter::SELECT_KEYS)->get() as $filter) {
                $this->labels[$filter->key] = $filter->values->keyBy('value')->all();
            }
        }
        return $this->labels;
    }
}

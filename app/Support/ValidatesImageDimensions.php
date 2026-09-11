<?php

namespace App\Support;

use Illuminate\Http\Request;

trait ValidatesImageDimensions
{
    use \CMS\SiteManager\Support\ValidatesImageDimensions {
        validateImageWithinLimits as private validateDimensions;
    }

    protected function validateImageWithinLimits(Request $request, string $field, array $config, string $label): void
    {
        $request->validate([$field => 'nullable|image|max:'.($config['max_size'] ?? 4096)]);
        $this->validateDimensions($request, $field, $config, $label);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\Brand;
use Illuminate\Routing\Controller;

/**
 * Public read-only endpoint the frontend's partner-logo strip uses — the
 * list comes straight from the admin-managed Brands screen, so adding,
 * reordering, or disabling a brand there shows up here without a code change.
 */
class PublicBrandController extends Controller
{
    public function index()
    {
        return Brand::active()
            ->orderBy('order_index')
            ->get(['id', 'image', 'image_alt'])
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'image_url' => asset('storage/'.$brand->image),
                'alt' => $brand->image_alt,
            ]);
    }
}

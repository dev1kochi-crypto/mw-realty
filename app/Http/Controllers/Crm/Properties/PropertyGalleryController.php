<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Services\PropertyGallery;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Properties — Gallery
 *
 * A listing's saved photos — `{reference_no}-{n}.jpeg`, shown in `image_sequence` order. New photos
 * are uploaded with the listing form (images[]).
 */
class PropertyGalleryController extends Controller
{
    use ScopesListings;

    /**
     * Delete a photo
     *
     * `number` is the gallery position suffix from the filename ({reference_no}-{number}.jpeg).
     */
    public function destroy($id, $number, PropertyGallery $gallery)
    {
        $property = $this->findAccessible($id);
        $number = (int) $number;
        $numbers = $property->galleryNumbers();

        if (in_array($number, $numbers, true) && $property->image_path) {
            $gallery->delete($property->image_path, $property->reference_no . '-' . $number . '.jpeg');
        }
        $property->update(['image_sequence' => implode(',', array_values(array_diff($numbers, [$number])))]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete every photo
     *
     * Removes the whole per-listing image folder in one go.
     */
    public function destroyAll($id, PropertyGallery $gallery)
    {
        $property = $this->findAccessible($id);
        if ($property->image_path) {
            $gallery->deleteAll($property->image_path);
        }
        $property->update(['image_sequence' => null]);

        return response()->json(['success' => true]);
    }

    /**
     * Reorder photos
     *
     * Filenames never change — this rewrites the display order (the first photo is the featured one).
     *
     * @bodyParam order integer[] required Photo numbers in the new order. Example: [3, 1, 2]
     */
    public function reorder(Request $request, $id)
    {
        $property = $this->findAccessible($id);
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        $submitted = array_map('intval', $request->input('order'));
        $valid = array_values(array_intersect($submitted, $property->galleryNumbers()));
        $property->update(['image_sequence' => implode(',', $valid)]);

        return response()->json(['success' => true]);
    }
}

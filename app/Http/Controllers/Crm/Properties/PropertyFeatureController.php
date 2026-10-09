<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Services\FeaturedListingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Properties — Premium
 *
 * Make a listing premium for a start + end date (listing cards and the Featured menu). Agents /
 * companies spend their plan's quota within its max duration; Super Admin has no limits and may
 * leave the end date open. See FeaturedListingService.
 */
class PropertyFeatureController extends Controller
{
    use ScopesListings;

    public function __construct(private readonly FeaturedListingService $featured)
    {
    }

    /**
     * Make premium
     *
     * @bodyParam start_date string required Y-m-d. Example: 2026-10-10
     * @bodyParam end_date string Y-m-d — required except for Super Admin. Example: 2026-11-08
     */
    public function store(Request $request, $id)
    {
        $property = $this->findOwned($id);
        $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => ($this->isAdmin() ? 'nullable' : 'required') . '|date_format:Y-m-d',
        ]);

        if ($this->isAdmin()) {
            $this->featured->featureAsAdmin($property, $request->input('start_date'), $request->input('end_date'));
        } else {
            abort_unless($this->viewer()->isApproved(), 403);
            $this->featured->feature($this->viewer(), $property, $request->input('start_date'), $request->input('end_date'));
        }

        return response()->json(['success' => true]);
    }

    /**
     * Change premium dates
     *
     * A live feature keeps its start date (send null).
     *
     * @bodyParam start_date string Y-m-d. Example: 2026-10-10
     * @bodyParam end_date string Y-m-d. Example: 2026-11-08
     */
    public function update(Request $request, $id)
    {
        $property = $this->findOwned($id);
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => ($this->isAdmin() ? 'nullable' : 'required') . '|date_format:Y-m-d',
        ]);
        if (!$this->isAdmin()) {
            abort_unless($this->viewer()->isApproved(), 403);
        }

        $this->featured->reschedule($property, $this->isAdmin() ? null : $this->viewer(), $request->input('start_date'), $request->input('end_date'));

        return response()->json(['success' => true]);
    }

    /**
     * Stop / cancel premium
     */
    public function destroy($id)
    {
        $this->featured->stop($this->findOwned($id));

        return response()->json(['success' => true]);
    }
}

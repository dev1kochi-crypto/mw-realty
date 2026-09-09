<?php

namespace App\Http\Controllers;

use App\Models\PortalUser;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yajra\DataTables\Facades\DataTables;

/**
 * Super Admin moderation screen for Agent/Company self-service accounts.
 * Approve/reject controls who can log into the portal (/portal) and list properties.
 */
class PortalUserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = PortalUser::withCount(['properties', 'enquiries'])->with('plan')->latest();
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('type', fn ($row) => ucfirst($row->type))
                ->addColumn('display_name', function ($row) {
                    return $row->type === 'company'
                        ? ($row->company_name ?: $row->name)
                        : $row->name;
                })
                ->addColumn('plan', function ($row) {
                    $plans = Plan::active()->orderBy('order_index')->get();
                    $options = '<option value="">No plan</option>';
                    foreach ($plans as $plan) {
                        $selected = $row->plan_id === $plan->id ? 'selected' : '';
                        $options .= '<option value="' . $plan->id . '" ' . $selected . '>' . $plan->getTranslation('name') . '</option>';
                    }
                    return '<select class="form-select form-select-sm plan-select" data-id="' . $row->id . '" style="width: 140px;">' . $options . '</select>';
                })
                ->addColumn('status', function ($row) {
                    $map = [
                        'pending' => 'bg-warning text-dark',
                        'approved' => 'bg-success',
                        'rejected' => 'bg-danger',
                    ];
                    return '<span class="badge ' . ($map[$row->status] ?? 'bg-secondary') . '">' . ucfirst($row->status) . '</span>';
                })
                ->addColumn('action', function ($row) {
                    $btns = '<div class="btn-group">';
                    if (auth('cms')->user()->can('portal-accounts.edit')) {
                        if ($row->status !== 'approved') {
                            $btns .= '<button type="button" class="btn btn-sm btn-outline-success approve-item" data-id="' . $row->id . '" title="Approve"><i class="fas fa-check"></i></button>';
                        }
                        if ($row->status !== 'rejected') {
                            $btns .= '<button type="button" class="btn btn-sm btn-outline-danger reject-item" data-id="' . $row->id . '" title="Reject"><i class="fas fa-ban"></i></button>';
                        }
                    }
                    if (auth('cms')->user()->can('portal-accounts.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-secondary delete-item" data-id="' . $row->id . '" title="Delete"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['plan', 'status', 'action'])
                ->make(true);
        }

        return view('portal-accounts.index');
    }

    public function approve($id)
    {
        $portalUser = PortalUser::findOrFail($id);
        $portalUser->status = 'approved';
        $portalUser->save();

        return response()->json(['success' => true]);
    }

    public function reject($id)
    {
        $portalUser = PortalUser::findOrFail($id);
        $portalUser->status = 'rejected';
        $portalUser->save();

        return response()->json(['success' => true]);
    }

    public function assignPlan(Request $request, $id)
    {
        $request->validate([
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        $portalUser = PortalUser::findOrFail($id);
        $portalUser->plan_id = $request->input('plan_id') ?: null;
        $portalUser->save();

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        PortalUser::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}

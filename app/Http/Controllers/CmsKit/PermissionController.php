<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $permissions = Permission::where('guard_name', 'cms')->get();
            return \Yajra\DataTables\Facades\DataTables::of($permissions)
                ->addColumn('module_badge', function ($row) {
                $module = explode('.', $row->name)[0] ?? 'general';
                return '<span class="badge bg-light text-dark border">' . ucfirst($module) . '</span>';
            })
                ->addColumn('name_code', function ($row) {
                return '<code class="text-primary fw-bold">' . $row->name . '</code>';
            })
                ->addColumn('created_at_fmt', function ($row) {
                return $row->created_at ? $row->created_at->format('M d, Y') : 'N/A';
            })
                ->addColumn('actions', function ($row) {
                $editBtn = '';
                $deleteBtn = '';
                if (auth()->guard('cms')->user()->can('permissions.edit')) {
                    $editBtn = '<button class="btn btn-sm btn-light border me-1 edit-permission" data-id="' . $row->id . '" data-name="' . $row->name . '"><i class="fas fa-edit text-primary"></i></button>';
                }
                if (auth()->guard('cms')->user()->can('permissions.delete')) {
                    $deleteBtn = '<form action="' . route('cms.permissions.destroy', $row->id) . '" method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this permission?\')">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-sm btn-light border text-danger"><i class="fas fa-trash"></i></button></form>';
                }
                return '<div class="text-end">' . $editBtn . $deleteBtn . '</div>';
            })
                ->rawColumns(['module_badge', 'name_code', 'actions'])
                ->make(true);
        }
        $permissions = Permission::where('guard_name', 'cms')->get();
        return view('cms-kit::permissions.index', compact('permissions'));
    }

    public function store(Request $request)
    {
        $tableName = config('permission.table_names.permissions', 'permissions');
        $request->validate([
            'name' => 'required|string|unique:' . $tableName . ',name',
        ]);

        Permission::create(['name' => $request->name, 'guard_name' => 'cms']);

        return back()->with('success', 'Permission created successfully.');
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);
        $tableName = config('permission.table_names.permissions', 'permissions');
        $request->validate([
            'name' => 'required|string|unique:' . $tableName . ',name,' . $id,
        ]);

        $permission->name = $request->name;
        $permission->save();

        return back()->with('success', 'Permission updated successfully.');
    }

    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        // Safeguard: Check if any roles are using this permission
        $rolesCount = \Spatie\Permission\Models\Role::where('guard_name', 'cms')
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('name', $permission->name)->where('guard_name', 'cms');
            })->count();
            
        if ($rolesCount > 0) {
            return back()->with('error', "Cannot delete. This permission is currently assigned to {$rolesCount} role(s).");
        }

        $permission->delete();
        return back()->with('success', 'Permission deleted successfully.');
    }
}



<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $roles = Role::where('guard_name', 'cms')->get();
            return \Yajra\DataTables\Facades\DataTables::of($roles)
                ->addColumn('permissions_count', function ($row) {
                return $row->name === 'superadmin' ? 'All' : $row->permissions->count();
            })
                ->addColumn('created_at_fmt', function ($row) {
                return $row->created_at->format('M d, Y');
            })
                ->addColumn('actions', function ($row) {
                $editBtn = '';
                $deleteBtn = '';
                if (auth()->guard('cms')->user()->can('roles.edit')) {
                    $editBtn = '<a href="' . route('cms.roles.edit', $row->id) . '" class="btn btn-sm btn-light border me-1" title="Edit"><i class="fas fa-key text-warning"></i></a>';
                    if ($row->name !== 'superadmin' && auth()->guard('cms')->user()->can('roles.delete')) {
                        $deleteBtn = '<form action="' . route('cms.roles.destroy', $row->id) . '" method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this role?\')">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-sm btn-light border text-danger"><i class="fas fa-trash"></i></button></form>';
                    }
                }
                return '<div class="text-end">' . $editBtn . $deleteBtn . '</div>';
            })
                ->rawColumns(['actions'])
                ->make(true);
        }
        $roles = Role::where('guard_name', 'cms')->get();
        return view('cms-kit::roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::where('guard_name', 'cms')->get()->groupBy(function ($item) {
            $parts = explode('.', $item->name);
            return count($parts) > 1 ? $parts[0] : 'other';
        });
        $role = null;
        $rolePermissions = [];

        return view('cms-kit::roles.edit', compact('permissions', 'role', 'rolePermissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:' . config('permission.table_names.roles', 'roles') . ',name',
            'permissions' => 'array'
        ]);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'cms']);
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('cms.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $permissions = Permission::where('guard_name', 'cms')->get()->groupBy(function ($item) {
            // Group by module if available, fallback to 'other'
            $parts = explode('.', $item->name);
            return count($parts) > 1 ? $parts[0] : 'other';
        });
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('cms-kit::roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $request->validate([
            'name' => 'required|unique:' . config('permission.table_names.roles', 'roles') . ',name,' . $id,
            'permissions' => 'array'
        ]);

        $role->name = $request->name;
        $role->save();

        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('cms.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        if ($role->name === 'superadmin') {
            return back()->with('error', 'Super Admin role cannot be deleted.');
        }

        // Safeguard: Check if any users are assigned to this role using DB facade for safety
        $usersCount = \Illuminate\Support\Facades\DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $id)
            ->count();
            
        if ($usersCount > 0) {
            return back()->with('error', 'Cannot delete. This role is currently assigned to one or more users.');
        }

        $role->delete();
        return redirect()->route('cms.roles.index')->with('success', 'Role deleted successfully.');
    }

    public function storePermission(Request $request)
    {
        $tableName = config('permission.table_names.permissions', 'permissions');
        $request->validate([
            'name' => 'required|string|unique:' . $tableName . ',name',
        ]);

        Permission::create(['name' => $request->name, 'guard_name' => 'cms']);

        return back()->with('success', 'Permission created successfully.');
    }
}



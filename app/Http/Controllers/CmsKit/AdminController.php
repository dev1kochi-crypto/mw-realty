<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\CmsKit\Admin;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $admins = Admin::with('roles')->get();
            return \Yajra\DataTables\Facades\DataTables::of($admins)
                ->addColumn('name_info', function($row) {
                    $initial = strtoupper(substr($row->name, 0, 1));
                    return '<div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem; font-weight: bold;">'.$initial.'</div>
                                <span class="fw-bold">'.$row->name.'</span>
                            </div>';
                })
                ->addColumn('roles_list', function($row) {
                    $html = '';
                    foreach($row->roles as $role) {
                        $html .= '<span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-1 me-1" style="background-color: rgba(var(--primary-rgb), 0.1);">'.ucfirst($role->name).'</span>';
                    }
                    return $html;
                })
                ->addColumn('status_toggle', function($row) {
                    $badgeClass = $row->is_active ? 'bg-success' : 'bg-secondary';
                    $statusText = $row->is_active ? 'Active' : 'Inactive';
                    $disabled = $row->isProtected() ? 'disabled' : '';
                    return '<form action="'.route('cms.admins.toggle-status', $row->id).'" method="POST">
                                '.csrf_field().'
                                <button type="submit" class="border-0 bg-transparent p-0" '.$disabled.'>
                                    <span class="badge '.$badgeClass.'" style="cursor: pointer;">'.$statusText.'</span>
                                </button>
                            </form>';
                })
                ->addColumn('actions', function($row) {
                    $editBtn = '';
                    $deleteBtn = '';
                    if (auth()->guard('cms')->user()->can('users.edit')) {
                        $editBtn = '<a href="'.route('cms.admins.edit', $row->id).'" class="btn btn-sm btn-light border me-1" title="Edit"><i class="fas fa-edit text-primary"></i></a>';
                        if (!$row->isProtected()) {
                            $deleteBtn = '<form action="'.route('cms.admins.destroy', $row->id).'" method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this admin?\')">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-sm btn-light border text-danger"><i class="fas fa-trash"></i></button></form>';
                        }
                    }
                    return '<div class="text-end">' . $editBtn . $deleteBtn . '</div>';
                })
                ->rawColumns(['name_info', 'roles_list', 'status_toggle', 'actions'])
                ->make(true);
        }
        $admins = Admin::with('roles')->get();
        return view('cms-kit::admins.index', compact('admins'));
    }

    public function create()
    {
        $roles = Role::where('guard_name', 'cms')->get();
        $permissions = \Spatie\Permission\Models\Permission::where('guard_name', 'cms')->get()->groupBy(function($item) {
            $parts = explode('.', $item->name);
            return count($parts) > 1 ? $parts[0] : 'other';
        });
        $admin = null;
        $adminRoles = [];
        $adminPermissions = [];

        return view('cms-kit::admins.edit', compact('roles', 'permissions', 'admin', 'adminRoles', 'adminPermissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:cms_admins,email',
            'password' => 'required|min:6|confirmed',
            'roles' => 'required|array'
        ]);

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true
        ]);

        $admin->syncRoles($request->roles);
        if ($request->has('permissions')) {
            $admin->syncPermissions($request->permissions);
        }

        return redirect()->route('cms.admins.index')->with('success', 'Admin created successfully.');
    }

    public function edit($id)
    {
        $admin = Admin::findOrFail($id);
        $roles = Role::where('guard_name', 'cms')->get();
        $permissions = \Spatie\Permission\Models\Permission::where('guard_name', 'cms')->get()->groupBy(function($item) {
            $parts = explode('.', $item->name);
            return count($parts) > 1 ? $parts[0] : 'other';
        });
        $adminRoles = $admin->roles->pluck('name')->toArray();
        $adminPermissions = $admin->permissions->pluck('name')->toArray();

        return view('cms-kit::admins.edit', compact('admin', 'roles', 'permissions', 'adminRoles', 'adminPermissions'));
    }

    public function update(Request $request, $id)
    {
        $admin = Admin::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:cms_admins,email,' . $id,
            'password' => 'nullable|min:6|confirmed',
            'roles' => 'required|array'
        ]);

        $admin->name = $request->name;
        $admin->email = $request->email;
        if ($request->password) {
            $admin->password = Hash::make($request->password);
        }
        $admin->save();

        $admin->syncRoles($request->roles);
        $admin->syncPermissions($request->permissions ?? []);

        return redirect()->route('cms.admins.index')->with('success', 'Admin updated successfully.');
    }

    public function destroy($id)
    {
        $admin = Admin::findOrFail($id);
        if ($admin->isProtected()) {
            return back()->with('error', 'Special protected Admin accounts cannot be deleted.');
        }
        $admin->delete();
        return redirect()->route('cms.admins.index')->with('success', 'Admin deleted successfully.');
    }

    public function toggleStatus($id)
    {
        $admin = Admin::findOrFail($id);
        
        if ($admin->isProtected()) {
            return back()->with('error', 'Protected Admin accounts cannot be deactivated.');
        }

        $admin->is_active = !$admin->is_active;
        $admin->save();

        return back()->with('success', 'Admin status updated.');
    }
}



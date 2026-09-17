<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Agent roster: Super Admin (cms guard) sees every agent across every agency;
 * a Company (portal guard) sees and manages only the agents under its own
 * company_id. An Agent-type portal login has no access here at all — agents
 * don't manage other agents.
 *
 * Agents added here don't get a working login yet — real credential sharing
 * is gated behind a future plan upgrade — so a random, never-communicated
 * password is set just to satisfy the column; nothing surfaces it to log in with.
 */
class AgentController extends Controller
{
    protected function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    protected function company(): ?PortalUser
    {
        $user = Auth::guard('portal')->user();

        return $user && $user->type === 'company' ? $user : null;
    }

    public function index()
    {
        abort_unless($this->isAdmin() || $this->company(), 403);

        $agents = PortalUser::where('type', 'agent')
            ->with('company')
            ->when(!$this->isAdmin(), fn ($q) => $q->where('company_id', $this->company()->id))
            ->latest()
            ->paginate(15);

        return view('portal.agents.index', [
            'agents' => $agents,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    public function create()
    {
        abort_unless($this->company(), 403);

        return view('portal.agents.create');
    }

    public function store(Request $request)
    {
        $company = $this->company();
        abort_unless($company, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:portal_users,email',
            'phone' => 'nullable|string|max:50',
            'whatsapp_number' => 'nullable|string|max:50',
            'years_of_experience' => 'nullable|integer|min:0|max:80',
            'preferred_areas' => 'nullable|array',
            'preferred_areas.*' => 'nullable|string|max:100',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:100',
        ]);

        PortalUser::create([
            'type' => 'agent',
            'company_id' => $company->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'years_of_experience' => $data['years_of_experience'] ?? null,
            'preferred_areas' => array_values(array_filter($data['preferred_areas'] ?? [], fn ($v) => trim((string) $v) !== '')),
            'features' => array_values(array_filter($data['features'] ?? [], fn ($v) => trim((string) $v) !== '')),
            'password' => Hash::make(Str::random(40)),
            'status' => 'approved',
            'status_changed_at' => now(),
            'is_active' => true,
        ]);

        return redirect()->route('portal.agents.index')->with('success', 'Agent added successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PortalUser;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Models\PlanUpgradeRequest;
use App\Mail\PortalAccountApproved;
use App\Mail\PortalAccountRejected;
use App\Mail\PortalDocumentFlaggedMail;
use App\Mail\PortalInfoRequestedMail;
use App\Notifications\PortalAccountApprovedNotification;
use App\Notifications\PortalAccountRejectedNotification;
use App\Notifications\PortalDocumentFlaggedNotification;
use App\Notifications\PortalInfoRequestedNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Yajra\DataTables\Facades\DataTables;

/**
 * Super Admin moderation screen for Agent/Company self-service accounts.
 * Approve/reject controls who can log into the portal (/portal) and list properties.
 */
class PortalUserController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type');

        if ($request->ajax()) {
            $data = PortalUser::withCount(['properties', 'leads', 'agents'])->with(['plan', 'company'])
                ->when(in_array($type, ['agent', 'company']), fn ($q) => $q->where('type', $type))
                ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
                ->when($request->filled('plan_id'), function ($q) use ($request) {
                    $request->query('plan_id') === 'none' ? $q->whereNull('plan_id') : $q->where('plan_id', $request->query('plan_id'));
                })
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->query('payment_status')))
                ->when($request->filled('payment_month'), function ($q) use ($request) {
                    [$year, $month] = array_pad(explode('-', $request->query('payment_month')), 2, null);
                    $q->whereYear('last_payment_at', $year)->whereMonth('last_payment_at', $month);
                })
                ->when($request->filled('joined_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('joined_from')))
                ->when($request->filled('joined_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('joined_to')))
                ->when($request->filled('pending_days'), fn ($q) => $q->where('status', 'pending')
                    ->where('status_changed_at', '<=', now()->subDays((int) $request->query('pending_days'))))
                ->when($request->filled('has_properties'), fn ($q) => $request->query('has_properties') === '1' ? $q->has('properties') : $q->doesntHave('properties'))
                ->when($request->filled('has_leads'), fn ($q) => $request->query('has_leads') === '1' ? $q->has('leads') : $q->doesntHave('leads'))
                ->latest();

            $plans = Plan::active()->orderBy('order_index')->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('type', fn ($row) => ucfirst($row->type))
                ->addColumn('contact', function ($row) {
                    $label = $row->type === 'company' ? ($row->company_name ?: $row->name) : $row->name;
                    $initials = strtoupper(collect(preg_split('/\s+/', trim($label)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
                    $fill = $row->type === 'company' ? 'fill-navy' : 'fill-teal';

                    return '<div class="d-flex align-items-center gap-2">'
                        . '<span class="contact-avatar ' . $fill . '">' . $initials . '</span>'
                        . '<div class="min-w-0">'
                        . '<a href="' . route('cms.portal-accounts.show', ['id' => $row->id, 'type' => $row->type]) . '" class="fw-semibold text-decoration-none d-block">' . e($label) . '</a>'
                        . '<div class="text-muted contact-subline">' . e($row->email) . '</div>'
                        . '<div class="text-muted contact-subline">' . e($row->phone ?: '-') . '</div>'
                        . '</div></div>';
                })
                ->filterColumn('contact', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%")
                            ->orWhere('company_name', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%")
                            ->orWhere('phone', 'like', "%{$keyword}%");
                    });
                })
                ->addColumn('company', function ($row) {
                    if ($row->type !== 'agent') {
                        return '<span class="text-muted">-</span>';
                    }
                    if (!$row->company) {
                        return '<span class="text-muted fst-italic">Unaffiliated</span>';
                    }
                    $label = $row->company->company_name ?: $row->company->name;
                    return '<a href="' . route('cms.portal-accounts.show', ['id' => $row->company->id, 'type' => 'company']) . '" class="text-decoration-none">' . e($label) . '</a>';
                })
                ->addColumn('agents_count', function ($row) {
                    if ($row->type !== 'company') {
                        return '<span class="text-muted">-</span>';
                    }
                    return '<a href="' . route('cms.portal-accounts.index', ['type' => 'agent', 'company_id' => $row->id]) . '" class="text-decoration-none fw-semibold">' . $row->agents_count . '</a>';
                })
                ->addColumn('joined', fn ($row) => $row->created_at->format('d M Y'))
                ->orderColumn('joined', 'created_at $1')
                ->addColumn('plan', function ($row) use ($plans) {
                    $label = $row->plan ? $row->plan->getTranslation('name') : 'No Plan';
                    $colorClass = !$row->plan ? 'text-muted fst-italic' : ((float) $row->plan->price === 0.0 ? 'text-secondary fw-semibold' : 'text-success fw-bold');

                    $options = '<option value="">No plan</option>';
                    foreach ($plans as $plan) {
                        $selected = $row->plan_id === $plan->id ? 'selected' : '';
                        $options .= '<option value="' . $plan->id . '" ' . $selected . '>' . e($plan->getTranslation('name')) . '</option>';
                    }

                    return '<div class="editable-cell" data-id="' . $row->id . '">'
                        . '<span class="cell-display"><span class="' . $colorClass . '">' . e($label) . '</span> '
                        . '<button type="button" class="btn btn-link btn-sm p-0 ms-1 edit-cell-btn" title="Change plan"><i class="fas fa-pen"></i></button></span>'
                        . '<span class="cell-edit d-none d-flex align-items-center gap-1">'
                        . '<select class="form-select form-select-sm plan-select-inline" style="width: 130px;">' . $options . '</select>'
                        . '<button type="button" class="btn btn-sm btn-success submit-plan-btn" title="Save"><i class="fas fa-check"></i></button>'
                        . '<button type="button" class="btn btn-sm btn-outline-secondary cancel-cell-btn" title="Cancel"><i class="fas fa-times"></i></button>'
                        . '</span></div>';
                })
                ->addColumn('status', function ($row) {
                    $textMap = ['pending' => 'text-warning', 'approved' => 'text-success', 'rejected' => 'text-danger'];
                    $iconMap = ['pending' => 'fa-clock', 'approved' => 'fa-check-circle', 'rejected' => 'fa-ban'];
                    $colorClass = $textMap[$row->status] ?? 'text-muted';
                    $icon = $iconMap[$row->status] ?? 'fa-question-circle';

                    $options = '';
                    foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $optLabel) {
                        $selected = $row->status === $value ? 'selected' : '';
                        $options .= '<option value="' . $value . '" ' . $selected . '>' . $optLabel . '</option>';
                    }

                    $pendingDays = $row->pendingSinceDays();
                    $pendingSub = $pendingDays !== null
                        ? '<div class="text-muted" style="font-size: 0.68rem;">' . ($pendingDays === 0 ? 'Pending today' : 'Pending ' . $pendingDays . 'd') . '</div>'
                        : '';

                    $html = '<div class="editable-cell" data-id="' . $row->id . '">'
                        . '<span class="cell-display"><span class="fw-bold ' . $colorClass . '"><i class="fas ' . $icon . ' me-1"></i>' . ucfirst($row->status) . '</span> ';
                    if (auth('cms')->user()->can('portal-accounts.edit')) {
                        $html .= '<button type="button" class="btn btn-link btn-sm p-0 ms-1 edit-cell-btn" title="Change status"><i class="fas fa-pen"></i></button>';
                    }
                    $html .= '</span>' . $pendingSub
                        . '<span class="cell-edit d-none d-flex align-items-center gap-1">'
                        . '<select class="form-select form-select-sm status-select-inline" style="width: 120px;">' . $options . '</select>'
                        . '<button type="button" class="btn btn-sm btn-success submit-status-btn" title="Save"><i class="fas fa-check"></i></button>'
                        . '<button type="button" class="btn btn-sm btn-outline-secondary cancel-cell-btn" title="Cancel"><i class="fas fa-times"></i></button>'
                        . '</span></div>';

                    return $html;
                })
                ->addColumn('payment_status', function ($row) {
                    $colorClass = $row->payment_status === 'paid' ? 'text-success' : 'text-danger';
                    $icon = $row->payment_status === 'paid' ? 'fa-check-circle' : 'fa-times-circle';
                    $sub = $row->last_payment_at ? '<div class="text-muted" style="font-size: 0.68rem;">' . $row->last_payment_at->format('d M Y') . '</div>' : '';

                    $options = '';
                    foreach (['unpaid' => 'Unpaid', 'paid' => 'Paid'] as $value => $optLabel) {
                        $selected = $row->payment_status === $value ? 'selected' : '';
                        $options .= '<option value="' . $value . '" ' . $selected . '>' . $optLabel . '</option>';
                    }

                    $html = '<div class="editable-cell" data-id="' . $row->id . '">'
                        . '<span class="cell-display"><span class="fw-bold ' . $colorClass . '"><i class="fas ' . $icon . ' me-1"></i>' . ucfirst($row->payment_status) . '</span> ';
                    if (auth('cms')->user()->can('portal-accounts.edit')) {
                        $html .= '<button type="button" class="btn btn-link btn-sm p-0 ms-1 edit-cell-btn" title="Change payment status"><i class="fas fa-pen"></i></button>';
                    }
                    $html .= '</span>' . $sub
                        . '<span class="cell-edit d-none d-flex align-items-center gap-1">'
                        . '<select class="form-select form-select-sm payment-select-inline" style="width: 110px;">' . $options . '</select>'
                        . '<button type="button" class="btn btn-sm btn-success submit-payment-btn" title="Save"><i class="fas fa-check"></i></button>'
                        . '<button type="button" class="btn btn-sm btn-outline-secondary cancel-cell-btn" title="Cancel"><i class="fas fa-times"></i></button>'
                        . '</span></div>';

                    return $html;
                })
                ->addColumn('is_active', function ($row) {
                    $checked = $row->is_active ? 'checked' : '';
                    $disabled = auth('cms')->user()->can('portal-accounts.edit') ? '' : 'disabled';
                    return '<div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input toggle-active" type="checkbox" data-id="' . $row->id . '" ' . $checked . ' ' . $disabled . '>
                            </div>';
                })
                ->addColumn('actions', function ($row) {
                    if (!auth('cms')->user()->can('portal-accounts.edit')) {
                        return '';
                    }
                    $label = $row->type === 'company' ? ($row->company_name ?: $row->name) : $row->name;
                    return '<button type="button" class="btn btn-sm btn-outline-secondary trigger-reset-password" data-id="' . $row->id . '" data-name="' . e($label) . '" title="Reset Password">'
                        . '<i class="fas fa-key"></i></button>';
                })
                ->rawColumns(['select_all', 'contact', 'company', 'agents_count', 'plan', 'status', 'payment_status', 'is_active', 'actions'])
                ->make(true);
        }

        $companiesForFilter = PortalUser::companies()->orderBy('company_name')->get(['id', 'company_name', 'name']);
        $plansForFilter = Plan::orderBy('order_index')->get();

        return view('portal-accounts.index', compact('type', 'companiesForFilter', 'plansForFilter'));
    }

    public function export(Request $request)
    {
        $type = $request->query('type');

        $query = PortalUser::with(['plan', 'company'])
            ->when(in_array($type, ['agent', 'company']), fn ($q) => $q->where('type', $type))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('plan_id'), function ($q) use ($request) {
                $request->query('plan_id') === 'none' ? $q->whereNull('plan_id') : $q->where('plan_id', $request->query('plan_id'));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->query('payment_status')))
            ->when($request->filled('payment_month'), function ($q) use ($request) {
                [$year, $month] = array_pad(explode('-', $request->query('payment_month')), 2, null);
                $q->whereYear('last_payment_at', $year)->whereMonth('last_payment_at', $month);
            })
            ->when($request->filled('joined_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('joined_from')))
            ->when($request->filled('joined_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('joined_to')))
            ->when($request->filled('has_properties'), fn ($q) => $request->query('has_properties') === '1' ? $q->has('properties') : $q->doesntHave('properties'))
            ->when($request->filled('has_leads'), fn ($q) => $request->query('has_leads') === '1' ? $q->has('leads') : $q->doesntHave('leads'))
            ->withCount(['properties', 'leads'])
            ->orderBy('id');

        $filename = 'agents-companies-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            \App\Support\Csv::row($out, ['Name', 'Type', 'Company', 'Email', 'Phone', 'Properties', 'Leads', 'Plan', 'Admin Approval', 'Active', 'Payment Status', 'Last Payment', 'Joined']);

            // Chunked (not get()) so exporting 50,000+ accounts never holds them all in memory at once.
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    \App\Support\Csv::row($out, [
                        $row->type === 'company' ? ($row->company_name ?: $row->name) : $row->name,
                        ucfirst($row->type),
                        $row->company ? ($row->company->company_name ?: $row->company->name) : '-',
                        $row->email,
                        $row->phone,
                        $row->properties_count,
                        $row->leads_count,
                        $row->plan ? $row->plan->getTranslation('name') : 'No Plan',
                        ucfirst($row->status),
                        $row->is_active ? 'Active' : 'Disabled',
                        ucfirst($row->payment_status),
                        $row->last_payment_at?->format('Y-m-d'),
                        $row->created_at->format('Y-m-d'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request)
    {
        $type = in_array($request->query('type'), ['agent', 'company']) ? $request->query('type') : null;
        $companies = PortalUser::companies()->orderBy('company_name')->get(['id', 'company_name', 'name', 'status']);
        return view('portal-accounts.create', compact('type', 'companies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => ['required', Rule::in(['company', 'agent'])],
            'name' => 'required|string|max:255',
            'company_name' => 'required_if:type,company|nullable|string|max:255',
            'email' => 'required|email|unique:portal_users,email',
            'phone' => 'nullable|string|max:50',
            'password' => ['required', Password::min(8)],
            'status' => ['required', Rule::in(['pending', 'approved'])],

            'nationality' => 'nullable|string|max:100',
            'emirates_id_no' => 'nullable|string|max:50',
            'emirates_id_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'passport_no' => 'nullable|string|max:50',
            'passport_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'passport_expiry' => 'nullable|date',

            'brn_number' => 'nullable|string|max:50',
            'rera_card_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'company_id' => ['nullable', Rule::exists('portal_users', 'id')->where('type', 'company')],
            'trade_license_no' => 'nullable|string|max:50',
            'trade_license_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'trade_license_expiry' => 'nullable|date',
            'orn_number' => 'nullable|string|max:50',
            'rera_certificate_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'office_address' => 'nullable|string|max:255',
            'trn_number' => 'nullable|string|max:50',
            'trn_expiry' => 'nullable|date',
            'authorized_signatory_name' => 'nullable|string|max:255',
            'landline' => 'nullable|string|max:50',
        ]);

        $type = $request->input('type');

        $portalUser = PortalUser::create([
            'type' => $type,
            'name' => $request->input('name'),
            'company_name' => $type === 'company' ? $request->input('company_name') : null,
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'status' => $request->input('status'),
            'kyc_review_status' => $request->input('status') === 'approved' ? 'approved' : 'submitted',
            'kyc_submitted_at' => $request->input('status') === 'approved' ? null : now(),
            'status_changed_at' => now(),
            'plan_id' => Plan::defaultFree()?->id,

            'nationality' => $request->input('nationality'),
            'emirates_id_no' => $request->input('emirates_id_no'),
            'passport_no' => $request->input('passport_no'),
            'passport_expiry' => $request->input('passport_expiry'),

            'brn_number' => $type === 'agent' ? $request->input('brn_number') : null,
            'company_id' => $type === 'agent' ? $request->input('company_id') : null,

            'trade_license_no' => $request->input('trade_license_no'),
            'trade_license_expiry' => $request->input('trade_license_expiry'),
            'orn_number' => $type === 'company' ? $request->input('orn_number') : null,
            'office_address' => $type === 'company' ? $request->input('office_address') : null,
            'trn_number' => $request->input('trn_number'),
            'trn_expiry' => $request->input('trn_expiry'),
            'authorized_signatory_name' => $type === 'company' ? $request->input('authorized_signatory_name') : null,
            'landline' => $type === 'company' ? $request->input('landline') : null,
        ]);

        $this->storeKycDocuments($request, $portalUser);

        if ($portalUser->status === 'approved') {
            $this->sendStatusEmail($portalUser, 'pending');
        }

        return redirect()->route('cms.portal-accounts.index', ['type' => $type])->with('success', 'Account created successfully.');
    }

    private function storeKycDocuments(Request $request, PortalUser $portalUser): void
    {
        $documentFields = [
            'emirates_id_document', 'passport_document', 'rera_card_document',
            'trade_license_document', 'rera_certificate_document',
        ];

        $updates = [];
        foreach ($documentFields as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $updates[$field] = app(\App\Services\ManagedFiles::class)->store($file, 'portal-kyc', 'kyc');
            }
        }

        if ($updates) {
            $portalUser->update($updates);
        }
    }

    public function show(Request $request, $id)
    {
        $portalUser = PortalUser::withCount(['properties', 'leads', 'agents'])->with(['plan', 'company'])->findOrFail($id);
        $companies = PortalUser::companies()->where('id', '!=', $id)->where('status', '!=', 'rejected')->orderBy('company_name')->get(['id', 'company_name', 'name']);

        // Paginated — a long-lived account can build up years of payment history, and a large
        // brokerage can have thousands of affiliated agents, so neither list loads unbounded.
        $payments = $portalUser->payments()->paginate(12, ['*'], 'payments_page')->withQueryString();
        $agents = $portalUser->agents()->orderBy('name')->paginate(10, ['*'], 'agents_page')->withQueryString();
        $totalPaid = (float) PlanPayment::paid()->where('portal_user_id', $portalUser->id)->sum('amount');
        $languages = \App\Models\CmsKit\Language::active()->orderByDesc('is_default')->get(['name', 'code', 'is_default']);

        return view('portal-accounts.show', compact('portalUser', 'companies', 'payments', 'agents', 'totalPaid', 'languages'));
    }

    /**
     * Partial update from the profile page's per-section inline editors
     * (Identity, RERA Broker Details, Company / RERA Office Details).
     */
    public function update(Request $request, $id)
    {
        $portalUser = PortalUser::findOrFail($id);

        $request->validate([
            'section' => ['required', Rule::in(['identity', 'agent', 'company', 'about'])],
            'name' => 'sometimes|required|string|max:255',
            'company_name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('portal_users', 'email')->ignore($portalUser->id)],
            'phone' => 'sometimes|nullable|string|max:50',
            'nationality' => 'sometimes|nullable|string|max:100',
            'emirates_id_no' => 'sometimes|nullable|string|max:50',
            'passport_no' => 'sometimes|nullable|string|max:50',
            'passport_expiry' => 'sometimes|nullable|date',
            'brn_number' => 'sometimes|nullable|string|max:50',
            'company_id' => ['sometimes', 'nullable', Rule::notIn([$portalUser->id]), Rule::exists('portal_users', 'id')->where('type', 'company')->where('status', 'approved')->where('is_active', true)],
            'trade_license_no' => 'sometimes|nullable|string|max:50',
            'trade_license_expiry' => 'sometimes|nullable|date',
            'orn_number' => 'sometimes|nullable|string|max:50',
            'trn_number' => 'sometimes|nullable|string|max:50',
            'trn_expiry' => 'sometimes|nullable|date',
            'authorized_signatory_name' => 'sometimes|nullable|string|max:255',
            'landline' => 'sometimes|nullable|string|max:50',
            'office_address' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|array',
            'bio.*' => 'nullable|string|max:2000',
            'years_of_experience' => 'sometimes|nullable|integer|min:0|max:80',
            'preferred_areas' => 'sometimes|nullable|string|max:500',
            'website' => 'sometimes|nullable|url|max:255',
            'founding_year' => 'sometimes|nullable|integer|min:1900|max:' . now()->year,
            'badges' => 'sometimes|array',
            'badges.*' => 'string|max:60',
        ]);

        if (in_array($request->input('section'), ['agent', 'company'], true)) {
            abort_unless($request->input('section') === $portalUser->type, 422, 'This section does not apply to this account.');
        }
        $fieldsBySection = [
            'identity' => ['name', 'company_name', 'email', 'phone', 'nationality', 'emirates_id_no', 'passport_no', 'passport_expiry'],
            'agent' => ['brn_number', 'company_id', 'trade_license_no', 'trade_license_expiry', 'trn_number', 'trn_expiry'],
            'company' => ['trade_license_no', 'trade_license_expiry', 'orn_number', 'trn_number', 'trn_expiry', 'authorized_signatory_name', 'landline', 'office_address'],
            'about' => ['years_of_experience', 'website', 'founding_year'],
        ];

        if ($request->input('section') === 'about') {
            $portalUser->fill($request->only($fieldsBySection['about']));
            $portalUser->preferred_areas = $request->filled('preferred_areas')
                ? array_values(array_filter(array_map('trim', explode(',', $request->input('preferred_areas')))))
                : [];
            $portalUser->badges = $request->input('badges', []);

            if ($request->has('bio')) {
                // Admin edits every language directly, one textarea per active site language —
                // unlike the client's own form, which only ever writes the site's default locale.
                $translations = $portalUser->translations ?? [];
                foreach ($request->input('bio', []) as $locale => $text) {
                    $translations['bio'][$locale] = $text ?? '';
                }
                $portalUser->translations = $translations;
            }

            $portalUser->save();
        } else {
            $portalUser->update($request->only($fieldsBySection[$request->input('section')]));
        }

        return response()->json(['success' => true]);
    }

    /**
     * Re-run auto-translation for one language of the "about" bio — lets admin refresh a stale
     * machine translation after the source bio changed, without touching other languages.
     */
    public function retranslateBio(Request $request, $id)
    {
        $request->validate(['locale' => 'required|string|max:10']);

        $portalUser = PortalUser::findOrFail($id);
        $defaultLocale = \App\Models\CmsKit\Language::active()->where('is_default', true)->value('code') ?? config('app.fallback_locale');
        $sourceText = $portalUser->getTranslation('bio', $defaultLocale);

        if (!$sourceText) {
            return response()->json(['success' => false, 'message' => 'No source bio to translate from yet.'], 422);
        }

        $translated = app(\App\Services\AutoTranslator::class)->translate($sourceText, $request->input('locale'), $defaultLocale);
        if ($translated === null) {
            return response()->json(['success' => false, 'message' => 'Translation failed — check the Google Translate API key is configured.'], 422);
        }

        $translations = $portalUser->translations ?? [];
        $translations['bio'][$request->input('locale')] = $translated;
        $portalUser->translations = $translations;
        $portalUser->save();

        return response()->json(['success' => true, 'text' => $translated]);
    }

    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $portalUser = PortalUser::findOrFail($id);
        $portalUser->password = Hash::make($request->input('password'));
        $portalUser->remember_token = \Illuminate\Support\Str::random(60);
        $portalUser->save();

        return response()->json(['success' => true]);
    }

    public function approve($id)
    {
        $portalUser = PortalUser::findOrFail($id);
        if ($portalUser->kyc_review_status !== 'submitted' || !$portalUser->kyc_user_submitted_at) {
            return response()->json(['success' => false, 'message' => 'This account has not submitted its KYC for review.'], 422);
        }
        $previousStatus = $portalUser->status;
        $portalUser->status = 'approved';
        $portalUser->status_changed_at = now();
        $portalUser->rejection_reason = null;
        $portalUser->kyc_review_status = 'approved';
        $portalUser->kyc_review_note = null;
        $portalUser->save();

        $this->sendStatusEmail($portalUser, $previousStatus);

        return response()->json(['success' => true]);
    }

    public function reject(Request $request, $id)
    {
        $portalUser = PortalUser::findOrFail($id);
        $previousStatus = $portalUser->status;
        $portalUser->status = 'rejected';
        $portalUser->status_changed_at = now();
        $portalUser->rejection_reason = $request->input('reason');
        $portalUser->kyc_review_status = 'changes_requested';
        $portalUser->kyc_review_note = $request->input('reason');
        $portalUser->save();

        $this->sendStatusEmail($portalUser, $previousStatus);

        return response()->json(['success' => true]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'reason' => 'nullable|string|max:500',
        ]);

        $portalUser = PortalUser::findOrFail($id);
        $previousStatus = $portalUser->status;
        if ($request->input('status') === 'approved'
            && ($portalUser->kyc_review_status !== 'submitted' || !$portalUser->kyc_user_submitted_at)) {
            return response()->json(['success' => false, 'message' => 'This account has not submitted its KYC for review.'], 422);
        }
        $portalUser->status = $request->input('status');
        $portalUser->status_changed_at = now();
        $portalUser->rejection_reason = $portalUser->status === 'rejected' ? $request->input('reason') : null;
        $portalUser->kyc_review_status = match ($portalUser->status) {
            'approved' => 'approved',
            'rejected' => 'changes_requested',
            default => $portalUser->kyc_review_status === 'approved' ? 'submitted' : $portalUser->kyc_review_status,
        };
        if ($portalUser->status === 'approved') {
            $portalUser->kyc_review_note = null;
        } elseif ($portalUser->status === 'rejected') {
            $portalUser->kyc_review_note = $request->input('reason');
        }
        $portalUser->save();

        $this->sendStatusEmail($portalUser, $previousStatus);

        return response()->json(['success' => true]);
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:portal_users,id',
        ]);

        $portalUsers = PortalUser::whereIn('id', $request->input('ids'))
            ->where('status', '!=', 'approved')
            ->where('kyc_review_status', 'submitted')
            ->whereNotNull('kyc_user_submitted_at')
            ->get();
        foreach ($portalUsers as $portalUser) {
            $previousStatus = $portalUser->status;
            $portalUser->status = 'approved';
            $portalUser->status_changed_at = now();
            $portalUser->rejection_reason = null;
            $portalUser->kyc_review_status = 'approved';
            $portalUser->kyc_review_note = null;
            $portalUser->save();

            $this->sendStatusEmail($portalUser, $previousStatus);
        }

        return response()->json(['success' => true, 'count' => $portalUsers->count()]);
    }

    /**
     * A failed notification email must never block an admin's approve/reject action —
     * log and move on. Only fires when the status actually changed, so re-saving the
     * same status (e.g. re-editing an already-approved account) doesn't spam an email.
     */
    private function sendStatusEmail(PortalUser $portalUser, string $previousStatus): void
    {
        if ($portalUser->status === $previousStatus) {
            return;
        }

        try {
            if ($portalUser->status === 'approved') {
                Mail::to($portalUser->email)->queue((new PortalAccountApproved($portalUser))->afterCommit());
            } elseif ($portalUser->status === 'rejected') {
                Mail::to($portalUser->email)->queue((new PortalAccountRejected($portalUser, $portalUser->rejection_reason))->afterCommit());
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send portal account status email: ' . $e->getMessage());
        }

        try {
            if ($portalUser->status === 'approved') {
                $portalUser->notify(new PortalAccountApprovedNotification($portalUser));
            } elseif ($portalUser->status === 'rejected') {
                $portalUser->notify(new PortalAccountRejectedNotification($portalUser, $portalUser->rejection_reason));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create portal account status bell notification: ' . $e->getMessage());
        }
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

    public function toggleActive($id)
    {
        $portalUser = PortalUser::findOrFail($id);
        $portalUser->is_active = !$portalUser->is_active;
        $portalUser->save();

        return response()->json(['success' => true, 'is_active' => $portalUser->is_active]);
    }

    private const DOCUMENT_FIELDS = [
        'emirates_id_document', 'passport_document', 'rera_card_document',
        'trade_license_document', 'rera_certificate_document',
    ];

    public function uploadDocument(Request $request, $id, $field)
    {
        abort_unless(in_array($field, self::DOCUMENT_FIELDS, true), 404);

        $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $portalUser = PortalUser::findOrFail($id);

        if ($portalUser->$field) {
            app(\App\Services\ManagedFiles::class)->delete($portalUser->$field, 'kyc');
        }

        $file = $request->file('document');
        $portalUser->$field = app(\App\Services\ManagedFiles::class)->store($file, 'portal-kyc', 'kyc');
        $statuses = $portalUser->document_status ?? [];
        unset($statuses[$field]);
        $portalUser->document_status = $statuses;
        $portalUser->save();

        return response()->json(['success' => true]);
    }

    public function removeDocument($id, $field)
    {
        abort_unless(in_array($field, self::DOCUMENT_FIELDS, true), 404);

        $portalUser = PortalUser::findOrFail($id);

        if ($portalUser->$field) {
            app(\App\Services\ManagedFiles::class)->delete($portalUser->$field, 'kyc');
            $portalUser->$field = null;

            $statuses = $portalUser->document_status ?? [];
            unset($statuses[$field]);
            $portalUser->document_status = $statuses;

            $portalUser->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Per-document verification, independent of the account's overall status — lets
     * an admin flag "this specific document is blurry" instead of one blanket rejection.
     */
    public function updateDocumentStatus(Request $request, $id, $field)
    {
        abort_unless(in_array($field, self::DOCUMENT_FIELDS, true), 404);

        $request->validate([
            'status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
            'note' => 'nullable|string|max:255',
        ]);

        $portalUser = PortalUser::findOrFail($id);

        $statuses = $portalUser->document_status ?? [];
        $statuses[$field] = ['status' => $request->input('status'), 'note' => $request->input('note')];
        $portalUser->document_status = $statuses;
        $portalUser->save();

        // Only a rejection requires the user to actually do something — verified/pending
        // verdicts don't need to interrupt them with a notification.
        if ($request->input('status') === 'rejected') {
            $note = $request->input('note');
            $portalUser->forceFill([
                'kyc_review_status' => 'changes_requested',
                'kyc_review_note' => trim(PortalUser::documentLabel($field) . ($note ? ': ' . $note : ' needs to be updated.')),
            ])->save();

            try {
                $portalUser->notify(new PortalDocumentFlaggedNotification($field));
            } catch (\Throwable $e) {
                Log::error('Failed to create document-flagged bell notification: ' . $e->getMessage());
            }

            try {
                Mail::to($portalUser->email)->queue((new PortalDocumentFlaggedMail($portalUser, $field, $note))->afterCommit());
            } catch (\Throwable $e) {
                Log::error('Failed to send document-flagged notification email: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * General-purpose "please also send us X" ask. It notifies the portal user and returns
     * an active KYC review to changes_requested so the user can update and resubmit.
     */
    public function requestInfo(Request $request, $id)
    {
        $portalUser = PortalUser::findOrFail($id);
        $requestOptions = [
            'identity' => 'Identity and contact details',
            'registration_details' => $portalUser->type === 'agent' ? 'BRN and brokerage details' : 'Company and office details',
            'tax_details' => 'Tax registration details (TRN)',
            'public_profile' => 'Public profile, bio, and service areas',
        ];
        $documentFields = $portalUser->type === 'agent'
            ? PortalUser::DOCUMENT_FIELDS
            : ['emirates_id_document', 'passport_document', 'trade_license_document', 'rera_certificate_document'];
        foreach ($documentFields as $field) {
            $requestOptions[$field] = PortalUser::documentLabel($field) . ' document';
        }

        $validated = $request->validate([
            'request_items' => ['required', 'array', 'min:1'],
            'request_items.*' => ['required', 'string', Rule::in(array_keys($requestOptions))],
            'message' => ['nullable', 'string', 'max:1500'],
        ]);

        $selectedItems = array_values(array_unique($validated['request_items']));
        $message = "Please update the following:\n" . implode("\n", array_map(
            fn (string $item) => '• ' . $requestOptions[$item],
            $selectedItems
        ));
        if (filled($validated['message'] ?? null)) {
            $message .= "\n\nAdditional instructions:\n" . trim($validated['message']);
        }

        if ($portalUser->status === 'approved') {
            return back()->with('error', 'Approved accounts cannot be returned to KYC review from this action.');
        }
        if ($portalUser->kyc_review_status === 'submitted' && !$portalUser->kyc_user_submitted_at) {
            return back()->with('error', 'The account must submit its KYC before you can request changes.');
        }
        if (!in_array($portalUser->kyc_review_status, ['submitted', 'changes_requested'], true)) {
            return back()->with('error', 'The account must submit its KYC before you can request changes.');
        }

        $portalUser->forceFill([
            'kyc_review_status' => 'changes_requested',
            'kyc_review_note' => $message,
        ])->save();

        try {
            $portalUser->notify(new PortalInfoRequestedNotification());
        } catch (\Throwable $e) {
            Log::error('Failed to create info-requested bell notification: ' . $e->getMessage());
        }

        try {
            Mail::to($portalUser->email)->queue((new PortalInfoRequestedMail($portalUser, $message))->afterCommit());
        } catch (\Throwable $e) {
            Log::error('Failed to send info-requested notification email: ' . $e->getMessage());
        }

        return redirect()->route('cms.portal-accounts.show', ['id' => $portalUser->id, 'type' => $portalUser->type])
            ->with('success', 'Your request has been sent to ' . $portalUser->displayName() . '.');
    }

    public function updatePaymentStatus(Request $request, $id)
    {
        $request->validate([
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
        ]);

        app(\App\Services\PaymentRecorder::class)->record((int) $id, $request->input('payment_status'));

        return response()->json(['success' => true]);
    }

    public function planUpgradeRequests(Request $request)
    {
        $requests = PlanUpgradeRequest::with(['portalUser', 'plan', 'currentPlan'])
            ->pending()
            ->latest('requested_at')
            ->paginate(15)
            ->withQueryString();

        return view('portal-accounts.plan-upgrade-requests', compact('requests'));
    }

    public function approvePlanUpgradeRequest($id)
    {
        $upgradeRequest = PlanUpgradeRequest::with(['portalUser', 'plan'])->pending()->findOrFail($id);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($upgradeRequest) {
                // Re-checks the coupon (expired / used up since the request?) before applying it.
                app(\App\Services\CouponService::class)->redeem($upgradeRequest);
                $upgradeRequest->portalUser->update(['plan_id' => $upgradeRequest->plan_id, 'billing_interval' => $upgradeRequest->billing_interval ?: 'monthly']);
                $upgradeRequest->update([
                    'status' => 'approved',
                    'decided_at' => now(),
                    'decided_by' => auth('cms')->id(),
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon ' . $upgradeRequest->coupon_code . ' can no longer be applied: ' . collect($e->errors())->flatten()->first()
                    . ' Reject this request so the account can resubmit without it.',
            ], 422);
        }

        return response()->json(['success' => true]);
    }

    public function rejectPlanUpgradeRequest(Request $request, $id)
    {
        $request->validate(['decision_note' => 'nullable|string|max:500']);

        $upgradeRequest = PlanUpgradeRequest::pending()->findOrFail($id);
        $upgradeRequest->update([
            'status' => 'rejected',
            'decided_at' => now(),
            'decided_by' => auth('cms')->id(),
            'decision_note' => $request->input('decision_note'),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $portalUser = PortalUser::with('properties.images')->findOrFail($id);
        $this->deleteWithConnectedData($portalUser);

        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:portal_users,id',
        ]);

        $portalUsers = PortalUser::with('properties.images')->whereIn('id', $request->input('ids'))->get();
        foreach ($portalUsers as $portalUser) {
            $this->deleteWithConnectedData($portalUser);
        }

        return response()->json(['success' => true, 'count' => $portalUsers->count()]);
    }

    /**
     * Deleting an agent/company also removes everything that belongs to them —
     * listings (with their images/details) and CRM leads — rather than leaving orphaned rows.
     */
    private function deleteWithConnectedData(PortalUser $portalUser): void
    {
        foreach ($portalUser->properties as $property) {
            if ($property->image) {
                app(\App\Services\ManagedFiles::class)->delete($property->image);
            }
            if ($property->image_path) {
                app(\App\Services\PropertyGallery::class)->deleteAll($property->image_path);
            }
            foreach ($property->images as $image) {
                app(\App\Services\ManagedFiles::class)->delete($image->image);
            }
            $property->delete();
        }

        $portalUser->leads()->delete();

        foreach (['avatar', 'emirates_id_document', 'passport_document', 'rera_card_document', 'trade_license_document', 'rera_certificate_document'] as $field) {
            if ($portalUser->$field) {
                app(\App\Services\ManagedFiles::class)->delete($portalUser->$field, $field === 'avatar' ? 'public' : 'kyc');
            }
        }

        $portalUser->delete();
    }
}

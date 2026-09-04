<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\CareerCandidate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yajra\DataTables\Facades\DataTables;

class CareerCandidateController extends Controller
{
    protected function configuredColumns(): array
    {
        return config('cms-kit.database.careers.candidates.columns', [
            'name' => true,
            'email' => true,
            'phone' => true,
            'state' => true,
            'country' => true,
            'apply_for' => true,
            'experience' => true,
            'designation' => true,
            'additional_information' => true,
            'attachment' => true,
            'privacy' => true,
            'submitted_at' => true,
        ]);
    }

    protected function listColumns(): array
    {
        $columns = $this->configuredColumns();

        foreach (['state', 'additional_information', 'attachment', 'privacy'] as $field) {
            if (array_key_exists($field, $columns)) {
                $columns[$field] = false;
            }
        }

        return $columns;
    }

    protected function visibleColumns(): array
    {
        return collect($this->listColumns())
            ->filter(fn ($show) => (bool) $show)
            ->keys()
            ->values()
            ->all();
    }

    protected function applyFilters(Request $request)
    {
        $columns = $this->configuredColumns();

        return CareerCandidate::query()
            ->when(($columns['apply_for'] ?? true) && $request->filled('apply_for') && $request->apply_for !== 'All', fn ($query) => $query->where('apply_for', $request->apply_for))
            ->when(($columns['state'] ?? true) && $request->filled('state') && $request->state !== 'All', fn ($query) => $query->where('state', $request->state))
            ->when(($columns['country'] ?? true) && $request->filled('country') && $request->country !== 'All', fn ($query) => $query->where('country', $request->country))
            ->when(($columns['submitted_at'] ?? true) && $request->filled('from_date'), fn ($query) => $query->whereDate('submitted_at', '>=', Carbon::parse($request->from_date)))
            ->when(($columns['submitted_at'] ?? true) && $request->filled('to_date'), fn ($query) => $query->whereDate('submitted_at', '<=', Carbon::parse($request->to_date)));
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $dataTable = DataTables::of($this->applyFilters($request)->latest('submitted_at'))
                ->addIndexColumn()
                ->addColumn('select_all', fn ($row) => '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">')
                ->editColumn('submitted_at', fn ($row) => optional($row->submitted_at)->format('d M Y H:i') ?: '-')
                ->editColumn('privacy', fn ($row) => $row->privacy ? 'Yes' : 'No');

            foreach (config('cms-kit.database.careers.candidates.extra_fields', []) as $key => $field) {
                $dataTable->addColumn($key, function ($row) use ($key) {
                    return $row->extra_fields[$key] ?? '-';
                });
            }

            return $dataTable->addColumn('action', function ($row) {
                    $buttons = '<div class="btn-group">';
                    if (auth('cms')->user()?->can('careers.show')) {
                        $buttons .= '<a href="' . route('cms.careers.candidates.show', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> View</a>';
                    }

                    if (auth('cms')->user()?->can('careers.delete')) {
                        $buttons .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }

                    return $buttons . '</div>';
                })
                ->rawColumns(['select_all', 'action'])
                ->make(true);
        }

        $columns = $this->listColumns();
        $applyForOptions = ($columns['apply_for'] ?? true)
            ? CareerCandidate::query()->select('apply_for')->distinct()->pluck('apply_for')->filter()->values()
            : collect();
        $stateOptions = ($columns['state'] ?? true)
            ? CareerCandidate::query()->select('state')->distinct()->pluck('state')->filter()->values()
            : collect();
        $countryOptions = ($columns['country'] ?? true)
            ? CareerCandidate::query()->select('country')->distinct()->pluck('country')->filter()->values()
            : collect();
        $hasData = CareerCandidate::exists();
        $detailColumns = $this->visibleColumns();

        return view('cms-kit::careers.candidates.index', compact('applyForOptions', 'stateOptions', 'countryOptions', 'hasData', 'columns', 'detailColumns'));
    }

    public function show($id)
    {
        $candidate = CareerCandidate::findOrFail($id);
        $detailColumns = [
            'name',
            'email',
            'phone',
            'country',
            'apply_for',
            'experience',
            'designation',
            'submitted_at',
            'state',
            'additional_information',
            'attachment',
            'privacy',
        ];

        return view('cms-kit::careers.candidates.show', compact('candidate', 'detailColumns'));
    }

    public function export(Request $request)
    {
        $candidates = $this->applyFilters($request)->latest('submitted_at')->get();
        $filename = 'career_candidates_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($candidates) {
            $file = fopen('php://output', 'w');
            $visibleColumns = $this->visibleColumns();
            $extraFieldLabels = collect(config('cms-kit.database.careers.candidates.extra_fields', []))
                ->map(fn ($field, $key) => $field['label'] ?? ucfirst(str_replace('_', ' ', $key)))
                ->values()
                ->all();

            $baseHeaderMap = [
                'name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone',
                'state' => 'State',
                'country' => 'Country',
                'apply_for' => 'Apply For',
                'experience' => 'Experience',
                'designation' => 'Designation',
                'submitted_at' => 'Submitted',
                'additional_information' => 'Additional Information',
                'attachment' => 'Attachment',
                'privacy' => 'Privacy',
            ];

            fputcsv($file, array_merge(
                ['ID'],
                collect($visibleColumns)->map(fn ($key) => $baseHeaderMap[$key] ?? ucfirst(str_replace('_', ' ', $key)))->all(),
                $extraFieldLabels
            ));

            foreach ($candidates as $candidate) {
                $rowMap = [
                    'name' => $candidate->name,
                    'email' => $candidate->email,
                    'phone' => $candidate->phone,
                    'state' => $candidate->state,
                    'country' => $candidate->country,
                    'apply_for' => $candidate->apply_for,
                    'experience' => $candidate->experience,
                    'designation' => $candidate->designation,
                    'submitted_at' => optional($candidate->submitted_at)->format('Y-m-d H:i:s'),
                    'additional_information' => $candidate->additional_information,
                    'attachment' => $candidate->attachment ? asset('storage/' . $candidate->attachment) : '',
                    'privacy' => $candidate->privacy ? 'Yes' : 'No',
                ];

                $row = array_merge(
                    [$candidate->id],
                    collect($visibleColumns)->map(fn ($key) => $rowMap[$key] ?? '')->all()
                );

                foreach (array_keys(config('cms-kit.database.careers.candidates.extra_fields', [])) as $key) {
                    $row[] = $candidate->extra_fields[$key] ?? '';
                }

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function destroy($id)
    {
        CareerCandidate::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));

        if ($request->input('action') === 'delete' && !empty($ids)) {
            CareerCandidate::whereIn('id', $ids)->delete();
        }

        return response()->json(['success' => true]);
    }
}

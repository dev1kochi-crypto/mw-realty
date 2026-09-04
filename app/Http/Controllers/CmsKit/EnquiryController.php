<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Enquiry;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;
use Carbon\Carbon;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Enquiry::query();

            // Filters
            if ($request->filled('page_source') && $request->page_source != 'All') {
                $query->where('page_source', $request->page_source);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->from_date));
            }

            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->to_date));
            }

            $dataTable = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
            })
                ->addColumn('date', function ($row) {
                return $row->created_at->format('d M Y H:i');
            });

            // Handle dynamic extra fields from config
            $extraFields = config('cms-kit.database.enquiries.extra_fields', []);
            foreach ($extraFields as $key => $field) {
                $dataTable->addColumn($key, function ($row) use ($key) {
                    // Check if it's a core field or in extra_fields JSON
                    return $row->{ $key} ?? ($row->extra_fields[$key] ?? '-');
                });
            }

            return $dataTable->addColumn('action', function ($row) {
                return '<div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary view-enquiry" data-id="' . $row->id . '"><i class="fas fa-eye"></i> View</button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>
                            </div>';
            })
                ->rawColumns(['select_all', 'action'])
                ->make(true);
        }

        $sources = Enquiry::select('page_source')->distinct()->pluck('page_source')->filter()->values();
        $hasData = Enquiry::exists();

        return view('cms-kit::enquiries.index', compact('sources', 'hasData'));
    }

    public function show($id)
    {
        $enquiry = Enquiry::findOrFail($id);
        return response()->json($enquiry);
    }

    public function export(Request $request)
    {
        $query = Enquiry::query();

        // Apply filters same as index
        if ($request->filled('page_source') && $request->page_source != 'All') {
            $query->where('page_source', $request->page_source);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->from_date));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->to_date));
        }

        $enquiries = $query->latest()->get();

        $filename = "enquiries_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['ID', 'Name', 'Email', 'Phone', 'Company', 'Country', 'Page Source', 'Page URL', 'Message', 'Date'];

        $callback = function () use ($enquiries, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($enquiries as $enquiry) {
                fputcsv($file, [
                    $enquiry->id,
                    $enquiry->name,
                    $enquiry->email,
                    $enquiry->phone,
                    $enquiry->company,
                    $enquiry->country,
                    $enquiry->page_source,
                    $enquiry->page_url,
                    $enquiry->message,
                    $enquiry->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function destroy($id)
    {
        $enquiry = Enquiry::findOrFail($id);
        $enquiry->delete();

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request)
    {
        $ids = $request->ids;
        $action = $request->action;

        if ($action === 'delete') {
            Enquiry::whereIn('id', $ids)->delete();
        }

        return response()->json(['success' => true]);
    }
}



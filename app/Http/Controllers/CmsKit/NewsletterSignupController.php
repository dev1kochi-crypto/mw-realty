<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\NewsletterSignup;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;

class NewsletterSignupController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = NewsletterSignup::latest();
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at->format('d M Y H:i');
                })
                ->addColumn('action', function ($row) {
                    if (auth('cms')->user()->can('newsletter.delete')) {
                        return '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    return '';
                })
                ->rawColumns(['select_all', 'action'])
                ->make(true);
        }

        return view('cms-kit::newsletter-signups.index');
    }

    public function destroy($id)
    {
        NewsletterSignup::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request)
    {
        if ($request->action === 'delete') {
            NewsletterSignup::whereIn('id', $request->ids)->delete();
        }
        return response()->json(['success' => true]);
    }
}



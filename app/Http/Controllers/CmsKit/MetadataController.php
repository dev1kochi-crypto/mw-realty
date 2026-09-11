<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\Metadata;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;


class MetadataController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $data = Metadata::query();
            return DataTables::eloquent($data)
                ->addColumn('page', function ($row) {
                $name = $row->getTranslation('page_name', 'en') ?: ucfirst($row->page_key);
                return '<strong>' . e($name) . '</strong><br><small class="text-muted">' . e($row->page_key) . '</small>';
            })
                ->addColumn('meta_title', function ($row) {
                $title = $row->getTranslation('meta_title', 'en');
                if (!$title)
                    return '<span class="text-muted">â€”</span>';

                $len = mb_strlen($title);
                $badgeClass = $len > 60 ? 'bg-danger' : 'bg-secondary';
                return '<div class="mb-1 text-truncate" style="max-width: 250px;">' . e($title) . '</div>' .
                    '<span class="badge ' . $badgeClass . '">' . $len . '/60</span>';
            })
                ->addColumn('meta_description', function ($row) {
                $desc = $row->getTranslation('meta_description', 'en');
                if (!$desc)
                    return '<span class="text-muted">â€”</span>';

                $len = mb_strlen($desc);
                $badgeClass = $len > 160 ? 'bg-danger' : 'bg-secondary';
                return '<div class="mb-1 text-truncate" style="max-width: 300px;">' . e($desc) . '</div>' .
                    '<span class="badge ' . $badgeClass . '">' . $len . '/160</span>';
            })
                ->addColumn('actions', function ($row) {
                return '<div class="text-end pe-3">
                                <a href="' . route('cms.metadata.edit', $row->id) . '" class="btn btn-outline-primary btn-sm">Edit</a>
                            </div>';
            })
                ->rawColumns(['page', 'meta_title', 'meta_description', 'actions'])
                ->make(true);
        }

        return view('cms-kit::metadata.index');
    }

    public function edit($id)
    {
        $metadata = Metadata::findOrFail($id);
        return view('cms-kit::metadata.edit', compact('metadata'));
    }

    public function update(Request $request, $id)
    {
        $metadata = Metadata::findOrFail($id);
        $requiredFields = config('cms-kit.database.metadata.required', []);

        $rules = [];
        foreach ($requiredFields as $field) {
            $rules["{$field}.en"] = 'required';
        }

        $rules['og_image'] = 'nullable|image|max:4096';
        $rules['canonical_url.*'] = 'nullable|url:http,https|max:2048';
        $rules['remove_og_image'] = 'nullable|boolean';

        $request->validate($rules);

        $data = $request->only([
            'canonical_url',
            'meta_title',
            'meta_description',
            'meta_keywords',
            'og_title',
            'og_description',
            'other_meta_tags'
        ]);

        if ($request->hasFile('og_image')) {
            // Delete old image if exists
            if ($metadata->og_image) {
                app(\App\Services\ManagedFiles::class)->delete($metadata->og_image);
            }
            $data['og_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('og_image'), 'metadata');
        } elseif ($request->boolean('remove_og_image') && $metadata->og_image) {
            app(\App\Services\ManagedFiles::class)->delete($metadata->og_image);
            $data['og_image'] = null;
        }

        $metadata->update($data);

        return redirect()->route('cms.metadata.index')->with('success', 'Metadata updated successfully.');
    }
}



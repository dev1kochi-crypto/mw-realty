<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Faq;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use CMS\SiteManager\Http\Requests\FaqRequest;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;
use CMS\SiteManager\Support\ManagesOrderIndex;

class FaqController extends Controller
{
    use ManagesOrderIndex;

    protected function mergeFaqTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.faqs.items.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    protected function mergeFaqSectionTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.faqs.section.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Faq::orderBy('order_index', 'asc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
            })
                ->addColumn('question', function ($row) {
                return $row->getTranslation('question');
            })
                ->addColumn('status', function ($row) {
                $checked = $row->status ? 'checked' : '';
                return '<div class="form-check form-switch">
                                <input class="form-check-input toggle-status" type="checkbox" data-id="' . $row->id . '" ' . $checked . '>
                            </div>';
            })
                ->addColumn('order', function ($row) {
                return '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 70px;">';
            })
                ->addColumn('action', function ($row) {
                return '<div class="btn-group">
                                <a href="' . route('cms.faqs.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>
                            </div>';
            })
                ->rawColumns(['select_all', 'status', 'order', 'action'])
                ->make(true);
        }
        
        $section = SectionLabel::firstOrCreate(['section_key' => 'faqs']);
        $languages = Language::active()->get();

        return view('cms-kit::faqs.index', compact('section', 'languages'));
    }

    public function updateSection(Request $request)
    {
        $languages = Language::active()->get();
        $section = SectionLabel::firstOrCreate(['section_key' => 'faqs']);
        $sectionConfig = config('cms-kit.database.faqs.section', []);
        $requiredFields = $sectionConfig['required'] ?? [];
        
        $rules = [];
        foreach ($languages as $lang) {
            if (in_array('title', $requiredFields)) {
                $rules["translations.{$lang->code}.title"] = 'required';
            }
            if (in_array('description', $requiredFields)) {
                $rules["translations.{$lang->code}.description"] = 'required';
            }
        }

        $request->validate($rules);

        $translations = [];
        foreach ($languages as $lang) {
            $transData = [];
            if ($sectionConfig['title'] ?? false) {
                $transData['title'] = $request->input("translations.{$lang->code}.title");
            }
            if ($sectionConfig['description'] ?? false) {
                $transData['description'] = $request->input("translations.{$lang->code}.description");
            }

            $translations[$lang->code] = $transData;
        }
        $translations = $this->mergeFaqSectionTranslatableExtraFields($translations);

        $data = [
            'translations' => $translations,
            'status' => $request->has('status'),
        ];

        if (($sectionConfig['section_image'] ?? false) && $request->hasFile('section_image')) {
            $data['section_image'] = $request->file('section_image')->store('faqs', 'public');
        }

        $extra_fields = [];
        foreach ($sectionConfig['extra_fields'] ?? [] as $key => $field) {
            $extra_fields[$key] = $request->input("extra_fields.{$key}");
        }
        $data['extra_fields'] = $extra_fields;

        $section->update($data);

        return redirect()->back()->with('success', 'Section settings updated.');
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $nextOrder = Faq::count() + 1;
        return view('cms-kit::faqs.create', compact('languages', 'nextOrder'));
    }

    public function store(FaqRequest $request)
    {
        $data = $request->validated();
        $data['translations'] = $this->mergeFaqTranslatableExtraFields($request->input('translations', []));
        $data['status'] = $request->has('status');
        $data['extra_fields'] = $request->input('extra_fields', []);

        $order = $this->resolveOrderForCreate(Faq::class, $request->order_index ? (int) $request->order_index : null);
        Faq::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        Faq::create($data);

        return redirect()->route('cms.faqs.index')->with('success', 'FAQ created successfully.');
    }

    public function edit($id)
    {
        $faq = Faq::findOrFail($id);
        $languages = Language::where('status', true)->get();
        return view('cms-kit::faqs.edit', compact('faq', 'languages'));
    }

    public function update(FaqRequest $request, $id)
    {
        $faq = Faq::findOrFail($id);
        $data = $request->validated();
        $data['translations'] = $this->mergeFaqTranslatableExtraFields($request->input('translations', []));
        $data['status'] = $request->has('status');
        $data['extra_fields'] = $request->input('extra_fields', []);

        $faq->update($data);

        return redirect()->route('cms.faqs.index')->with('success', 'FAQ updated successfully.');
    }

    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);
        $order = $faq->order_index;
        $faq->delete();

        Faq::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Faq::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->status = !$faq->status;
        $faq->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:faqs,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $faq = Faq::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Faq::class, (int) $request->order_index);
        $oldOrder = $faq->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                Faq::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                Faq::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }
            $faq->order_index = $newOrder;
            $faq->save();
        }
        $this->normalizeOrderIndex(Faq::class);

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request)
    {
        $ids = $request->ids;
        $action = $request->action;

        if ($action === 'delete') {
            Faq::whereIn('id', $ids)->delete();
            $this->normalizeOrderIndex(Faq::class);
        }
        elseif ($action === 'activate') {
            Faq::whereIn('id', $ids)->update(['status' => true]);
        }
        elseif ($action === 'deactivate') {
            Faq::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}



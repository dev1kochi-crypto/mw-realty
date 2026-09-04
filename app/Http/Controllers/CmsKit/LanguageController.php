<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\Language;
use CMS\SiteManager\Services\StaticTranslationService;
use CMS\SiteManager\Support\ValidatesImageDimensions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class LanguageController extends Controller
{
    use ValidatesImageDimensions;

    public function __construct(
        protected StaticTranslationService $staticTranslations
    ) {}

    protected function isEnglishLanguage(Language $language): bool
    {
        return strtolower((string) $language->code) === 'en';
    }

    protected function flagImageRules(): array
    {
        $maxKb = config('cms-kit.images.languages.flag.max_size', 256);

        return [
            'flag_image' => 'nullable|image|max:' . $maxKb,
            'flag_alt' => 'nullable|string|max:255',
        ];
    }

    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $languages = Language::all();
            return \Yajra\DataTables\Facades\DataTables::of($languages)
                ->addColumn('flag_thumb', function ($row) {
                    if ($row->flag_image) {
                        return '<img src="' . e(asset('storage/' . $row->flag_image)) . '" alt="' . e($row->flag_alt ?? '') . '" class="rounded border" style="height: 28px; width: auto;">';
                    }

                    return '<span class="text-muted">—</span>';
                })
                ->addColumn('status_badge', function ($row) {
                    $badgeClass = $row->status ? 'bg-success' : 'bg-secondary';
                    $btnClass = $row->status ? 'text-success' : 'text-secondary';
                    return '<form action="' . route('cms.languages.toggle-status', $row->id) . '" method="POST" style="display:inline;">
                                ' . csrf_field() . '
                                <button type="submit" class="btn btn-link ' . $btnClass . ' p-0"><i class="fas ' . ($row->status ? 'fa-check-circle' : 'fa-times-circle') . '"></i></button>
                            </form>';
                })
                ->addColumn('default_badge', function ($row) {
                    if ($this->isEnglishLanguage($row) || $row->is_default) {
                        return '<span class="badge bg-primary">Default</span>';
                    }

                    return '<span class="text-muted">-</span>';
                })
                ->addColumn('actions', function ($row) {
                    $flagUrl = $row->flag_image ? asset('storage/' . $row->flag_image) : '';
                    $langCode = strtolower((string) $row->code);
                    $vueTpl = config('cms-kit.static_translations.vue_editor_url');
                    $staticTextsUrl = is_string($vueTpl) && trim($vueTpl) !== ''
                        ? str_replace(['{code}', '{CODE}', '{id}'], [$langCode, strtoupper($langCode), (string) $row->id], trim($vueTpl))
                        : route('cms.languages.translations', $row->id);
                    $staticBtn = '<a href="' . e($staticTextsUrl) . '" class="btn btn-sm btn-outline-primary border me-1" title="Static site texts (' . e($langCode) . ')"><i class="fas fa-file-lines"></i></a>';
                    $editBtn = '<button class="btn btn-sm btn-light border me-1 edit-language" data-id="' . $row->id . '" data-name="' . e($row->name) . '" data-code="' . e($row->code) . '" data-flag-url="' . e($flagUrl) . '" data-flag-alt="' . e($row->flag_alt ?? '') . '"><i class="fas fa-edit text-primary"></i></button>';
                    $deleteBtn = '';
                    if (!$row->is_default && !$this->isEnglishLanguage($row)) {
                        $deleteBtn = '<form action="' . route('cms.languages.destroy', $row->id) . '" method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this language?\')">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-sm btn-light border text-danger"><i class="fas fa-trash"></i></button></form>';
                    }
                    return '<div class="text-end">' . $staticBtn . $editBtn . $deleteBtn . '</div>';
                })
                ->rawColumns(['flag_thumb', 'status_badge', 'default_badge', 'actions'])
                ->make(true);
        }
        return view('cms-kit::languages.index');
    }

    public function store(Request $request)
    {
        $flagConfig = config('cms-kit.images.languages.flag', []);
        $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s]+$/u'],
            'code' => ['required', 'string', 'max:10', 'unique:languages', 'regex:/^[\p{L}]+$/u'],
        ], $this->flagImageRules()), [
            'name.required' => 'Language name is required.',
            'name.regex' => 'Language name must contain only letters and spaces.',
            'code.required' => 'Language code is required.',
            'code.unique' => 'This language code already exists.',
            'code.regex' => 'Language code must contain only letters (no numbers or symbols).',
        ]);

        if ($request->hasFile('flag_image')) {
            $this->validateImageWithinLimits($request, 'flag_image', $flagConfig, 'Language flag');
        }

        $data = $request->only(['name', 'code', 'flag_alt']);
        if ($request->hasFile('flag_image')) {
            $data['flag_image'] = $request->file('flag_image')->store('languages/flags', 'public');
        }

        $language = Language::create($data);
        $this->staticTranslations->copyMasterToNewLanguage(strtolower((string) $language->code));

        return redirect()->back()->with('success', 'Language added.');
    }

    public function update(Request $request, $id)
    {
        $language = Language::findOrFail($id);
        $previousCode = strtolower((string) $language->code);
        $isEnglish = $this->isEnglishLanguage($language);

        $flagConfig = config('cms-kit.images.languages.flag', []);
        $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s]+$/u'],
            'code' => $isEnglish
                ? ['required', 'string', 'in:en']
                : ['required', 'string', 'max:10', 'unique:languages,code,' . $id, 'regex:/^[\p{L}]+$/u'],
        ], $this->flagImageRules()), [
            'name.required' => 'Language name is required.',
            'name.regex' => 'Language name must contain only letters and spaces.',
            'code.required' => 'Language code is required.',
            'code.unique' => 'This language code already exists.',
            'code.regex' => 'Language code must contain only letters (no numbers or symbols).',
            'code.in' => 'English language code must remain en.',
        ]);

        if ($request->hasFile('flag_image')) {
            $this->validateImageWithinLimits($request, 'flag_image', $flagConfig, 'Language flag');
        }

        $data = $request->only(['name', 'code', 'flag_alt']);
        if ($request->hasFile('flag_image')) {
            if ($language->flag_image) {
                Storage::disk('public')->delete($language->flag_image);
            }
            $data['flag_image'] = $request->file('flag_image')->store('languages/flags', 'public');
        }

        $language->update($data);
        if ($isEnglish && (!$language->is_default || !$language->status)) {
            $language->update([
                'is_default' => true,
                'status' => true,
            ]);
        }

        $language->refresh();
        $newCode = strtolower((string) $language->code);
        if (!$isEnglish && $newCode !== $previousCode) {
            $this->staticTranslations->renameLanguageFile($previousCode, $newCode);
        }

        return redirect()->back()->with('success', 'Language updated.');
    }

    public function toggleStatus($id)
    {
        $language = Language::findOrFail($id);

        if (($language->is_default || $this->isEnglishLanguage($language)) && $language->status) {
            return redirect()->back()->with('error', 'Cannot deactivate the default language.');
        }

        $language->update(['status' => !$language->status]);
        return redirect()->back()->with('success', 'Status updated.');
    }

    public function setDefault($id)
    {
        $language = Language::findOrFail($id);

        if (!$this->isEnglishLanguage($language)) {
            return redirect()->back()->with('error', 'English must remain the default language.');
        }

        Language::query()->update(['is_default' => false]);
        $language->update([
            'is_default' => true,
            'status' => true,
        ]);

        return redirect()->back()->with('success', 'English remains the default language.');
    }

    public function destroy($id)
    {
        $language = Language::findOrFail($id);
        if ($language->is_default || $this->isEnglishLanguage($language)) {
            return redirect()->back()->with('error', 'Cannot delete default language.');
        }
        if ($language->flag_image) {
            Storage::disk('public')->delete($language->flag_image);
        }
        $this->staticTranslations->deleteLanguageFile(strtolower((string) $language->code));
        $language->delete();
        return redirect()->back()->with('success', 'Language deleted.');
    }
}

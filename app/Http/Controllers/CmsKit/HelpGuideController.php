<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\HelpGuide;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Admin › Help Desk — the "!" quick-guide popup on each CRM / portal screen. The topic is the key
 * a screen asks for (CRM route meta `help`), so it is fixed once created; everything else is content.
 */
class HelpGuideController extends Controller
{
    public function index()
    {
        return view('cms-kit::help-desk.index', [
            'guides' => HelpGuide::orderBy('title')->get(),
        ]);
    }

    public function create()
    {
        return view('cms-kit::help-desk.create');
    }

    public function store(Request $request)
    {
        HelpGuide::create($this->validated($request));

        return redirect()->route('cms.help-desk.index')->with('success', 'Help guide created.');
    }

    public function edit($id)
    {
        return view('cms-kit::help-desk.edit', ['guide' => HelpGuide::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $guide = HelpGuide::findOrFail($id);
        $guide->update($this->validated($request, $guide));

        return redirect()->route('cms.help-desk.index')->with('success', 'Help guide updated.');
    }

    public function toggleStatus($id)
    {
        $guide = HelpGuide::findOrFail($id);
        $guide->update(['status' => !$guide->status]);

        return back()->with('success', "{$guide->title} guide " . ($guide->status ? 'activated.' : 'deactivated.'));
    }

    public function destroy($id)
    {
        $guide = HelpGuide::findOrFail($id);
        $guide->delete();

        return back()->with('success', "{$guide->title} guide deleted.");
    }

    private function validated(Request $request, ?HelpGuide $guide = null): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^fa-[a-z0-9-]+$/'],
            'intro' => 'nullable|string|max:1000',
            'features' => 'nullable|array',
            'features.*.icon' => ['nullable', 'string', 'max:60', 'regex:/^fa-[a-z0-9-]+$/'],
            'features.*.title' => 'nullable|string|max:255',
            'features.*.text' => 'nullable|string|max:1000',
            'steps' => 'nullable|array',
            'steps.*.title' => 'nullable|string|max:255',
            'steps.*.text' => 'nullable|string|max:1000',
            'tips' => 'nullable|array',
            'tips.*' => 'nullable|string|max:1000',
        ];
        if (!$guide) {
            $rules['topic'] = ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('help_guides', 'topic')];
        }

        $data = $request->validate($rules, [
            'topic.regex' => 'The topic may only contain lowercase letters, numbers and dashes (e.g. lead-insights).',
            'icon.regex' => 'The icon must be a Font Awesome class such as fa-address-book.',
            'features.*.icon.regex' => 'Feature icons must be Font Awesome classes such as fa-filter.',
        ]);

        // Blank repeater rows are dropped; a row needs a title to show.
        $data['features'] = collect($request->input('features', []))
            ->filter(fn ($f) => filled($f['title'] ?? null))
            ->map(fn ($f) => ['icon' => $f['icon'] ?: null, 'title' => $f['title'], 'text' => $f['text'] ?? ''])
            ->values()->all();
        $data['steps'] = collect($request->input('steps', []))
            ->filter(fn ($s) => filled($s['title'] ?? null))
            ->map(fn ($s) => ['title' => $s['title'], 'text' => $s['text'] ?? ''])
            ->values()->all();
        $data['tips'] = collect($request->input('tips', []))->filter(fn ($t) => filled($t))->values()->all();
        $data['status'] = $request->boolean('status');

        return $data;
    }
}

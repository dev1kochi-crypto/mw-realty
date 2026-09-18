<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Support\ValidatesImageDimensions;

class ConnectUsController extends Controller
{
    use ValidatesImageDimensions;

    public function index()
    {
        $section = SectionLabel::where('section_key', 'connect-us')->first();
        $languages = Language::where('status', true)->get();
        $videoMaxSize = config('cms-kit.images.connect-us.video.max_size', 10240);
        $posterConfig = config('cms-kit.images.connect-us.poster', []);
        return view('cms-kit::connect-us.index', compact('section', 'languages', 'videoMaxSize', 'posterConfig'));
    }

    public function update(Request $request)
    {
        $languages = Language::where('status', true)->get();
        $videoMaxSize = config('cms-kit.images.connect-us.video.max_size', 10240);
        $posterConfig = config('cms-kit.images.connect-us.poster', []);

        $rules = [
            'video_source' => ['nullable', Rule::in(['url', 'file'])],
            'video_url' => 'nullable|url',
            'video_file' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:' . $videoMaxSize,
            'remove_poster' => 'nullable|boolean',
        ];
        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title"] = 'required';
        }
        $request->validate($rules);
        $this->validateImageWithinLimits($request, 'poster', $posterConfig, 'Poster image');

        $section = SectionLabel::where('section_key', 'connect-us')->first();
        $existingExtra = $section?->extra_fields ?? [];

        $extraFields = [
            'button_text' => null, // kept translatable, not stored here
            'video_source' => $request->input('video_source'),
            'video_url' => $request->input('video_url'),
            'video_file' => $existingExtra['video_file'] ?? null,
        ];

        if ($request->hasFile('video_file')) {
            if (!empty($existingExtra['video_file'])) {
                app(\App\Services\ManagedFiles::class)->delete($existingExtra['video_file']);
            }
            $extraFields['video_file'] = app(\App\Services\ManagedFiles::class)->store($request->file('video_file'), 'connect-us/videos');
            $extraFields['video_url'] = null;
        } elseif ($extraFields['video_source'] === 'url') {
            if (!empty($existingExtra['video_file'])) {
                app(\App\Services\ManagedFiles::class)->delete($existingExtra['video_file']);
            }
            $extraFields['video_file'] = null;
        }

        unset($extraFields['button_text']);

        $data = [
            'translations' => $request->input('translations', []),
            'extra_fields' => $extraFields,
            'status' => $request->has('status'),
        ];

        if ($request->hasFile('poster')) {
            if ($section?->section_image) {
                app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            }
            $data['section_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('poster'), 'connect-us');
            $data['section_image_alt'] = $request->input('poster_alt');
        } elseif ($request->boolean('remove_poster') && $section?->section_image) {
            app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            $data['section_image'] = null;
            $data['section_image_alt'] = null;
        } elseif ($request->filled('poster_alt')) {
            $data['section_image_alt'] = $request->input('poster_alt');
        }

        SectionLabel::updateOrCreate(['section_key' => 'connect-us'], $data);

        return redirect()->route('cms.connect-us.index')->with('success', 'Connect Us updated successfully.');
    }
}

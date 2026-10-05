<?php

namespace App\Http\Controllers\Portal;

use App\Models\CmsKit\Language;
use App\Services\AutoTranslator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Auto-fill for the property form: the English (source) title / key features / description /
 * location are translated into the other site languages as the user types (Google Cloud
 * Translation — see AutoTranslator). The form only fills fields the user hasn't edited by hand.
 */
class PropertyTranslateController extends Controller
{
    /** Translatable property fields; description is rich text (TinyMCE HTML). */
    private const FIELDS = ['title', 'key_features', 'description', 'address', 'community', 'city', 'country'];
    private const HTML_FIELDS = ['description'];

    public function __invoke(Request $request, AutoTranslator $translator)
    {
        $codes = Language::active()->pluck('code')->all();
        $data = $request->validate([
            'source' => ['required', Rule::in($codes)],
            'targets' => 'required|array|min:1',
            'targets.*' => [Rule::in($codes), 'different:source'],
            'fields' => 'required|array',
            'fields.*' => 'nullable|string|max:20000',
        ]);

        if (!AutoTranslator::configured()) {
            return response()->json(['message' => 'Auto-translate isn\'t set up yet (GOOGLE_TRANSLATE_API_KEY). You can still type each language by hand.'], 503);
        }

        $fields = array_intersect_key($data['fields'], array_flip(self::FIELDS));
        $html = array_intersect_key($fields, array_flip(self::HTML_FIELDS));
        $text = array_diff_key($fields, $html);

        $result = [];
        foreach (array_unique($data['targets']) as $target) {
            $translatedText = $text ? $translator->translateMany($text, $target, $data['source'], 'text') : [];
            $translatedHtml = $html ? $translator->translateMany($html, $target, $data['source'], 'html') : [];
            if ($translatedText === null || $translatedHtml === null) {
                return response()->json(['message' => 'Translation failed — please try again, or type this language by hand.'], 502);
            }
            $result[$target] = $translatedText + $translatedHtml;
        }

        return response()->json(['translations' => $result]);
    }
}

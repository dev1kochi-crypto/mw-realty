<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\Language;
use CMS\SiteManager\Services\StaticTranslationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class LanguageStaticTextController extends Controller
{
    public function __construct(
        protected StaticTranslationService $staticTranslations
    ) {}

    public function index()
    {
        return redirect()->route('cms.languages.index');
    }

    /**
     * Legacy URL; redirects to /languages/{id}/translations.
     */
    public function edit(string $code)
    {
        $language = Language::query()->whereRaw('LOWER(code) = ?', [strtolower($code)])->firstOrFail();

        return redirect()->route('cms.languages.translations', $language);
    }

    public function translations(Language $language)
    {
        return $this->renderTranslationsEditor($language);
    }

    public function updateTranslations(Request $request, Language $language)
    {
        $code = strtolower((string) $language->code);
        $this->persistTranslations($request, $code);

        return $this->redirectAfterSave($language, $code);
    }

    /**
     * Legacy PUT /languages/static-texts/{code}.
     */
    public function update(Request $request, string $code)
    {
        $code = strtolower($code);
        $language = Language::query()->whereRaw('LOWER(code) = ?', [$code])->firstOrFail();
        $this->persistTranslations($request, $code);

        return $this->redirectAfterSave($language, $code);
    }

    protected function redirectAfterSave(Language $language, string $code): \Illuminate\Http\RedirectResponse
    {
        $masterCode = $this->staticTranslations->masterCode();
        if ($code === $masterCode) {
            return redirect()
                ->route('cms.languages.translations', $language)
                ->with('success', 'English (master) saved. Other language files were synchronized with any new keys.');
        }

        return redirect()
            ->route('cms.languages.translations', $language)
            ->with('success', 'Translations saved for ' . strtoupper($code) . '.');
    }

    protected function renderTranslationsEditor(Language $language): \Illuminate\Contracts\View\View
    {
        $code = strtolower((string) $language->code);
        $masterCode = $this->staticTranslations->masterCode();
        $isMaster = $code === $masterCode;

        $englishTree = $this->staticTranslations->readMaster();
        $englishFlat = $this->staticTranslations->flatten($englishTree);

        $data = $this->staticTranslations->read($code);
        $flat = $this->staticTranslations->flatten($data);
        if (is_array(old('entries'))) {
            $fromOld = [];
            foreach (old('entries') as $e) {
                if (!is_array($e)) {
                    continue;
                }
                $k = trim((string) ($e['key'] ?? ''));
                if ($k === '') {
                    continue;
                }
                $fromOld[$k] = (string) ($e['value'] ?? '');
            }
            if ($fromOld !== []) {
                $flat = $fromOld;
            }
        }
        ksort($flat);

        $jsonFilePath = str_replace('\\', '/', $this->staticTranslations->pathForCode($code));

        return view('cms-kit::languages.static-texts.edit', [
            'language' => $language,
            'flat' => $flat,
            'englishFlat' => $englishFlat,
            'isMaster' => $isMaster,
            'masterCode' => $masterCode,
            'jsonFilePath' => $jsonFilePath,
        ]);
    }

    protected function persistTranslations(Request $request, string $code): void
    {
        Language::query()->whereRaw('LOWER(code) = ?', [$code])->firstOrFail();

        $masterCode = $this->staticTranslations->masterCode();
        $entries = $request->input('entries', []);
        if (!is_array($entries)) {
            $entries = [];
        }

        $normalized = [];
        $seenKeys = [];
        foreach ($entries as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            if (isset($seenKeys[$key])) {
                throw ValidationException::withMessages(['entries' => ['Duplicate key in form: ' . $key]]);
            }
            $seenKeys[$key] = true;

            $val = $row['value'] ?? '';
            if (is_string($val)) {
                $normalized[$key] = $val;
            } elseif ($val === null) {
                $normalized[$key] = '';
            } elseif (is_scalar($val)) {
                $normalized[$key] = (string) $val;
            } else {
                $normalized[$key] = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
        }

        if ($code !== $masterCode) {
            $masterFlat = $this->staticTranslations->flatten($this->staticTranslations->readMaster());
            foreach ($normalized as $key => $val) {
                if ($this->isEnglishOnlyAltKey($key)) {
                    $normalized[$key] = $masterFlat[$key] ?? '';
                }
            }
        }

        if ($code === $masterCode) {
            $this->validateEnglishKeys($normalized);
            $this->assertMasterKeysMatchFile($normalized);
            $tree = $this->staticTranslations->unflatten($normalized);
            $this->staticTranslations->write($code, $tree);

            foreach (Language::query()->get() as $lang) {
                $other = strtolower((string) $lang->code);
                if ($other !== $masterCode) {
                    $this->staticTranslations->read($other);
                }
            }

            return;
        }

        $english = $this->staticTranslations->readMaster();
        $errors = $this->staticTranslations->validateNonEnglishMatchesEnglish($english, $normalized);
        if ($errors !== []) {
            throw ValidationException::withMessages(['entries' => $errors]);
        }

        $tree = $this->staticTranslations->unflatten($normalized);
        $tree = $this->staticTranslations->mergeWithMaster($english, $tree);
        $this->staticTranslations->write($code, $tree);
    }

    /**
     * Keys treated as English-only (accessibility ALT copy); values stay aligned with the master file for non-English locales.
     */
    protected function isEnglishOnlyAltKey(string $key): bool
    {
        return (bool) preg_match('/(^|\.)alt$/i', $key)
            || (bool) preg_match('/Alt$/', $key);
    }

    /**
     * @param  array<string, string>  $normalized
     */
    protected function validateEnglishKeys(array $normalized): void
    {
        $pattern = '/^[a-zA-Z0-9]([a-zA-Z0-9._-]*[a-zA-Z0-9])?$/';
        $errors = [];
        foreach (array_keys($normalized) as $key) {
            if (str_contains((string) $key, '..')) {
                $errors[] = 'Key cannot contain empty segments: ' . $key;

                continue;
            }
            if (!preg_match($pattern, (string) $key)) {
                $errors[] = 'Invalid key format: ' . $key;
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['entries' => $errors]);
        }
    }

    /**
     * Master saves may only update values for keys already present on disk (keys are maintained in development).
     *
     * @param  array<string, string>  $normalized
     */
    protected function assertMasterKeysMatchFile(array $normalized): void
    {
        $masterFlat = $this->staticTranslations->flatten($this->staticTranslations->readMaster());
        $onDisk = array_keys($masterFlat);
        $submitted = array_keys($normalized);
        sort($onDisk);
        sort($submitted);
        if ($onDisk === $submitted) {
            return;
        }
        throw ValidationException::withMessages([
            'entries' => [
                'Keys must match the master JSON file exactly. Add or remove keys in the codebase, reload this page, then edit values.',
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Mail\CareerApplicationReceived;
use App\Models\CmsKit\Career;
use App\Models\CmsKit\CareerCandidate;
use App\Models\CmsKit\SiteInformation;
use App\Rules\RecaptchaRule;
use App\Services\CareerPageService;
use App\Services\ManagedFiles;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

/**
 * Public endpoints for the /careers listing page, the /careers/{slug} vacancy page, and job applications.
 *
 * @group Careers
 */
class CareerController extends Controller
{
    public function __construct(private readonly CareerPageService $careerPage)
    {
    }

    /**
     * List vacancies
     *
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->careerPage->getListingData($lang, $request->only(CareerPageService::FILTERS)));
    }

    /**
     * Vacancy details
     *
     * @urlParam slug string required Example: senior-property-consultant
     * @queryParam lang string Example: en
     *
     * @response 404 {"message": "Not found"}
     */
    public function show(Request $request, string $slug)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->careerPage->getJobData($lang, $slug);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }

    /**
     * Apply for a job
     *
     * Send as `multipart/form-data` (the CV is a file). Leave out `career` for a general
     * application.
     *
     * Public, unauthenticated — the application form on both careers pages (POST /api/careers/apply).
     * Saves to Careers > Candidates and emails admin.
     *
     * @bodyParam career string A vacancy slug. Example: senior-property-consultant
     * @bodyParam attachment file required The CV: PDF, DOC or DOCX, max 5 MB.
     * @bodyParam privacy boolean required Must be accepted. Example: true
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"message": "Thanks for applying — our team will review your application and get back to you."}
     */
    public function apply(Request $request, ManagedFiles $files)
    {
        $request->validate([
            'career' => 'nullable|string|exists:careers,slug',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'country' => 'nullable|string|max:100',
            'experience' => 'nullable|string|max:50',
            'designation' => 'nullable|string|max:255',
            'additional_information' => 'nullable|string|max:2000',
            'attachment' => 'required|file|mimes:pdf,doc,docx|max:5120',
            'privacy' => 'accepted',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ], [
            'attachment.required' => 'Please attach your CV.',
            'attachment.mimes' => 'Your CV must be a PDF or Word document.',
            'attachment.max' => 'Your CV must be 5 MB or smaller.',
            'privacy.accepted' => 'Please accept the privacy policy to continue.',
        ]);

        $career = $request->filled('career')
            ? Career::active()->where('slug', $request->input('career'))->first()
            : null;

        $candidate = CareerCandidate::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'country' => $request->input('country'),
            'apply_for' => $career?->getTranslation('title', config('app.fallback_locale', 'en')) ?? 'General Application',
            'experience' => $request->input('experience'),
            'designation' => $request->input('designation'),
            'additional_information' => $request->input('additional_information'),
            'attachment' => $files->store($request->file('attachment'), 'careers/cv'),
            'privacy' => true,
            'submitted_at' => now(),
        ]);

        $adminEmail = SiteInformation::notificationEmail();
        if ($adminEmail) {
            Mail::to($adminEmail)->queue((new CareerApplicationReceived($candidate))->afterCommit());
        }

        return response()->json(['message' => "Thanks for applying — our team will review your application and get back to you."]);
    }
}

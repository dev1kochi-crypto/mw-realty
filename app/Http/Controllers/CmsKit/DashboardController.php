<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Routing\Controller;
use App\Models\CmsKit\Banner;
use App\Models\CmsKit\Faq;
use App\Models\CmsKit\Enquiry;
use App\Models\CmsKit\Testimonial;
use App\Models\CmsKit\Career;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'banners' => Banner::count(),
            'faqs' => Faq::count(),
            'enquiries' => Enquiry::count(),
            'testimonials' => Testimonial::count(),
            'careers' => class_exists(Career::class) ? Career::count() : 0,
        ];

        $recentEnquiries = Enquiry::latest()->take(5)->get();

        return view('cms-kit::dashboard', compact('stats', 'recentEnquiries'));
    }
}



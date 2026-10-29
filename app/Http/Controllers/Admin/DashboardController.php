<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\Enquiry;
use App\Models\Page;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'pages' => Page::count(),
            'publishedPages' => Page::published()->count(),
            'draftPages' => Page::where('status', Page::DRAFT)->count(),
            'scheduledPages' => Page::upcoming()->count(),
            'enquiries' => Enquiry::where('status', 'new')->count(),
            'upcomingEvents' => ContentItem::ofType('event')->published()->where('starts_at', '>=', now())->count(),
            'recentEnquiries' => Enquiry::where('status', '!=', Enquiry::SPAM)->latest()->limit(5)->get(),
        ]);
    }
}

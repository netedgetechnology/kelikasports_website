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
            'draftPages' => Page::where('status', 'draft')->count(),
            'enquiries' => Enquiry::where('status', 'new')->count(),
            'upcomingEvents' => ContentItem::ofType('event')->published()->where('starts_at', '>=', now())->count(),
            'recentEnquiries' => Enquiry::latest()->limit(5)->get(),
        ]);
    }
}

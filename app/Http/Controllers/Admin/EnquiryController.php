<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $query = Enquiry::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $term = $request->input('q');
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('message', 'like', '%'.$term.'%'));
        }

        $enquiries = $query->paginate(25)->withQueryString();

        return view('admin.enquiries.index', compact('enquiries'));
    }

    public function show(Enquiry $enquiry)
    {
        return view('admin.enquiries.show', compact('enquiry'));
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_progress', 'resolved'])],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $enquiry->update($data);

        return back()->with('success', 'Enquiry updated.');
    }

    public function export()
    {
        $rows = Enquiry::query()->latest()->get([
            'id', 'name', 'email', 'phone', 'category', 'message', 'status', 'source_page', 'created_at',
        ]);
        $filename = 'kelika-enquiries-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Category', 'Message', 'Status', 'Source Page', 'Created At']);

            foreach ($rows as $row) {
                $cells = $row->toArray();
                foreach ($cells as $key => $value) {
                    if (is_string($value) && preg_match('/^[=+@\-\t\r]/', $value)) {
                        $cells[$key] = "'".$value;
                    }
                }
                fputcsv($output, $cells);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

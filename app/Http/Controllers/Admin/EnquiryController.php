<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Support\EnquiryDuplicates;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $enquiries = Enquiry::query()->filtered($filters)->paginate(25)->withQueryString();
        $duplicates = EnquiryDuplicates::for($enquiries->items());
        $deletedCount = $request->user()->isAdministrator() ? Enquiry::onlyTrashed()->count() : 0;

        return view('admin.enquiries.index', compact('enquiries', 'filters', 'duplicates', 'deletedCount'));
    }

    /** Deleted enquiries, newest deletion first, for an administrator to review and restore. */
    public function deleted(Request $request)
    {
        $this->requireAdministrator('view deleted enquiries');
        $enquiries = Enquiry::onlyTrashed()->latest('deleted_at')->orderByDesc('id')->paginate(25);

        return view('admin.enquiries.deleted', compact('enquiries'));
    }

    public function show(Enquiry $enquiry)
    {
        $duplicates = EnquiryDuplicates::for([$enquiry])[$enquiry->id] ?? collect();

        return view('admin.enquiries.show', compact('enquiry', 'duplicates'));
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Enquiry::STATUSES))],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $enquiry->update($data);

        return back()->with('success', 'Enquiry updated.');
    }

    /** Moves an enquiry out of the lists and exports; its record is kept so it can be restored. */
    public function destroy(Enquiry $enquiry)
    {
        $this->requireAdministrator('delete enquiries');
        // Deleting and restoring leave updated_at alone, so the record reads exactly as before.
        $enquiry->timestamps = false;
        $enquiry->delete();

        return redirect()->route('admin.enquiries.index')->with('success', "The enquiry from {$enquiry->name} was deleted. An administrator can restore it from Deleted enquiries.");
    }

    public function restore(Enquiry $enquiry)
    {
        $this->requireAdministrator('restore enquiries');
        abort_unless($enquiry->trashed(), 404);
        $enquiry->timestamps = false;
        $enquiry->restore();

        return redirect()->route('admin.enquiries.show', $enquiry)->with('success', 'Enquiry restored.');
    }

    public function export(Request $request)
    {
        $query = Enquiry::query()->filtered($this->filters($request))->select([
            'id', 'name', 'email', 'phone', 'category', 'message', 'status', 'source_page', 'created_at',
        ]);
        $filename = 'kelika-enquiries-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Category', 'Message', 'Status', 'Source Page', 'Created At']);

            // Read in batches, so a large export never holds every enquiry in memory at once.
            foreach ($query->lazy(500) as $row) {
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

    /** @return array{q: ?string, status: ?string, from: ?string, to: ?string} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:190'],
            'status' => ['nullable', Rule::in(array_keys(Enquiry::STATUSES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], ['to.after_or_equal' => 'The end date must be on or after the start date.']);

        return ['q' => $data['q'] ?? null, 'status' => $data['status'] ?? null, 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null];
    }

    private function requireAdministrator(string $action): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403, "Only administrators can {$action}.");
    }
}

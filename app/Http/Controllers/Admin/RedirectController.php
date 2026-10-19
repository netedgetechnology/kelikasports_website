<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RedirectController extends Controller
{
    public function index()
    {
        $redirects = Redirect::query()->orderBy('from_path')->paginate(30);

        return view('admin.redirects.index', compact('redirects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:2048', 'regex:/^\/(?!\/)[a-zA-Z0-9\/_-]*$/', 'unique:redirects,from_path'],
            'to_path' => ['required', 'string', 'max:2048'],
            'status_code' => ['required', Rule::in([301, 302, 307, 308])],
        ]);

        $error = $this->destinationError($data['from_path'], $data['to_path']);
        if ($error) return back()->withErrors(['to_path' => $error])->withInput();

        Redirect::create($data + ['is_active' => true]);

        return back()->with('success', 'Redirect created.');
    }

    public function update(Request $request, Redirect $redirect)
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:2048', 'regex:/^\/(?!\/)[a-zA-Z0-9\/_-]*$/', Rule::unique('redirects', 'from_path')->ignore($redirect->id)],
            'to_path' => ['required', 'string', 'max:2048'],
            'status_code' => ['required', Rule::in([301, 302, 307, 308])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $error = $this->destinationError($data['from_path'], $data['to_path']);
        if ($error) return back()->withErrors(['to_path' => $error])->withInput();

        $data['is_active'] = $request->boolean('is_active');
        $redirect->update($data);

        return back()->with('success', 'Redirect updated.');
    }

    public function destroy(Redirect $redirect)
    {
        $redirect->delete();

        return back()->with('success', 'Redirect deleted.');
    }

    private function destinationError(string $from, string $to): ?string
    {
        if (! SafeUrl::isSafe($to)) return 'Use a relative path or a valid http/https URL.';
        if ($from === $to) return 'The source and destination cannot be identical.';

        return null;
    }
}

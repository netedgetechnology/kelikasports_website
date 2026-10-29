<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Redirect;
use App\Support\ReservedPaths;
use App\Support\SafeUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RedirectController extends Controller
{
    /** Redirect chains are followed this many hops when checking for loops. */
    private const MAX_HOPS = 20;

    public function index()
    {
        $redirects = Redirect::query()->orderBy('from_path')->paginate(30);

        return view('admin.redirects.index', compact('redirects'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $error = $this->destinationError($data['from_path'], $data['to_path']);
        if ($error) return back()->withErrors(['to_path' => $error])->withInput();

        Redirect::create($data + ['is_active' => true]);

        return back()->with('success', 'Redirect created.');
    }

    public function update(Request $request, Redirect $redirect)
    {
        $data = $this->validated($request, $redirect);

        $error = $this->destinationError($data['from_path'], $data['to_path'], $redirect);
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

    private function validated(Request $request, ?Redirect $redirect = null): array
    {
        // Sources are stored in the same canonical form the public site matches against.
        if (is_string($request->input('from_path'))) {
            $request->merge(['from_path' => Redirect::normalizePath($request->input('from_path'))]);
        }

        return $request->validate([
            'from_path' => [
                'required', 'string', 'max:255',
                // One or more path segments of lowercase letters, numbers, ".", "_", "~" and "-"; no segment starts with ".".
                'regex:/^(\/[a-z0-9_~-][a-z0-9._~-]*)+$/',
                Rule::unique('redirects', 'from_path')->ignore($redirect?->id),
                function (string $attribute, mixed $value, Closure $fail) {
                    $first = explode('/', trim((string) $value, '/'))[0];
                    if (ReservedPaths::isReserved($first)) {
                        $fail("Paths starting with /{$first} are handled by the website itself and cannot be redirected.");
                    } elseif (! str_contains(trim((string) $value, '/'), '/') && Page::query()->where('slug', $first)->exists()) {
                        $fail("The page at /{$first} would always be shown instead. Change or archive that page first.");
                    }
                },
            ],
            'to_path' => ['required', 'string', 'max:255'],
            'status_code' => ['required', Rule::in([301, 302, 307, 308])],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'from_path.regex' => 'Use a site path such as /old-page or /old/page.html: letters, numbers, ".", "_", "~" and "-" only.',
        ]);
    }

    /**
     * Rejects unsafe destinations and loops. Internal destinations are followed through existing active redirects
     * until they reach a live page, an external URL or a path with no redirect.
     */
    private function destinationError(string $from, string $to, ?Redirect $ignore = null): ?string
    {
        if (! SafeUrl::isSafe($to)) return 'Use a relative path or a valid http/https URL.';
        if (! str_starts_with($to, '/')) return null;

        $chain = [$from];
        $path = Redirect::normalizePath(parse_url($to, PHP_URL_PATH) ?: '/');
        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            if (in_array($path, $chain, true)) {
                return $hop === 0 ? 'The source and destination cannot be the same path.' : 'This redirect would create a loop: '.implode(' → ', [...$chain, $path]).'.';
            }
            $chain[] = $path;
            if ($path === '/' || Page::published()->where('slug', trim($path, '/'))->exists()) return null;

            $next = Redirect::query()->where('from_path', $path)->where('is_active', true)
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))->value('to_path');
            if ($next === null || ! str_starts_with($next, '/')) return null;
            $path = Redirect::normalizePath(parse_url($next, PHP_URL_PATH) ?: '/');
        }

        return 'This redirect would start a chain of more than '.self::MAX_HOPS.' redirects.';
    }
}

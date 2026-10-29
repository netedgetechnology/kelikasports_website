<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\Redirect;
use App\Support\MediaPicker;
use App\Support\ReservedPaths;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $pages = Page::with('creator')->orderBy('sort_order')->orderBy('title')->paginate(20);
        // Archiving and restoring pages is for administrators, so only they see the archive.
        $archived = $request->user()->isAdministrator() ? Page::onlyTrashed()->orderByDesc('deleted_at')->get() : collect();

        return view('admin.pages.index', compact('pages', 'archived'));
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(), 'isNew' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($data['status'] !== Page::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Create the page as a draft, add its sections and hero image, then publish or schedule it.']);
        }
        unset($data['published_at']);

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $page = Page::create($data);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page created. Add sections to build the page.');
    }

    public function edit(Page $page)
    {
        $page->load(['sections', 'revisions.user']);

        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->publication($page, $this->validated($request, $page));
        // Home and Contact keep their addresses: the site links to / and /contact directly, and they stay protected.
        if ($page->isCorePage() && $data['slug'] !== $page->slug) {
            throw ValidationException::withMessages(['slug' => $page->slug === 'home' ? 'The homepage slug is reserved and cannot be changed.' : "The {$page->title} page address /{$page->slug} is fixed and cannot be changed."]);
        }

        $oldSlug = $page->slug;
        $wasPublished = $page->isLive();
        $data['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($page, $data, $request, $oldSlug, $wasPublished) {
            PageRevision::create([
                'page_id' => $page->id,
                'user_id' => $request->user()->id,
                'revision_label' => 'Before update',
                'snapshot' => $page->revisionSnapshot(),
            ]);

            $page->update($data);

            if ($wasPublished && $page->isLive() && $oldSlug !== $page->slug) {
                $this->redirectOldPath($oldSlug, $page->slug);
            }
        });

        $message = match ($page->publicationState()) {
            Page::SCHEDULED => 'Page settings saved. The page is scheduled to go live on '.$page->published_at->format('d M Y \a\t H:i').' ('.config('app.timezone').').',
            default => 'Page settings saved.',
        };

        return redirect()->route('admin.pages.edit', $page)->with('success', $message);
    }

    /**
     * Applies the chosen status to the validated settings:
     * - Draft keeps the stored publication date (it no longer matters while the page is a draft);
     * - Scheduled needs a future date and time, given in the site's time zone;
     * - Published goes live now, or keeps its original date when the page is already live.
     * Scheduled and Published both need a publishable Hero image, and a live Home or Contact page must stay live.
     */
    private function publication(Page $page, array $data): array
    {
        $chosen = $data['status'];
        $date = filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at'], config('app.timezone')) : null;
        unset($data['published_at']);

        if ($page->isCorePage() && $page->isLive() && $chosen !== Page::PUBLISHED) {
            throw ValidationException::withMessages(['status' => "The {$page->title} page must stay published, so it cannot be changed to ".($chosen === Page::SCHEDULED ? 'scheduled' : 'a draft').'.']);
        }

        if ($chosen === Page::SCHEDULED) {
            // Saving an already scheduled page whose time has just come keeps it live instead of rejecting the date.
            if ($date && $page->status === Page::SCHEDULED && $page->published_at?->equalTo($date) && $date->isPast()) {
                return ['status' => Page::SCHEDULED] + $data;
            }
            if (! $date) throw ValidationException::withMessages(['published_at' => 'Choose the date and time this page should go live.']);
            if (! $date->isFuture()) throw ValidationException::withMessages(['published_at' => 'Choose a time in the future ('.config('app.timezone').' time), or choose Published to publish the page now.']);
            $this->assertPublishable($page);

            return ['status' => Page::SCHEDULED, 'published_at' => $date] + $data;
        }

        if ($chosen === Page::PUBLISHED) {
            if ($date && $date->isFuture()) throw ValidationException::withMessages(['published_at' => 'To publish this page later, choose Scheduled. Published pages go live straight away.']);
            if ($page->isLive()) return ['status' => Page::PUBLISHED] + $data;
            $this->assertPublishable($page);

            return ['status' => Page::PUBLISHED, 'published_at' => now()] + $data;
        }

        return $data;
    }

    /**
     * After a live page moves from /old to /new: send /old, and any redirect that already pointed at /old, straight
     * to /new, and switch off any redirect away from /new. Without that last step, moving a page A → B → A left
     * /A → /B and /B → /A in place: a loop.
     */
    private function redirectOldPath(string $oldSlug, string $newSlug): void
    {
        $old = '/'.$oldSlug;
        $new = $newSlug === 'home' ? '/' : '/'.$newSlug;

        Redirect::query()->where('from_path', $new)->update(['is_active' => false]);
        Redirect::query()->where('to_path', $old)->where('from_path', '!=', $new)->update(['to_path' => $new]);
        Redirect::query()->updateOrCreate(['from_path' => $old], ['to_path' => $new, 'status_code' => 301, 'is_active' => true]);
    }

    public function destroy(Page $page)
    {
        abort_unless(auth()->user()->isAdministrator(), 403, 'Only administrators can archive pages.');
        abort_if($page->isCorePage(), 422, 'Core pages cannot be deleted.');
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page moved to the archive. It can be restored from the archive list below.');
    }

    /** Brings an archived page back as a draft, so it only goes live again when someone publishes it. */
    public function restore(Page $page)
    {
        abort_unless(auth()->user()->isAdministrator(), 403, 'Only administrators can restore pages.');
        abort_unless($page->trashed(), 404);

        DB::transaction(function () use ($page) {
            $page->restore();
            $page->update(['status' => 'draft', 'updated_by' => auth()->id()]);
        });

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page restored as a draft. Review it, then publish it when it is ready.');
    }

    /** Publishes now: a draft, or a scheduled page that should not wait. A page that is already live keeps its date. */
    public function publish(Page $page)
    {
        $this->assertPublishable($page);
        DB::transaction(function () use ($page) {
            $this->recordRevision($page, 'Before publishing');
            $page->update([
                'status' => Page::PUBLISHED,
                'published_at' => $page->isLive() ? $page->published_at : now(),
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Page published.');
    }

    /** Takes a live page offline, or cancels a schedule, by making it a draft. Home and Contact always stay live. */
    public function unpublish(Page $page)
    {
        if ($page->isCorePage() && $page->isLive()) {
            return back()->withErrors(['status' => "The {$page->title} page must stay published, so it cannot be unpublished."]);
        }
        $wasScheduled = $page->publicationState() === Page::SCHEDULED;
        DB::transaction(function () use ($page, $wasScheduled) {
            $this->recordRevision($page, $wasScheduled ? 'Before cancelling the schedule' : 'Before unpublishing');
            $page->update(['status' => Page::DRAFT, 'updated_by' => auth()->id()]);
        });

        return back()->with('success', $wasScheduled ? 'Schedule cancelled. The page is a draft again.' : 'Page changed to draft and removed from public view.');
    }

    private function recordRevision(Page $page, string $label): void
    {
        PageRevision::create(['page_id' => $page->id, 'user_id' => auth()->id(), 'revision_label' => $label, 'snapshot' => $page->revisionSnapshot()]);
    }

    /**
     * Puts a page back to the state saved in one of its revisions. The current state is saved as a new revision first,
     * so a restore can itself be undone. Only what the revision captured is restored: the page settings it contains
     * (keys of Page::REVISION_FIELDS) and, if it holds them, its sections. Everything happens in one transaction, and
     * the same rules as a normal save apply (valid, unique slug; the homepage slug; a visible hero image when published).
     */
    public function restoreRevision(Request $request, Page $page, PageRevision $revision)
    {
        abort_unless($revision->page_id === $page->id, 404);

        $snapshot = is_array($revision->snapshot) ? $revision->snapshot : [];
        $fields = array_intersect_key(is_array($snapshot['page'] ?? null) ? $snapshot['page'] : [], array_flip(Page::REVISION_FIELDS));
        $sections = $snapshot['sections'] ?? null;
        $restoresSections = array_key_exists('sections', $snapshot);
        $version = 'the version from '.$revision->created_at->format('d M Y H:i');
        $refuse = fn (string $reason) => back()->withErrors(['revision' => ucfirst($version)." cannot be restored: {$reason} Nothing was changed."]);

        if ($fields === [] && ! $restoresSections) return $refuse('it holds no page settings or sections.');
        if ($restoresSections && ! self::validSectionSnapshot($sections)) return $refuse('its section data is incomplete or no longer valid.');
        $validator = Validator::make($fields, array_intersect_key($this->rules($page), $fields), self::MESSAGES);
        if ($validator->fails()) return $refuse($validator->errors()->first());
        if ($page->isCorePage() && isset($fields['slug']) && $fields['slug'] !== $page->slug) return $refuse($page->slug === 'home' ? 'the homepage address cannot change.' : "the {$page->title} page address /{$page->slug} cannot change.");

        $oldSlug = $page->slug;
        $wasPublished = $page->isLive();

        try {
            DB::transaction(function () use ($request, $page, $revision, $fields, $sections, $restoresSections, $oldSlug, $wasPublished) {
                PageRevision::create([
                    'page_id' => $page->id,
                    'user_id' => $request->user()->id,
                    'revision_label' => 'Before restoring the version from '.$revision->created_at->format('d M Y H:i'),
                    'snapshot' => $page->revisionSnapshot(),
                ]);

                $page->update($fields + ['updated_by' => $request->user()->id]);
                if ($restoresSections) $this->restoreSections($page, $sections);

                if ($page->isPublishedOrScheduled()) {
                    $this->assertPublishable($page);
                    if (! $page->published_at) $page->update(['published_at' => now()]);
                    if ($wasPublished && $page->isLive() && $oldSlug !== $page->slug) $this->redirectOldPath($oldSlug, $page->slug);
                }
                if ($wasPublished && $page->isCorePage() && ! $page->isLive()) {
                    throw ValidationException::withMessages(['status' => "the {$page->title} page must stay published."]);
                }
            });
        } catch (ValidationException $exception) {
            $page->refresh();

            return $refuse(collect($exception->errors())->flatten()->first());
        } catch (Throwable $exception) {
            report($exception);
            $page->refresh();

            return back()->withErrors(['revision' => 'The revision could not be restored, so the page was left exactly as it was. Please try again.']);
        }

        $notCaptured = array_diff(Page::REVISION_FIELDS, array_keys($fields));
        $message = 'Restored '.$version.'. The previous version was saved as a revision, so this can be undone.';
        if ($notCaptured) $message .= ' That revision did not record: '.implode(', ', str_replace('_', ' ', $notCaptured)).'; those settings were left as they were.';
        if (! $restoresSections) $message .= ' It did not record sections, so they were left as they were.';

        return redirect()->route('admin.pages.edit', $page)->with('success', $message);
    }

    /**
     * Makes the page's sections match a snapshot: sections that still exist are updated in place (same ID), sections
     * deleted since are recreated, and sections added since are removed. Only stored attributes are written.
     */
    private function restoreSections(Page $page, array $sections): void
    {
        $current = $page->sections()->get()->keyBy('id');
        $kept = [];
        foreach ($sections as $snapshot) {
            $attributes = array_intersect_key($snapshot, array_flip(['admin_label', 'sort_order', 'is_visible', 'data']));
            $existing = isset($snapshot['id']) ? $current->get($snapshot['id']) : null;
            if ($existing && $existing->type === $snapshot['type']) {
                $existing->update($attributes);
                $kept[] = $existing->id;
            } else {
                $kept[] = $page->sections()->create(['type' => $snapshot['type']] + $attributes)->id;
            }
        }
        $page->sections()->whereNotIn('id', $kept)->get()->each->delete();
    }

    private static function validSectionSnapshot(mixed $sections): bool
    {
        if (! is_array($sections) || ! array_is_list($sections)) return false;
        foreach ($sections as $section) {
            if (! is_array($section) || ! in_array($section['type'] ?? null, PageSection::TYPES, true) || ! is_array($section['data'] ?? null)) return false;
        }

        return true;
    }

    private function assertPublishable(Page $page): void
    {
        $hero = $page->sections()->where('type', 'hero')->where('is_visible', true)->first();
        $heroData = $hero?->data ?? [];
        $image = $heroData['image'] ?? null;

        if (! is_string($image) || trim($image) === '') {
            throw ValidationException::withMessages(['publish' => 'Add an image to a visible Hero section before publishing this page.']);
        }
        if (str_starts_with($image, 'media/') && ! Storage::disk('public')->exists($image)) {
            throw ValidationException::withMessages(['publish' => 'The selected Hero image file is missing from storage. Re-select or upload the image before publishing.']);
        }
        if (str_starts_with($image, '/images/') && ! is_file(public_path(ltrim($image, '/')))) {
            throw ValidationException::withMessages(['publish' => 'The selected Hero image file is missing from public/images. Re-select or upload the image before publishing.']);
        }
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate($this->rules($page), self::MESSAGES);

        $data['show_in_menu'] = $request->boolean('show_in_menu');
        $data['sort_order'] = (int) ($request->input('sort_order') ?? 0);

        return $data;
    }

    private const MESSAGES = [
        'canonical_url.max' => 'The canonical URL must be 255 characters or fewer.',
        'canonical_url.url' => 'The canonical URL must be a complete web address, for example https://kelikasports.com/about.',
    ];

    /** Page settings rules, shared by saving the settings form and restoring a revision. */
    private function rules(?Page $page): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->ignore($page?->id),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (is_string($value) && ReservedPaths::isReserved($value)) {
                        $fail("/{$value} is used by the website itself (a system route or file folder). Choose another URL.");
                    }
                },
            ],
            'template' => ['required', Rule::in(['standard', 'landing'])],
            'status' => ['required', Rule::in(Page::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'show_in_menu' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'url', 'max:255'], // stored in a 255-character column
            // Only an image the library can still serve: not deleted, not a PDF, and with its file on disk.
            'og_media_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                if (! MediaPicker::find($value)) $fail('Choose an available image from the media library as the sharing image, or leave it empty to use the global image.');
            }],
            'robots_directive' => ['required', Rule::in(['index,follow', 'noindex,follow', 'noindex,nofollow'])],
            'og_title' => ['nullable', 'string', 'max:120'],
            'og_description' => ['nullable', 'string', 'max:320'],
        ];
    }
}

@extends('layouts.admin')
@section('title', 'Edit Section')
@section('heading', 'Edit Section')
@section('content')
@php($d = \App\Support\SectionDefaults::apply($section))
<div class="admin-panel">
    <div class="mb-4">
        <a href="{{ route('admin.pages.edit', $page) }}">← Back to {{ $page->title }}</a>
        <p class="eyebrow mt-3">{{ str_replace('_', ' ', ucfirst($section->type)) }}</p>
        <h2 class="h4 fw-bold">Section content</h2>
        <p class="text-secondary small">Only supported fields are rendered. Text is escaped by default; stored scripts and arbitrary PHP are never executed.</p>
    </div>
    <form method="post" action="{{ route('admin.pages.sections.update', [$page, $section]) }}" id="section-editor-form">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label" for="admin_label">Internal section label</label><input class="form-control" id="admin_label" name="admin_label" value="{{ old('admin_label', $section->admin_label) }}" maxlength="120"></div>
            <div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="is_visible" name="is_visible" value="1" @checked(old('is_visible', $section->is_visible))><label class="form-check-label" for="is_visible">Visible on public page</label></div></div>
            <div class="col-md-6"><label class="form-label" for="eyebrow">Eyebrow / small heading</label><input class="form-control" id="eyebrow" name="eyebrow" value="{{ old('eyebrow', $d['eyebrow'] ?? '') }}" maxlength="120"></div>
            <div class="col-md-6"><label class="form-label" for="title">Heading</label><input class="form-control" id="title" name="title" value="{{ old('title', $d['title'] ?? '') }}" maxlength="240"><div class="form-text">Use the pipe character to create line breaks, e.g. First line|Second line.@if($section->type === 'hero') Wrap words in asterisks to highlight them in the accent colour, e.g. A STRONGER|*YOU*|THROUGH SPORT.@elseif(in_array($section->type, ['feature_list', 'program_list', 'card_grid', 'five_elements', 'sport_directory'], true)) In the layouts marked as the Academy or Sports design, wrap words in asterisks to highlight them in green, e.g. Developing Athletes|*Through the Five Elements*.@endif</div></div>
            <div class="col-12"><label class="form-label" for="subtitle">Subtitle</label><textarea class="form-control" id="subtitle" name="subtitle" rows="2" maxlength="300">{{ old('subtitle', $d['subtitle'] ?? '') }}</textarea></div>
            <div class="col-12"><label class="form-label" for="body">Body text</label><textarea class="form-control" id="body" name="body" rows="5" maxlength="12000">{{ old('body', $d['body'] ?? '') }}</textarea></div>
            @if(! in_array($section->type, ['hero', 'divider', 'map_embed', 'contact_form', 'contact_details'], true))
            <div class="col-md-4"><label class="form-label" for="heading_accent">Heading accent colour</label><select class="form-select" id="heading_accent" name="heading_accent"><option value="">Design default</option>@foreach(\App\Support\SectionDefaults::HEADING_ACCENTS as $accent)<option value="{{ $accent }}" @selected(old('heading_accent', $d['heading_accent'] ?? '') === $accent)>{{ $accent === 'none' ? 'None (single colour)' : ucfirst($accent) }}</option>@endforeach</select><div class="form-text">Colour applied to the heading lines after the first.</div></div>
            @endif
            @if($section->type === 'stats')
            <div class="col-12"><div class="form-check p-3 ps-5 rounded border {{ old('demo_content', $d['demo_content'] ?? false) ? 'border-warning bg-warning-subtle' : '' }}"><input class="form-check-input" type="checkbox" id="demo_content" name="demo_content" value="1" @checked(old('demo_content', $d['demo_content'] ?? false))><label class="form-check-label fw-bold" for="demo_content">Demo content — unverified statistics</label><div class="form-text">While ticked, these figures appear only in CMS preview and development demo mode; production visitors see a neutral “coming soon” band. Untick only after every figure has been verified.</div></div></div>
            @elseif($section->type === 'hero')
            <div class="col-12"><div class="form-check p-3 ps-5 rounded border {{ old('demo_content', $d['demo_content'] ?? false) ? 'border-warning bg-warning-subtle' : '' }}"><input class="form-check-input" type="checkbox" id="demo_content" name="demo_content" value="1" @checked(old('demo_content', $d['demo_content'] ?? false))><label class="form-check-label fw-bold" for="demo_content">Demo content — unverified figures</label><div class="form-text">Applies to hero items that have a Value (e.g. 500+). While ticked, those figures appear only in CMS preview and development demo mode; production visitors do not see them. Untick only after every figure has been verified.</div></div></div>
            @elseif($section->type === 'gallery')
            <div class="col-12"><div class="form-check p-3 ps-5 rounded border {{ old('demo_content', $d['demo_content'] ?? false) ? 'border-warning bg-warning-subtle' : '' }}"><input class="form-check-input" type="checkbox" id="demo_content" name="demo_content" value="1" @checked(old('demo_content', $d['demo_content'] ?? false))><label class="form-check-label fw-bold" for="demo_content">Demo content — unapproved images</label><div class="form-text">While ticked, this section's images appear only in CMS preview and development demo mode; production visitors see neutral placeholders. Untick only after the images are approved photographs of KELIKA.</div></div></div>
            @endif
            @if($section->type === 'facilities_showcase')
            <div class="col-12"><p class="form-text mb-0">The facilities listed come from the published Facility records in Content; this section sets the heading, text and photo.</p></div>
            @endif
            @if(isset(\App\Support\SectionDefaults::LAYOUTS[$section->type]))
            <div class="col-md-4"><label class="form-label" for="layout">Layout</label><select class="form-select" id="layout" name="layout"><option value="">Automatic</option>@foreach(\App\Support\SectionDefaults::LAYOUTS[$section->type] as $value => $label)<option value="{{ $value }}" @selected(old('layout', $d['layout'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            @endif
            @if($section->type === 'hero')
            @php($overlay = (int) old('overlay_darkness', \App\Support\SectionDefaults::overlayDarkness($section)))
            <div class="col-md-4"><label class="form-label" for="overlay_darkness">Image Overlay Darkness: <output id="overlay_darkness_value" for="overlay_darkness" data-range-value>{{ $overlay }}%</output></label><input class="form-range" type="range" id="overlay_darkness" name="overlay_darkness" min="0" max="{{ \App\Support\SectionDefaults::OVERLAY_MAX }}" step="1" value="{{ $overlay }}" data-range-output="overlay_darkness_value" aria-describedby="overlay_darkness_help"><div class="form-text" id="overlay_darkness_help">The black shade over the photo in the “Full-width photo behind the text” layout. Default {{ \App\Support\SectionDefaults::OVERLAY_DEFAULT }}% (the original design); 0% removes it, leaving only the soft fade where the photo meets the text. Phones use the same setting.</div></div>
            @endif
            @if(in_array($section->type, ['hero', 'cta'], true))
            <div class="col-md-6"><label class="form-label" for="script_text">Handwritten accent text (optional)</label><input class="form-control" id="script_text" name="script_text" value="{{ old('script_text', $d['script_text'] ?? '') }}" maxlength="120"><div class="form-text">Decorative script text over the image. Use | for line breaks. Leave empty to hide.</div></div>
            @endif
            @if($section->type === 'hero')
            <div class="col-md-6"><label class="form-label" for="values_text">Side panel words (optional)</label><input class="form-control" id="values_text" name="values_text" value="{{ old('values_text', $d['values_text'] ?? '') }}" maxlength="160"><div class="form-text">Separate words with |, e.g. TRAIN|COMPETE|GROW. Leave empty to hide.</div></div>
            @endif
            @if($section->type === 'split')
            <div class="col-md-6"><label class="form-label" for="video_url">Video / virtual tour URL (optional)</label><input class="form-control" id="video_url" name="video_url" value="{{ old('video_url', $d['video_url'] ?? '') }}" maxlength="2048" placeholder="https://..."></div>
            <div class="col-md-6"><label class="form-label" for="video_label">Video button label</label><input class="form-control" id="video_label" name="video_label" value="{{ old('video_label', $d['video_label'] ?? '') }}" maxlength="80" placeholder="Take a virtual tour"></div>
            @endif
            @if(in_array($section->type, \App\Http\Controllers\Admin\PageSectionController::IMAGE_SECTION_TYPES, true))
            {{-- A published page must keep its hero image, so only a draft hero offers to clear it. --}}
            <div class="col-md-6">@include('admin.sections._image-picker', ['field' => 'image', 'label' => 'Desktop image', 'optional' => ! ($section->type === 'hero' && $page->status === 'published')])</div>
            <div class="col-md-6">@include('admin.sections._image-picker', ['field' => 'mobile_image', 'label' => 'Mobile image (optional)', 'optional' => true])</div>
            <div class="col-md-3"><label class="form-label" for="image_position">Image focal position</label><select class="form-select" id="image_position" name="image_position">@php($positions = ['center','top','bottom','left','right','center top','center bottom','left center','right center'])@php($currentPosition = (string) old('image_position', $d['image_position'] ?? 'center'))@if($currentPosition !== '' && ! in_array($currentPosition, $positions, true)){{-- A fine-tuned stored value is offered as-is, so saving the form never resets it. --}}<option value="{{ $currentPosition }}" selected>Custom ({{ $currentPosition }})</option>@endif @foreach($positions as $position)<option value="{{ $position }}" @selected($currentPosition === $position)>{{ ucfirst($position) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="image_alt">Image alt text</label><input class="form-control" id="image_alt" name="image_alt" value="{{ old('image_alt', $d['image_alt'] ?? '') }}" maxlength="250"></div>
            @endif
            @if(in_array($section->type, ['hero', 'split', 'cta', 'card_grid', 'event_list', 'contact_form', 'feature_list', 'gallery'], true))
            <div class="col-md-3"><label class="form-label" for="button_label">Primary button label</label><input class="form-control" id="button_label" name="button_label" value="{{ old('button_label', $d['button_label'] ?? '') }}" maxlength="80"></div>
            <div class="col-md-3"><label class="form-label" for="button_url">Primary button URL</label><input class="form-control" id="button_url" name="button_url" value="{{ old('button_url', $d['button_url'] ?? '') }}" maxlength="2048"></div>
            @endif
            @if(in_array($section->type, ['hero', 'cta', 'split'], true))
            <div class="col-md-6"><label class="form-label" for="secondary_button_label">Secondary button label</label><input class="form-control" id="secondary_button_label" name="secondary_button_label" value="{{ old('secondary_button_label', $d['secondary_button_label'] ?? '') }}" maxlength="80"></div>
            <div class="col-md-6"><label class="form-label" for="secondary_button_url">Secondary button URL</label><input class="form-control" id="secondary_button_url" name="secondary_button_url" value="{{ old('secondary_button_url', $d['secondary_button_url'] ?? '') }}" maxlength="2048"></div>
            @endif
            @if($section->type === 'map_embed')
            <div class="col-md-8"><label class="form-label" for="map_url">Map URL (for map sections)</label><input class="form-control" id="map_url" name="map_url" value="{{ old('map_url', $d['map_url'] ?? '') }}" maxlength="2048"></div>
            @endif
            @if(in_array($section->type, ['cta', 'split', 'rich_text'], true))
            <div class="col-md-4"><label class="form-label" for="tone">Background tone</label><select class="form-select" id="tone" name="tone">@foreach(['light','green','terracotta','gold','soft'] as $tone)<option value="{{ $tone }}" @selected(old('tone', $d['tone'] ?? ($section->type === 'cta' ? 'green' : 'light')) === $tone)>{{ ucfirst($tone) }}</option>@endforeach</select></div>
            @endif
            @if(in_array($section->type, ['hero', 'split', 'card_grid', 'feature_list', 'five_elements', 'stats', 'faq', 'gallery'], true))
            <div class="col-12">
                <hr>
                <div id="items-editor" data-section-type="{{ $section->type }}" class="items-editor">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h3 class="h5 fw-bold mb-1">Cards, features and repeatable items</h3><p class="small text-secondary mb-0">Add, remove and reorder items without editing JSON manually.@if(in_array($section->type, ['hero', 'split', 'card_grid', 'feature_list'], true)) Icon names: {{ implode(', ', \App\Support\Icon::names()) }}.@endif @if($section->type === 'hero') Hero items form the highlight strip below the buttons; use | in a title for a line break.@endif @if($section->type === 'split') Items are shown only in the “Featured program” layout (as its highlights panel) and the “Main attraction” layout (as its image cards).@endif @if($section->type === 'feature_list') In the “option cards with checklists” layout, put each checklist point of a card on its own line in its description.@endif</p></div><button type="button" class="btn btn-kelika-outline" id="add-section-item">Add Item +</button></div>
                    <div id="section-items-list" class="section-items-list"></div>
                    <textarea class="d-none" id="items_json">{{ old('items_json', isset($d['items']) ? json_encode($d['items'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : '[]') }}</textarea>
                </div>
                <details class="items-json-fallback mt-3"><summary class="small">Advanced JSON editor (fallback)</summary><p class="form-text">Use a JSON array of objects. URLs must be relative paths or safe http/https URLs; colors must be six-digit hex values.</p><textarea class="form-control font-monospace" id="items_json_fallback" name="items_json" rows="10">{{ old('items_json', isset($d['items']) ? json_encode($d['items'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : '[]') }}</textarea><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="apply-items-json">Apply JSON to item editor</button></details>
            </div>
            @endif
            <div class="col-12 d-flex flex-wrap gap-2 mt-4"><button class="btn btn-kelika">Save Section</button><a class="btn btn-kelika-outline" href="{{ route('admin.pages.edit', $page) }}">Cancel</a></div>
        </div>
    </form>
</div>
@if(in_array($section->type, [...\App\Http\Controllers\Admin\PageSectionController::IMAGE_SECTION_TYPES, 'card_grid', 'gallery', 'five_elements'], true))
{{-- Media library picker, shared by the image fields above and the item image fields. Loaded only when opened. --}}
<script type="application/json" id="section-image-previews">@json((object) $imagePreviews)</script>
<dialog class="media-picker" id="media-picker" data-endpoint="{{ route('admin.media.picker') }}" aria-labelledby="media-picker-title">
    <div class="media-picker__header">
        <h2 class="h5 fw-bold mb-0" id="media-picker-title">Choose an image</h2>
        <button type="button" class="btn-close" data-picker-close aria-label="Close"></button>
    </div>
    <form class="media-picker__search" role="search" data-picker-search>
        <input class="form-control" type="search" name="q" maxlength="120" placeholder="Search by file name, alt text or caption" aria-label="Search images">
        <button class="btn btn-kelika-outline" type="submit">Search</button>
    </form>
    <div class="media-picker__grid" data-picker-grid></div>
    <p class="small text-secondary my-2" data-picker-status role="status"></p>
    <div class="media-picker__footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-picker-more hidden>Load more</button>
        <a class="small" href="{{ route('admin.media.index') }}" target="_blank" rel="noopener">Upload new images in the Media Library</a>
    </div>
</dialog>
@endif
@endsection

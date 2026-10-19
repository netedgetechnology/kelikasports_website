@extends('layouts.admin')
@section('title', 'Edit Section')
@section('heading', 'Edit Section')
@section('content')
@php($d = $section->data ?? [])
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
            <div class="col-md-6"><label class="form-label" for="title">Heading</label><input class="form-control" id="title" name="title" value="{{ old('title', $d['title'] ?? '') }}" maxlength="240"><div class="form-text">Use the pipe character to create line breaks, e.g. First line|Second line.</div></div>
            <div class="col-12"><label class="form-label" for="subtitle">Subtitle</label><textarea class="form-control" id="subtitle" name="subtitle" rows="2" maxlength="300">{{ old('subtitle', $d['subtitle'] ?? '') }}</textarea></div>
            <div class="col-12"><label class="form-label" for="body">Body text</label><textarea class="form-control" id="body" name="body" rows="5" maxlength="12000">{{ old('body', $d['body'] ?? '') }}</textarea></div>
            @if(in_array($section->type, ['hero', 'split', 'cta'], true))
            <div class="col-md-6"><label class="form-label" for="image">Desktop image path or full URL</label><input class="form-control" id="image" name="image" value="{{ old('image', $d['image'] ?? '') }}" maxlength="2048" placeholder="media/2026/10/example.webp"><div class="form-text"><a href="{{ route('admin.media.index') }}" target="_blank" rel="noopener">Open Media Library</a> to upload and copy an asset path.</div></div>
            <div class="col-md-6"><label class="form-label" for="mobile_image">Mobile image path or full URL (optional)</label><input class="form-control" id="mobile_image" name="mobile_image" value="{{ old('mobile_image', $d['mobile_image'] ?? '') }}" maxlength="2048"></div>
            <div class="col-md-3"><label class="form-label" for="image_position">Image focal position</label><select class="form-select" id="image_position" name="image_position">@foreach(['center','top','bottom','left','right','center top','center bottom','left center','right center'] as $position)<option value="{{ $position }}" @selected(old('image_position', $d['image_position'] ?? 'center') === $position)>{{ ucfirst($position) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="image_alt">Image alt text</label><input class="form-control" id="image_alt" name="image_alt" value="{{ old('image_alt', $d['image_alt'] ?? '') }}" maxlength="250"></div>
            @endif
            @if(in_array($section->type, ['hero', 'split', 'cta', 'card_grid', 'event_list', 'contact_form'], true))
            <div class="col-md-3"><label class="form-label" for="button_label">Primary button label</label><input class="form-control" id="button_label" name="button_label" value="{{ old('button_label', $d['button_label'] ?? '') }}" maxlength="80"></div>
            <div class="col-md-3"><label class="form-label" for="button_url">Primary button URL</label><input class="form-control" id="button_url" name="button_url" value="{{ old('button_url', $d['button_url'] ?? '') }}" maxlength="2048"></div>
            @endif
            @if(in_array($section->type, ['hero', 'cta'], true))
            <div class="col-md-6"><label class="form-label" for="secondary_button_label">Secondary button label</label><input class="form-control" id="secondary_button_label" name="secondary_button_label" value="{{ old('secondary_button_label', $d['secondary_button_label'] ?? '') }}" maxlength="80"></div>
            <div class="col-md-6"><label class="form-label" for="secondary_button_url">Secondary button URL</label><input class="form-control" id="secondary_button_url" name="secondary_button_url" value="{{ old('secondary_button_url', $d['secondary_button_url'] ?? '') }}" maxlength="2048"></div>
            @endif
            @if($section->type === 'map_embed')
            <div class="col-md-8"><label class="form-label" for="map_url">Map URL (for map sections)</label><input class="form-control" id="map_url" name="map_url" value="{{ old('map_url', $d['map_url'] ?? '') }}" maxlength="2048"></div>
            @endif
            @if(in_array($section->type, ['cta', 'split', 'rich_text'], true))
            <div class="col-md-4"><label class="form-label" for="tone">Background tone</label><select class="form-select" id="tone" name="tone">@foreach(['light','green','terracotta','gold','soft'] as $tone)<option value="{{ $tone }}" @selected(old('tone', $d['tone'] ?? 'light') === $tone)>{{ ucfirst($tone) }}</option>@endforeach</select></div>
            @endif
            @if(in_array($section->type, ['card_grid', 'feature_list', 'five_elements', 'stats', 'faq', 'gallery'], true))
            <div class="col-12">
                <hr>
                <div id="items-editor" data-section-type="{{ $section->type }}" class="items-editor">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h3 class="h5 fw-bold mb-1">Cards, features and repeatable items</h3><p class="small text-secondary mb-0">Add, remove and reorder items without editing JSON manually.</p></div><button type="button" class="btn btn-kelika-outline" id="add-section-item">Add Item +</button></div>
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
@endsection

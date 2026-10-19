@extends('layouts.admin')
@section('title', ($item->exists ? 'Edit ' : 'Add ').ucfirst($type))
@section('heading', ($item->exists ? 'Edit ' : 'Add ').ucfirst($type))
@section('content')
<div class="admin-panel">
    <a href="{{ route('admin.content.index', $type) }}">← Back to {{ ucfirst($type) }}</a>
    <form class="mt-4" method="post" action="{{ $item->exists ? route('admin.content.update', [$type, $item]) : route('admin.content.store', $type) }}" id="content-item-form">
        @csrf
        @if($item->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label" for="title">Title *</label><input class="form-control" id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="180"></div>
            <div class="col-md-4"><label class="form-label" for="slug">Slug</label><input class="form-control" id="slug" name="slug" value="{{ old('slug', $item->slug) }}" maxlength="180"><div class="form-text">Leave blank to generate from the title.</div></div>
            <div class="col-md-4"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="draft" @selected(old('status', $item->status) === 'draft')>Draft</option><option value="published" @selected(old('status', $item->status) === 'published')>Published</option></select></div>
            <div class="col-md-4"><label class="form-label" for="sort_order">Display order</label><input class="form-control" type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}" min="0" max="9999"></div>
            <div class="col-md-6"><label class="form-label" for="starts_at">Start date/time (for events)</label><input class="form-control" type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', $item->starts_at?->format('Y-m-d\TH:i')) }}"></div>
            <div class="col-md-6"><label class="form-label" for="ends_at">End date/time (for events)</label><input class="form-control" type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', $item->ends_at?->format('Y-m-d\TH:i')) }}"></div>
            <div class="col-md-6"><label class="form-label" for="seo_title">SEO title</label><input class="form-control" id="seo_title" name="seo_title" value="{{ old('seo_title', $item->seo_title) }}" maxlength="70"></div>
            <div class="col-md-6"><label class="form-label" for="seo_description">SEO description</label><input class="form-control" id="seo_description" name="seo_description" value="{{ old('seo_description', $item->seo_description) }}" maxlength="320"></div>
            <div class="col-12"><hr><div id="content-data-editor" data-content-type="{{ $type }}"><h2 class="h5 fw-bold">Content details</h2><p class="small text-secondary">Use these fields for common content. Nested data such as sport features remains preserved in the advanced data editor.</p><div id="content-data-fields" class="row g-3"></div><textarea class="d-none" id="data_json">{{ old('data_json', json_encode($item->data ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) }}</textarea></div>
                <details class="content-json-fallback mt-3"><summary class="small">Advanced JSON editor (fallback)</summary><p class="form-text">Structured data must be a JSON object. Text is escaped, and unsafe URLs or image paths are rejected.</p><textarea class="form-control font-monospace" id="data_json_fallback" name="data_json" rows="12">{{ old('data_json', json_encode($item->data ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) }}</textarea></details>
            </div>
            <div class="col-12 mt-4"><button class="btn btn-kelika">Save {{ ucfirst($type) }}</button></div>
        </div>
    </form>
</div>
@endsection

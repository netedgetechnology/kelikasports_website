{{--
    One section image field: the current image, a media library picker and the stored path or URL itself.
    Without JavaScript only the path box is used, exactly as before. The picker fills the path box and a hidden
    {field}_media_id, which the server resolves to the asset's own path; typing a path clears that id again.

    field, label, optional   image field name, its label, and whether it may be cleared
--}}
@php
    $value = trim((string) old($field, $d[$field] ?? ''));
    $preview = $value !== '' ? ($imagePreviews[$value] ?? ['source' => 'custom', 'name' => $value, 'thumb' => null, 'thumb_srcset' => null]) : null;
    $meta = $preview && $preview['source'] === 'library'
        ? implode(' · ', array_filter([($preview['width'] ?? null) && ($preview['height'] ?? null) ? $preview['width'].' × '.$preview['height'] : null, $preview['type'] ?? null, $preview['size'] ?? null]))
        : ($preview ? ($preview['source'] === 'missing' ? 'Not an available media library image — choose another image.' : 'Custom path or URL') : 'No image selected');
@endphp
<div class="media-pick" data-media-pick data-optional="{{ $optional ? '1' : '0' }}">
    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
    <div class="media-pick__current">
        <div class="media-pick__thumb" data-pick-thumb>
            @if($preview && $preview['thumb'])<img src="{{ $preview['thumb'] }}" @if($preview['thumb_srcset']) srcset="{{ $preview['thumb_srcset'] }}" sizes="96px" @endif alt="" loading="lazy" decoding="async">@endif
        </div>
        <div class="media-pick__info">
            <strong data-pick-name>{{ $preview['name'] ?? 'No image selected' }}</strong>
            <span class="{{ ($preview['source'] ?? null) === 'missing' ? 'text-danger' : 'text-secondary' }}" data-pick-meta>{{ $meta }}</span>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2" data-pick-controls hidden>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-pick-open>Choose from library</button>
        @if($optional)<button type="button" class="btn btn-sm btn-outline-danger" data-pick-clear @if($value === '') hidden @endif>Clear</button>@endif
    </div>
    <input type="hidden" name="{{ $field }}_media_id" value="{{ old($field.'_media_id') }}" data-pick-id>
    <input class="form-control form-control-sm mt-2" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $d[$field] ?? '') }}" maxlength="2048" placeholder="media/2026/10/example.jpg or https://…" aria-describedby="{{ $field }}_help" data-pick-path>
    <div class="form-text" id="{{ $field }}_help">Choose from the library, or enter a media path, /images/… path or full URL.</div>
</div>

{{--
    One public logo setting as a choice of library images (plus the built-in wordmark). The option values are the same
    paths the field always stored, so existing values, validation and rendering are unchanged; a custom value that is
    not in the list (an external URL, for example) is kept as its own option.
    Expects: $field, $label, $emptyLabel, $help, $settings, $logoOptions (path => label).
--}}
@php($current = (string) old($field, $settings->get($field)?->value ?? ''))
<label class="form-label" for="{{ $field }}">{{ $label }}</label>
<select class="form-select" id="{{ $field }}" name="{{ $field }}" aria-describedby="{{ $field }}-help">
    <option value="" @selected($current === '')>{{ $emptyLabel }}</option>
    @foreach ($logoOptions as $path => $optionLabel)
        <option value="{{ $path }}" @selected($current === $path)>{{ $optionLabel }}</option>
    @endforeach
    @if ($current !== '' && ! array_key_exists($current, $logoOptions))
        <option value="{{ $current }}" selected>Current: {{ \Illuminate\Support\Str::limit($current, 60) }}</option>
    @endif
</select>
<div class="form-text" id="{{ $field }}-help">{{ $help }}</div>
@if ($current !== '' && ! \App\Support\PublicLogos::resolve(['logo_url' => $current])['header'])
    <div class="form-text text-warning-emphasis" data-logo-unavailable="{{ $field }}">This image is not available (deleted or missing), so the website falls back to the next option.</div>
@endif

{{-- Pipe-delimited CMS heading. Lines after the first take the section's accent colour; words wrapped in *asterisks*
     take the highlight colour (kl-heading__highlight). --}}
@php($headingLines = \App\Support\SectionDefaults::lines($title ?? ''))
@if($headingLines)
<{{ $tag ?? 'h2' }} class="{{ $class ?? 'kl-heading' }}">
    @foreach($headingLines as $line)
        <span class="d-block {{ ! $loop->first && ($accent ?? 'none') !== 'none' ? 'kl-accent-'.$accent : '' }}">@foreach(\App\Support\SectionDefaults::highlights($line) as $part)@if($part['highlight'])<span class="kl-heading__highlight">{{ $part['text'] }}</span>@else{{ $part['text'] }}@endif @endforeach</span>
    @endforeach
</{{ $tag ?? 'h2' }}>
@endif

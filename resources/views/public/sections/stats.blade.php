@php($d=$section->data ?? [])
@if(!empty($d['items']))<section class="section-pad stat-band"><div class="container"><div class="row g-3 text-center">@foreach($d['items'] as $item)<div class="col-6 col-lg-3"><div class="stat-number">{{ $item['value'] ?? '' }}</div><div class="small mt-2">{{ $item['label'] ?? '' }}</div></div>@endforeach</div></div></section>@endif

@php($d=$section->data ?? [])
@php
$faqItems = !empty($d['items']) ? collect($d['items']) : ($faqs ?? collect())->map(fn ($item) => ['question' => $item->title, 'answer' => $item->data['answer'] ?? '']);
@endphp
<section class="section-pad"><div class="container"><div class="row g-4"><div class="col-lg-4"><p class="eyebrow">{{ $d['eyebrow'] ?? 'FAQs' }}</p><h2 class="section-title">{{ $d['title'] ?? 'Quick Answers' }}</h2>@if(!empty($d['subtitle']))<p class="text-secondary mt-3">{{ $d['subtitle'] }}</p>@endif</div><div class="col-lg-8">@foreach($faqItems as $i=>$item)<div class="faq-row"><details><summary class="fw-bold">{{ $item['question'] ?? '' }}</summary><p class="text-secondary mt-3 mb-0">{{ $item['answer'] ?? '' }}</p></details></div>@endforeach</div></div></div></section>

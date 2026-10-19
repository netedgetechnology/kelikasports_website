@extends('layouts.public')
@section('title', $page->seo_title ?: $page->title)
@section('content')
@if($page->sections->isEmpty())
    <section class="page-hero-small"><div class="container"><p class="eyebrow">{{ $page->title }}</p><h1 class="section-title">{{ $page->title }}</h1><p class="text-secondary mt-3">This page is ready for content in the KELIKA section builder.</p>@if($preview)<a class="btn btn-kelika mt-3" href="{{ route('admin.pages.edit',$page) }}">Edit this page</a>@endif</div></section>
@else
    @foreach($page->sections as $section)
        @includeIf('public.sections.'.$section->type, ['section'=>$section,'events'=>$events ?? collect(),'sports'=>$sports ?? collect(),'programs'=>$programs ?? collect()])
    @endforeach
@endif
@endsection

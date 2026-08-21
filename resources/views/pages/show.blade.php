{{-- resources/views/pages/show.blade.php --}}
@extends('layouts.app')

@section('title', $page->meta_title ?: $page->title)

@if ($page->meta_description)
    @section('meta_description', $page->meta_description)
@endif

@section('content')
    @if ($preview ?? false)
        <div class="alert alert-warning">Staff preview — this page may not be publicly visible yet.</div>
    @endif
    @foreach ($page->content ?? [] as $block)
        @includeIf('blocks.'.$block['type'], ['data' => $block['data'] ?? [], 'blockKey' => $loop->index])
    @endforeach
@endsection

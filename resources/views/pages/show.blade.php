{{-- resources/views/pages/show.blade.php --}}
@extends('layouts.app')

@section('title', $page->meta_title ?: $page->title)

@if ($page->meta_description)
    @section('meta_description', $page->meta_description)
@endif

@section('content')
    @foreach ($page->content ?? [] as $block)
        @includeIf('blocks.'.$block['type'], ['data' => $block['data'] ?? []])
    @endforeach
@endsection

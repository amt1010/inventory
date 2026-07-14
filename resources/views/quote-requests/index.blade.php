{{-- resources/views/quote-requests/index.blade.php --}}
@php($hideNavSearchForm = true)
@extends('layouts.app')

@section('title', 'My Quote Requests')

@section('content')
    <h1>My Quote Requests</h1>

    @if ($quoteRequests->isEmpty())
        <p class="text-muted">You haven't submitted any quote requests yet.</p>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Reason</th>
                    <th>Product</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quoteRequests as $quoteRequest)
                    <tr>
                        <td>{{ $quoteRequest->created_at->format('d M Y') }}</td>
                        <td>{{ $quoteRequest->fullName() }}</td>
                        <td>{{ $quoteRequest->reason }}</td>
                        <td>{{ $quoteRequest->product->name ?? 'General Inquiry' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $quoteRequest->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

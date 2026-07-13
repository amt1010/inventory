@extends('layouts.app')

@section('title', 'Become a Seller')

@section('content')
    <h1>Seller Registration</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('seller.register.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Contact Person</label>
                <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Business Address</label>
            <textarea name="business_address" class="form-control" rows="2" required>{{ old('business_address') }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">GST Number</label>
            <input type="text" name="gst_number" class="form-control" value="{{ old('gst_number') }}" placeholder="e.g. 27AAAAA0000A1Z5" required>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Business Documents (optional)</label>
            <input type="file" name="documents[]" class="form-control" multiple>
            <div class="form-text">GST certificate, trade license, or similar. PDF, JPG, or PNG, max 5MB each.</div>
        </div>

        <button type="submit" class="btn btn-primary">Register</button>
    </form>
@endsection

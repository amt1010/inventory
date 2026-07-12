<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
            @if (\Illuminate\Support\Facades\Route::has('catalog.search'))
                {{-- catalog.search is registered in Task 13; guarded here so the layout (shared by every
                     catalog page) doesn't 500 in the interim window before that route exists. --}}
                <form class="d-flex ms-auto" action="{{ route('catalog.search') }}" method="GET">
                    <input class="form-control me-2" type="search" name="q" placeholder="Search for item by keyword or product number" value="{{ request('q') }}">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </form>
            @endif
        </div>
    </nav>

    <main class="container py-4">
        @yield('content')
    </main>
</body>
</html>

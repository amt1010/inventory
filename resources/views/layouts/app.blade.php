<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto">
                    @foreach ($headerNavItems as $item)
                        @if ($item->children->isNotEmpty())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="{{ $item->url }}" data-bs-toggle="dropdown">{{ $item->label }}</a>
                                <ul class="dropdown-menu">
                                    @foreach ($item->children as $child)
                                        <li><a class="dropdown-item" href="{{ $child->url }}">{{ $child->label }}</a></li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="nav-item"><a class="nav-link" href="{{ $item->url }}">{{ $item->label }}</a></li>
                        @endif
                    @endforeach
                </ul>
                <form class="d-flex flex-grow-1 mx-3" style="max-width: 480px;" action="{{ route('catalog.search') }}" method="GET">
                    <input class="form-control me-2 flex-grow-1" type="search" name="q" placeholder="Search for item by keyword or product number" value="{{ request('q') }}">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </form>
                <ul class="navbar-nav ms-2">
                    @guest('web')
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Log In</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Register</a></li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('favorites.index') }}">My Favorites</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('quote-requests.history') }}">My Quote Requests</a></li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="nav-link btn btn-link">Log Out</button>
                            </form>
                        </li>
                    @endguest
                    <li class="nav-item"><a class="nav-link" href="{{ route('filament.seller.auth.login') }}">Login as Seller</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        @if (session('quote_request_submitted'))
            <div class="alert alert-success">Thank you — your quote request has been submitted. Our team will be in touch shortly.</div>
        @endif
        @yield('content')
    </main>

    <footer class="bg-light border-top py-4 mt-5">
        <div class="container">
            <ul class="list-inline mb-0">
                @foreach ($footerNavItems as $item)
                    <li class="list-inline-item me-3"><a href="{{ $item->url }}">{{ $item->label }}</a></li>
                @endforeach
            </ul>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

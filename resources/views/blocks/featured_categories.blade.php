{{-- resources/views/blocks/featured_categories.blade.php --}}
@php
    $categoryOrder = array_flip($data['category_ids'] ?? []);
    $categories = \App\Models\Category::query()
        ->whereIn('id', $data['category_ids'] ?? [])
        ->where('status', 'published')
        ->get()
        ->sortBy(fn ($category) => $categoryOrder[$category->id] ?? PHP_INT_MAX)
        ->values();
@endphp
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        @if (!empty($data['heading']))
            <h2 class="mb-0">{{ $data['heading'] }}</h2>
        @endif
        <a href="{{ url('/products') }}" class="md-btn md-btn-ghost">View all categories</a>
    </div>
    <div class="row row-cols-1 row-cols-md-4 g-4">
        @foreach ($categories as $category)
            @php
                $productCount = \App\Models\Product::query()
                    ->whereIn('category_id', \App\Support\CategoryHierarchy::descendantAndSelfIds($category))
                    ->where('status', 'published')
                    ->count();
            @endphp
            <div class="col">
                <a href="{{ url('/products/'.$category->path()) }}" class="md-card h-100 text-decoration-none d-block">
                    @if ($category->image)
                        <img src="{{ asset('storage/'.$category->image) }}" class="w-100" alt="{{ $category->name }}">
                    @endif
                    <div class="p-3">
                        <h5 class="mb-1" style="color: var(--color-text);">{{ $category->name }}</h5>
                        <span class="text-muted small">{{ $productCount }} products</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>

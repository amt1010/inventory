{{-- resources/views/blocks/featured_categories.blade.php --}}
@php
    $categories = \App\Models\Category::query()
        ->whereIn('id', $data['category_ids'] ?? [])
        ->where('status', 'published')
        ->get();
@endphp
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    <div class="row row-cols-1 row-cols-md-3 g-4">
        @foreach ($categories as $category)
            <div class="col">
                <a href="{{ url('/products/'.$category->path()) }}" class="card h-100 text-decoration-none">
                    @if ($category->image)
                        <img src="{{ asset('storage/'.$category->image) }}" class="card-img-top" alt="{{ $category->name }}">
                    @endif
                    <div class="card-body">
                        <h5 class="card-title text-dark">{{ $category->name }}</h5>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>

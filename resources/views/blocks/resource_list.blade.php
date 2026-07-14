{{-- resources/views/blocks/resource_list.blade.php --}}
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    <ul class="list-group">
        @foreach ($data['items'] ?? [] as $item)
            <li class="list-group-item">
                <strong>
                    @if (!empty($item['file']))
                        <a href="{{ asset('storage/'.$item['file']) }}">{{ $item['title'] }}</a>
                    @elseif (!empty($item['url']))
                        <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                    @else
                        {{ $item['title'] }}
                    @endif
                </strong>
                @if (!empty($item['description']))
                    <p class="mb-0 text-muted">{{ $item['description'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</div>

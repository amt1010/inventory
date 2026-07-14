{{-- resources/views/blocks/faq.blade.php --}}
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    <div class="accordion" id="faqAccordion{{ $blockKey ?? 0 }}">
        @foreach ($data['items'] ?? [] as $item)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $blockKey ?? 0 }}-{{ $loop->index }}">
                        {{ $item['question'] }}
                    </button>
                </h2>
                <div id="faq{{ $blockKey ?? 0 }}-{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#faqAccordion{{ $blockKey ?? 0 }}">
                    <div class="accordion-body">{{ $item['answer'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>

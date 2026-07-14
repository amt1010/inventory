{{-- resources/views/blocks/hero.blade.php --}}
<div class="p-5 mb-4 bg-light rounded-3 text-center"
     @if (!empty($data['background_image']))
         style="background-image: url('{{ asset('storage/'.$data['background_image']) }}'); background-size: cover; background-position: center;"
     @endif
>
    <h1>{{ $data['heading'] }}</h1>
    @if (!empty($data['subheading']))
        <p class="lead">{{ $data['subheading'] }}</p>
    @endif
    @if (!empty($data['cta_label']) && !empty($data['cta_url']))
        <a href="{{ $data['cta_url'] }}" class="btn btn-primary btn-lg">{{ $data['cta_label'] }}</a>
    @endif
</div>

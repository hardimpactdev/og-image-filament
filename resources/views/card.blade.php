<article
    data-og-card
    data-og-title="{{ $properties->string('title') }}"
    style="width: 1200px; height: 630px;"
>
    <p>{{ $properties->string('label') }}</p>
    <h1>{{ $properties->string('title') }}</h1>

    @if ($properties->filled('description'))
        <p>{{ $properties->string('description') }}</p>
    @endif

    @if ($properties->filled('url'))
        <p>{{ $properties->string('url') }}</p>
    @endif
</article>

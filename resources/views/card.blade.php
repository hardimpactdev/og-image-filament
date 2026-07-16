<article
    data-og-card
    data-og-title="{{ $properties->has('title') ? $properties->string('title') : '' }}"
    style="width: 1200px; height: 630px;"
>
    @if ($properties->has('label'))
        <p>{{ $properties->string('label') }}</p>
    @endif

    @if ($properties->has('title'))
        <h1>{{ $properties->string('title') }}</h1>
    @endif

    @if ($properties->has('description') && $properties->filled('description'))
        <p>{{ $properties->string('description') }}</p>
    @endif

    @if ($properties->has('url') && $properties->filled('url'))
        <p>{{ $properties->string('url') }}</p>
    @endif
</article>

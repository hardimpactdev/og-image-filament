<article
    data-og-card
    data-og-title="{{ $data->title ?? '' }}"
    style="width: 1200px; height: 630px;"
>
    @if (isset($data->label))
        <p>{{ $data->label }}</p>
    @endif

    @if (isset($data->title))
        <h1>{{ $data->title }}</h1>
    @endif

    @if (isset($data->description) && filled($data->description))
        <p>{{ $data->description }}</p>
    @endif

    @if (isset($data->url) && filled($data->url))
        <p>{{ $data->url }}</p>
    @endif
</article>

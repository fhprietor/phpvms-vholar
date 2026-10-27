@php
    // Resumen en texto plano: el cuerpo es HTML (hay noticias de mas de 4 KB) y pintarlo
    // entero convertia la tarjeta en media pagina. El texto completo sigue disponible al
    // desplegar "Leer mas", que es un <details> y no necesita JavaScript.
    $excerpt = fn ($body) => \Illuminate\Support\Str::limit(
        trim(preg_replace(
            '/\s+/',
            ' ',
            html_entity_decode(strip_tags((string) $body), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        )),
        160
    );
@endphp

<div class="card">
    <div class="card-header bg-primary text-white" role="tablist">
        @lang('widgets.latestnews.news')
    </div>
    <div class="card-body p-4">
        @if ($news->count() === 0)
            <div class="text-center text-muted" style="padding: 30px;">
                @lang('widgets.latestnews.nonewsfound')
            </div>
        @endif

        <style>
            .vh-news details > summary { cursor: pointer; list-style: none; font-weight: 600; }
            .vh-news details > summary::-webkit-details-marker { display: none; }
            .vh-news details .vh-news-less { display: none; }
            .vh-news details[open] .vh-news-more { display: none; }
            .vh-news details[open] .vh-news-less { display: inline; }
        </style>

        @foreach ($news as $item)
            <div class="vh-news {{ $loop->last ? '' : 'border-bottom pb-2 mb-2' }}">
                <h4 class="h6 mb-1">{{ $item->subject }}</h4>
                <p class="category mb-2" style="font-size: 0.75rem;">
                    {{ optional($item->user)->name_private }} - {{ show_datetime($item->created_at) }}
                </p>

                <p class="mb-2" style="font-size: 0.85rem;">{{ $excerpt($item->body) }}</p>

                <details>
                    <summary class="small">
                        <span class="vh-news-more">@lang('widgets.latestnews.read_more')</span>
                        <span class="vh-news-less">@lang('widgets.latestnews.read_less')</span>
                    </summary>
                    <div class="mt-2" style="font-size: 0.9rem;">{!! $item->body !!}</div>
                </details>
            </div>
        @endforeach
    </div>
</div>

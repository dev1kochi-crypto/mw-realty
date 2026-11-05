{{--
    The home page as plain HTML, built from the same admin data /api/home serves (Home Banner,
    section titles, Marketing Properties, Why Choose Us, Communities, Contact…). It sits inside
    #app, so the Vue app replaces it as soon as it mounts — visitors only ever see the Vue page.
    It is for clients that don't run JavaScript: SEO audit tools, link previews and AI crawlers
    (GPTBot, ClaudeBot, PerplexityBot…), which otherwise find an empty page. Hidden with
    .mw-snapshot (welcome.blade.php) and lazy images, so it costs no layout or downloads.
--}}
@php
    $cardImage = fn (?string $url) => $url ? \App\Support\CloudinaryUrl::sized($url, 480) : null;
    $propertySections = array_filter([
        $home['developments'] ?? null,
        $home['premiumProperties'] ?? null,
        $home['luxury'] ?? null,
        $home['realty'] ?? null,
    ], fn ($section) => collect($section['properties'] ?? [])->isNotEmpty());
    $banner = $home['banner'] ?? [];
    $contact = $home['contact'] ?? [];
@endphp
<div class="mw-snapshot">
    <header>
        <a href="{{ url('/') }}">{{ $siteName }}</a>
        <nav aria-label="Main">
            <a href="{{ url('/properties?listing_type=sale') }}">Properties for sale</a>
            <a href="{{ url('/properties?listing_type=rent') }}">Properties for rent</a>
            <a href="{{ url('/commercial') }}">Commercial properties</a>
            <a href="{{ url('/premium-properties') }}">Premium properties</a>
            <a href="{{ url('/agents') }}">Agents</a>
            <a href="{{ url('/agencies') }}">Agencies</a>
            <a href="{{ url('/market-insights') }}">Market insights</a>
            <a href="{{ url('/blogs') }}">Blog</a>
            <a href="{{ url('/mortgage-calculator') }}">Mortgage calculator</a>
            <a href="{{ url('/about') }}">About us</a>
            <a href="{{ url('/contact') }}">Contact us</a>
        </nav>
    </header>

    <main>
        <h1>{{ trim(($banner['line_1'] ?? 'Your Trusted Partner in') . ' ' . ($banner['line_2'] ?? 'Finding the Best Properties')) }}</h1>
        @if(!empty($banner['content']))
            <p>{{ strip_tags($banner['content']) }}</p>
        @endif
        @if($seoDescription)
            <p>{{ $seoDescription }}</p>
        @endif

        @foreach($propertySections as $section)
            <section>
                <h2>{{ strip_tags($section['title'] ?? '') }}</h2>
                @if(!empty($section['description']))
                    <p>{{ strip_tags($section['description']) }}</p>
                @endif
                <ul>
                    @foreach(collect($section['properties'])->take(6) as $property)
                        <li>
                            <a href="{{ url('/property-details/' . $property['slug']) }}">
                                @if($cardImage($property['image'] ?? ($property['images'][0] ?? null)))
                                    <img src="{{ $cardImage($property['image'] ?? ($property['images'][0] ?? null)) }}" alt="{{ $property['name'] }}{{ !empty($property['location']) ? ' — ' . $property['location'] : '' }}" loading="lazy" width="480" height="320">
                                @endif
                                {{ $property['name'] }}
                            </a>
                            <p>{{ collect([$property['type'] ?? null, $property['location'] ?? null, $property['price'] ?? null, $property['area'] ?? null])->filter()->implode(' · ') }}</p>
                        </li>
                    @endforeach
                </ul>
                @if(!empty($section['button_url']))
                    <a href="{{ url($section['button_url']) }}">{{ $section['button_name'] ?? 'View all' }}</a>
                @endif
            </section>
        @endforeach

        @if(!empty($home['findProperties']['items']))
            <section>
                <h2>{{ $home['findProperties']['title'] }}</h2>
                <ul>
                    @foreach($home['findProperties']['items'] as $item)
                        <li><a href="{{ url('/properties' . (!empty($item['property_type']) ? '?property_type=' . urlencode($item['property_type']) : '')) }}">{{ $item['title'] }}{{ isset($item['count']) ? " ({$item['count']} listings)" : '' }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(!empty($home['popularPlaces']['places']))
            <section>
                <h2>{{ strip_tags($home['popularPlaces']['title']) }}</h2>
                <ul>
                    @foreach($home['popularPlaces']['places'] as $place)
                        <li><a href="{{ url('/properties?location=' . urlencode($place['name'])) }}">Properties in {{ $place['name'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(!empty($home['communities']['items']))
            <section>
                <h2>{{ strip_tags($home['communities']['title']) }}</h2>
                <ul>
                    @foreach($home['communities']['items'] as $community)
                        <li><a href="{{ url('/properties?listing_type=sale&location=' . urlencode($community['name'])) }}">{{ $community['name'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(!empty($home['whyChooseUs']['items']))
            <section>
                <h2>{{ strip_tags($home['whyChooseUs']['title']) }}</h2>
                @foreach($home['whyChooseUs']['items'] as $item)
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ strip_tags($item['description'] ?? '') }}</p>
                @endforeach
            </section>
        @endif

        @if(!empty($home['testimonials']['items']))
            <section>
                <h2>{{ strip_tags($home['testimonials']['title']) }}</h2>
                @foreach(collect($home['testimonials']['items'])->take(6) as $item)
                    @if(!empty($item['content'] ?? $item['message'] ?? null))
                        <blockquote>
                            <p>{{ strip_tags($item['content'] ?? $item['message']) }}</p>
                            <cite>{{ collect([$item['name'] ?? null, $item['role'] ?? null])->filter()->implode(', ') }}</cite>
                        </blockquote>
                    @endif
                @endforeach
            </section>
        @endif

        <section>
            <h2>{{ strip_tags($contact['title'] ?? 'Contact us') }}</h2>
            @if(!empty($contact['description']))
                <p>{{ strip_tags($contact['description']) }}</p>
            @endif
            <address>
                @if(!empty($contact['address'])){{ $contact['address'] }}<br>@endif
                @if(!empty($contact['phone']))<a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a><br>@endif
                @if(!empty($contact['email']))<a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>@endif
            </address>
            <a href="{{ url('/contact') }}">Contact {{ $siteName }}</a>
        </section>
    </main>
</div>

{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}{{-- Deklarasi XML sengaja dipecah: di peladen dengan short_open_tag=On, tanda pembuka XML yang utuh terbaca sebagai tag PHP dan halaman jadi error 500. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if(!empty($url['lastmod']))
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ $url['priority'] }}</priority>
    </url>
@endforeach
</urlset>

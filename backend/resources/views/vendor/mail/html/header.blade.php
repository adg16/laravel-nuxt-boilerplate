@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- Same-origin brand logo (nginx serves it in dev and prod). A raster PNG so
     it renders across email clients that don't support SVG. --}}
<img src="{{ rtrim(config('app.url'), '/') }}/apple-touch-icon.png" class="logo" alt="{{ $slot }}">
<span class="brand-name">{!! $slot !!}</span>
</a>
</td>
</tr>

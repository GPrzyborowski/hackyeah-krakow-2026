@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" class="brand" style="display: inline-block;">
<table class="brand-table" align="center" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="brand-logo-cell">
<img src="{{ rtrim(config('app.url'), '/') }}/icon-192.png" class="logo" width="48" height="48" alt="">
</td>
<td class="brand-name-cell">
<span class="brand-name">{{ config('app.name') }}</span>
</td>
</tr>
</table>
</a>
<p class="brand-tagline">praca dla przyszłych i obecnych mam</p>
</td>
</tr>

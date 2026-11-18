@props(['rating'])
<span class="pr-stars" aria-label="{{ $rating }} out of 5 stars">@for($i=1;$i<=5;$i++)<svg viewBox="0 0 24 24" aria-hidden="true" class="{{ $i<=$rating?'filled':'' }}"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8l-6.2 3.2L7 14.2l-5-4.9 6.9-1Z"/></svg>@endfor</span>

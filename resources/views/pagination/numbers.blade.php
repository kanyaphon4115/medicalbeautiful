@if($paginator->hasPages())
@php
 $last=$paginator->lastPage();$current=$paginator->currentPage();$count=min(3,$last);$start=max(1,min($current-1,$last-$count+1));
@endphp
<nav class="ae-pagination" aria-label="{{ __('Pages') }}">
 @for($number=$start;$number<$start+$count;$number++)
 <a href="{{ $paginator->url($number) }}" @if($number===$current) aria-current="page" @endif aria-label="{{ __('Page') }} {{ $number }}">{{ $number }}</a>
 @endfor
</nav>
@endif

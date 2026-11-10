@props(['name', 'size' => 24])
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
@switch($name)
@case('arrow')<path d="M5 12h14m-5-5 5 5-5 5"/>@break
@case('chevron')<path d="m9 5 7 7-7 7"/>@break
@case('search')<circle cx="10.8" cy="10.8" r="7"/><path d="m16 16 5 5"/>@break
@case('heart')<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>@break
@case('pin')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>@break
@case('shield')<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4Z"/><path d="m8 12 3 3 5-6"/>@break
@case('sparkle')<path d="m12 2 3 7 7 3-7 3-3 7-3-7-7-3 7-3 3-7Z"/><path d="m4 2 1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2Z"/>@break
@case('headset')<path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H2v7h4v-7H4Zm16 0h2v7h-4v-7h2Zm0 7c0 3-5 3-8 3"/>@break
@case('card')<rect x="2" y="5" width="20" height="15" rx="2"/><path d="M2 10h20M6 15h3m6 0h3"/>@break
@case('users')<circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3H2Zm14-17a3 3 0 0 1 0 6m3 11h3v-3a7 7 0 0 0-5-6"/>@break
@case('document')<path d="M14 2H4v20h16V8l-6-6Zm0 0v6h6M8 12h8m-8 4h6"/>@break
@case('smile')<circle cx="12" cy="12" r="10"/><path d="M8 14s1 3 4 3 4-3 4-3M8 8h.01M16 8h.01"/>@break
@case('play')<circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4V8Z"/>@break
@endswitch
</svg>

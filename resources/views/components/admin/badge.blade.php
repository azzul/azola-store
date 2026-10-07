@props(['value', 'map' => []])
@php($entry = $map[$value] ?? [$value, ''])
<span class="badge {{ $entry[1] ? 'badge--'.$entry[1] : '' }}">{{ $entry[0] }}</span>

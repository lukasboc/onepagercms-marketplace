@props(['email'])

@php $parts = explode('@', $email ?? '', 2); @endphp

@if (count($parts) === 2)
    <span x-data="obfuscatedEmail"
          data-user="{{ base64_encode($parts[0]) }}"
          data-domain="{{ base64_encode($parts[1]) }}"
          {{ $attributes->merge(['class' => 'text-indigo-600 hover:text-indigo-500']) }}
    >{{ $parts[0] }} [at] {{ str_replace('.', ' [punkt] ', $parts[1]) }}</span>
@endif

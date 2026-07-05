<x-mail::message>
# Your {{ $version->item->type }} was {{ $decision }}

Hello {{ $version->item->user->name }},

your submission **{{ $version->item->name }} {{ $version->version }}** was **{{ $decision }}**.

**Reviewer note:**

> {{ $note }}

@if ($decision === 'approved')
Your {{ $version->item->type }} is now listed in the OnePagerCMS marketplace.
@else
You can fix the issues mentioned above and submit a new version at any time.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

<x-mail::message>
# Your {{ $item->type }} was {{ $decision }}

Hello {{ $item->user->name }},

your {{ $item->type }} **{{ $item->name }}** was **{{ $decision }}**.

**Reviewer note:**

> {{ $note }}

@if ($decision === 'relisted')
Your {{ $item->type }} is listed in the OnePagerCMS marketplace again.
@else
Your {{ $item->type }} is no longer listed in the OnePagerCMS marketplace. If you have questions about this decision, please contact us.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

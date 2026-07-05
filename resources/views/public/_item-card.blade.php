@php($latest = $item->latestApprovedVersion())
<a href="{{ route('catalog.show', $item->slug) }}"
   class="block bg-white rounded-lg border border-gray-200 hover:border-indigo-400 hover:shadow-md transition p-6">
    <div class="flex items-start justify-between gap-2">
        <h3 class="font-semibold text-gray-900">{{ $item->name }}</h3>
        @if ($item->is_paid)
            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Paid</span>
        @else
            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Free</span>
        @endif
    </div>
    <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $item->summary }}</p>
    <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
        <span>{{ ucfirst($item->type) }} · v{{ $latest?->version }} · by {{ $item->user->name }}</span>
        <span>{{ number_format($item->downloads) }} downloads</span>
    </div>
</a>

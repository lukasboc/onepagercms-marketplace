<x-public-layout :title="ucfirst(Str::plural($type))">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <h1 class="text-3xl font-bold text-gray-900">{{ ucfirst(Str::plural($type)) }}</h1>
            <form method="get" class="flex gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search {{ Str::plural($type) }}..."
                       class="border-gray-300 rounded-md shadow-sm text-sm w-64">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-white text-sm font-semibold hover:bg-gray-700">
                    Search
                </button>
            </form>
        </div>

        @if ($items->isEmpty())
            <p class="text-gray-500 py-12 text-center">
                @if ($search !== '')
                    No {{ Str::plural($type) }} found for “{{ $search }}”.
                @else
                    No {{ Str::plural($type) }} have been published yet.
                @endif
            </p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    @include('public._item-card', ['item' => $item])
                @endforeach
            </div>
            <div class="mt-8">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</x-public-layout>

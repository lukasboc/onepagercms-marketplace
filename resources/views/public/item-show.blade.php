<x-public-layout :title="$item->name" :description="(string) $item->summary">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <nav class="text-sm text-gray-500 mb-6">
            <a class="hover:text-gray-700" href="{{ route('landing') }}">Marketplace</a>
            <span class="mx-1">/</span>
            <a class="hover:text-gray-700"
               href="{{ $item->type === 'theme' ? route('catalog.themes') : route('catalog.plugins') }}">{{ ucfirst(Str::plural($item->type)) }}</a>
            <span class="mx-1">/</span>
            <span class="text-gray-700">{{ $item->name }}</span>
        </nav>

        <div class="bg-white rounded-lg border border-gray-200 p-8">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        {{ $item->name }}
                        @if ($item->is_paid)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-amber-100 text-amber-800">Paid</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-green-100 text-green-800">Free</span>
                        @endif
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ ucfirst($item->type) }} · <code class="text-xs">{{ $item->slug }}</code> · by
                        {{ $item->user->name }} · {{ number_format($item->downloads) }} downloads
                    </p>
                    <p class="mt-4 text-gray-700">{{ $item->summary }}</p>
                </div>
                <div class="shrink-0 space-y-2 text-right">
                    @if ($item->is_paid)
                        @if ($item->purchase_url)
                            <a href="{{ $item->purchase_url }}" target="_blank" rel="noopener"
                               class="inline-flex items-center px-5 py-2.5 bg-indigo-600 rounded-md text-white font-semibold hover:bg-indigo-500">
                                Buy on developer site
                            </a>
                        @endif
                        <p class="text-xs text-gray-500 max-w-[220px]">You receive the ZIP and a license key from the
                            developer, then install it via your OnePagerCMS admin backend (Extensions &rarr; Upload).</p>
                    @else
                        <a href="{{ url("/api/v1/items/{$item->slug}/download") }}"
                           class="inline-flex items-center px-5 py-2.5 bg-indigo-600 rounded-md text-white font-semibold hover:bg-indigo-500">
                            Download v{{ $latest->version }}
                        </a>
                        <p class="text-xs text-gray-500 max-w-[220px]">Or install it directly from your OnePagerCMS
                            admin backend (Extensions &rarr; Marketplace).</p>
                    @endif
                </div>
            </div>

            @if ($item->description)
                <div class="mt-8 border-t border-gray-100 pt-6 text-gray-700 whitespace-pre-line">{{ $item->description }}</div>
            @endif

            <div class="mt-8 border-t border-gray-100 pt-6">
                <h2 class="font-semibold text-gray-900 mb-3">Version history</h2>
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                    <tr class="border-b">
                        <th class="py-2 font-medium">Version</th>
                        <th class="py-2 font-medium">Changelog</th>
                        <th class="py-2 font-medium">Requires OPCMS</th>
                        <th class="py-2 font-medium">Released</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($approvedVersions as $version)
                        <tr class="border-b border-gray-100">
                            <td class="py-2 font-medium">{{ $version->version }}</td>
                            <td class="py-2 text-gray-600">{{ $version->changelog }}</td>
                            <td class="py-2 text-gray-600">{{ $version->requires_opcms ?? '—' }}</td>
                            <td class="py-2 text-gray-600">{{ $version->updated_at->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-public-layout>

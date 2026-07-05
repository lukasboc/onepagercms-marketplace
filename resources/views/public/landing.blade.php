<x-public-layout>
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-gray-900">
                Extend your <span class="text-indigo-600">OnePagerCMS</span> website
            </h1>
            <p class="mt-4 max-w-2xl mx-auto text-lg text-gray-600">
                Browse {{ $pluginCount }} {{ Str::plural('plugin', $pluginCount) }} and
                {{ $themeCount }} {{ Str::plural('theme', $themeCount) }} — reviewed by our team and
                installable directly from your OnePagerCMS admin backend.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="{{ route('catalog.plugins') }}"
                   class="inline-flex items-center px-6 py-3 bg-indigo-600 rounded-md text-white font-semibold hover:bg-indigo-500">
                    Browse plugins
                </a>
                <a href="{{ route('catalog.themes') }}"
                   class="inline-flex items-center px-6 py-3 bg-white border border-gray-300 rounded-md text-gray-700 font-semibold hover:bg-gray-50">
                    Browse themes
                </a>
                <a href="{{ route('developers') }}"
                   class="inline-flex items-center px-6 py-3 text-indigo-600 font-semibold hover:text-indigo-500">
                    Publish your own &rarr;
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
        @if ($popular->isNotEmpty())
            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Most popular</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($popular as $item)
                        @include('public._item-card', ['item' => $item])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($latest->isNotEmpty())
            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Recently added</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($latest as $item)
                        @include('public._item-card', ['item' => $item])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($popular->isEmpty() && $latest->isEmpty())
            <p class="text-center text-gray-500 py-12">No extensions have been published yet — yours could be the
                first! <a class="text-indigo-600 hover:underline" href="{{ route('developers') }}">Learn how to
                    submit.</a></p>
        @endif

        <section class="bg-indigo-50 border border-indigo-100 rounded-xl p-8 text-center">
            <h2 class="text-2xl font-bold text-gray-900">Built something for OnePagerCMS?</h2>
            <p class="mt-2 text-gray-600 max-w-xl mx-auto">
                Publish your plugin or theme in the marketplace — free or paid. Read the guidelines, submit a ZIP,
                and our team will review it.
            </p>
            <a href="{{ route('developers') }}"
               class="mt-6 inline-flex items-center px-6 py-3 bg-indigo-600 rounded-md text-white font-semibold hover:bg-indigo-500">
                Developer guide
            </a>
        </section>
    </div>
</x-public-layout>

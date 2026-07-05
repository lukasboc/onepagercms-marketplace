<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title . ' — ' : '' }}{{ config('app.name', 'OnePagerCMS Marketplace') }}</title>
    <meta name="description" content="{{ $description ?? 'Plugins and themes for OnePagerCMS — browse, install, and publish your own extensions.' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased flex flex-col min-h-screen">
<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-8">
                <a href="{{ route('landing') }}" class="font-bold text-lg tracking-tight">
                    OnePagerCMS <span class="text-indigo-600">Marketplace</span>
                </a>
                <div class="hidden sm:flex items-center gap-6 text-sm font-medium text-gray-600">
                    <a class="hover:text-gray-900" href="{{ route('catalog.plugins') }}">Plugins</a>
                    <a class="hover:text-gray-900" href="{{ route('catalog.themes') }}">Themes</a>
                    <a class="hover:text-gray-900" href="{{ route('developers') }}">Developers</a>
                </div>
            </div>
            <div class="flex items-center gap-4 text-sm font-medium">
                @auth
                    <a class="text-gray-600 hover:text-gray-900" href="{{ route('developer.items.index') }}">My items</a>
                    @if (auth()->user()->isAdmin())
                        <a class="text-gray-600 hover:text-gray-900" href="{{ route('admin.review.index') }}">Review queue</a>
                    @endif
                @else
                    <a class="text-gray-600 hover:text-gray-900" href="{{ route('login') }}">Log in</a>
                    <a class="inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-white hover:bg-indigo-500"
                       href="{{ route('register') }}">Register</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<main class="flex-1">
    {{ $slot }}
</main>

<footer class="border-t border-gray-200 bg-white py-8 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between gap-4 text-sm text-gray-500">
        <p>&copy; {{ date('Y') }} <a class="hover:text-gray-700" href="https://onepagercms.de">OnePagerCMS</a></p>
        <div class="flex gap-6">
            <a class="hover:text-gray-700" href="{{ route('catalog.plugins') }}">Plugins</a>
            <a class="hover:text-gray-700" href="{{ route('catalog.themes') }}">Themes</a>
            <a class="hover:text-gray-700" href="{{ route('developers') }}">Publish your extension</a>
        </div>
    </div>
</footer>
</body>
</html>

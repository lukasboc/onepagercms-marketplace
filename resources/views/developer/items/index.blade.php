<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Items</h2>
            <a href="{{ route('developer.items.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Submit new item
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($items->isEmpty())
                        <p>You have not submitted any plugins or themes yet.</p>
                    @else
                        <table class="w-full text-left">
                            <thead>
                            <tr class="border-b">
                                <th class="py-2">Name</th>
                                <th class="py-2">Type</th>
                                <th class="py-2">Status</th>
                                <th class="py-2">Versions</th>
                                <th class="py-2">Downloads</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($items as $item)
                                <tr class="border-b">
                                    <td class="py-2">
                                        <a class="text-indigo-600 hover:underline"
                                           href="{{ route('developer.items.show', $item) }}">{{ $item->name }}</a>
                                        <span class="text-gray-500 text-sm">({{ $item->slug }})</span>
                                    </td>
                                    <td class="py-2">{{ $item->type }}</td>
                                    <td class="py-2">{{ $item->status }}</td>
                                    <td class="py-2">{{ $item->versions->count() }}</td>
                                    <td class="py-2">{{ $item->downloads }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

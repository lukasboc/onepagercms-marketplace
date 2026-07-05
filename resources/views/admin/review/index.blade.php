<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Review queue</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($pendingVersions->isEmpty())
                        <p>Nothing to review. 🎉</p>
                    @else
                        <table class="w-full text-left">
                            <thead>
                            <tr class="border-b">
                                <th class="py-2">Item</th>
                                <th class="py-2">Type</th>
                                <th class="py-2">Version</th>
                                <th class="py-2">Developer</th>
                                <th class="py-2">Submitted</th>
                                <th class="py-2"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($pendingVersions as $version)
                                <tr class="border-b">
                                    <td class="py-2">{{ $version->item->name }}
                                        <span class="text-gray-500 text-sm">({{ $version->item->slug }})</span></td>
                                    <td class="py-2">{{ $version->item->type }}</td>
                                    <td class="py-2">{{ $version->version }}</td>
                                    <td class="py-2">{{ $version->item->user->name }}</td>
                                    <td class="py-2">{{ $version->created_at->diffForHumans() }}</td>
                                    <td class="py-2">
                                        <a class="text-indigo-600 hover:underline"
                                           href="{{ route('admin.review.show', $version) }}">Review</a>
                                    </td>
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

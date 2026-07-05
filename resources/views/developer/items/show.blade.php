<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $item->name }} <span class="text-gray-500 text-base">({{ $item->slug }}, {{ $item->type }},
                {{ $item->status }})</span>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-3">Versions</h3>
                    <table class="w-full text-left">
                        <thead>
                        <tr class="border-b">
                            <th class="py-2">Version</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Changelog</th>
                            <th class="py-2">Submitted</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($item->versions as $version)
                            <tr class="border-b">
                                <td class="py-2">{{ $version->version }}</td>
                                <td class="py-2">{{ $version->status }}</td>
                                <td class="py-2">{{ $version->changelog }}</td>
                                <td class="py-2">{{ $version->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-3">Submit new version</h3>
                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-100 text-red-800 rounded">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form method="post" action="{{ route('developer.items.versions.store', $item) }}"
                          enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="zip">ZIP archive</label>
                            <input type="file" name="zip" id="zip" accept=".zip" required class="mt-1 block w-full">
                        </div>
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="changelog">Changelog</label>
                            <textarea name="changelog" id="changelog" rows="4" required
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('changelog') }}</textarea>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            Submit version for review
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-3">Review notes</h3>
                    @if ($item->reviewNotes->isEmpty())
                        <p class="text-gray-500">No review notes yet.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($item->reviewNotes as $note)
                                <li class="border-b pb-2">
                                    <span class="font-medium">{{ ucfirst($note->action) }}</span>
                                    @if ($note->version)
                                        <span class="text-gray-500">({{ $note->version->version }})</span>
                                    @endif
                                    — {{ $note->note }}
                                    <span class="text-gray-400 text-sm">{{ $note->created_at->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

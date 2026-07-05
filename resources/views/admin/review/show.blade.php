<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Review: {{ $version->item->name }} {{ $version->version }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-2">
                    <p><strong>Slug:</strong> {{ $version->item->slug }}</p>
                    <p><strong>Type:</strong> {{ $version->item->type }}</p>
                    <p><strong>Developer:</strong> {{ $version->item->user->name }}
                        ({{ $version->item->user->email }})</p>
                    <p><strong>Paid:</strong> {{ $version->item->is_paid ? 'yes' : 'no' }}
                        @if ($version->item->purchase_url)
                            — <a class="text-indigo-600 hover:underline" target="_blank" rel="noopener"
                                 href="{{ $version->item->purchase_url }}">{{ $version->item->purchase_url }}</a>
                        @endif
                    </p>
                    <p><strong>Summary:</strong> {{ $version->item->summary }}</p>
                    <p><strong>Description:</strong> {{ $version->item->description }}</p>
                    <p><strong>Changelog:</strong> {{ $version->changelog }}</p>
                    <p><strong>Requires OPCMS:</strong> {{ $version->requires_opcms ?? '—' }},
                        <strong>PHP:</strong> {{ $version->requires_php ?? '—' }}</p>
                    <p>
                        <a class="text-indigo-600 hover:underline"
                           href="{{ route('admin.review.download', $version) }}">Download review ZIP</a>
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <form method="post" action="{{ route('admin.review.approve', $version) }}" class="space-y-3">
                        @csrf
                        <label class="block font-medium text-sm text-gray-700" for="approve-note">Note
                            (optional)</label>
                        <textarea name="note" id="approve-note" rows="3"
                                  class="block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                        <button type="submit"
                                class="px-4 py-2 bg-green-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500">
                            Approve
                        </button>
                    </form>
                    <form method="post" action="{{ route('admin.review.reject', $version) }}" class="space-y-3">
                        @csrf
                        <label class="block font-medium text-sm text-gray-700" for="reject-note">Rejection reason
                            (required)</label>
                        <textarea name="note" id="reject-note" rows="3" required
                                  class="block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                            Reject
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-3">Previous review notes</h3>
                    @if ($version->item->reviewNotes->isEmpty())
                        <p class="text-gray-500">None.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($version->item->reviewNotes as $note)
                                <li class="border-b pb-2">
                                    <span class="font-medium">{{ ucfirst($note->action) }}</span> — {{ $note->note }}
                                    <span class="text-gray-400 text-sm">
                                        by {{ $note->reviewer?->name ?? 'system' }},
                                        {{ $note->created_at->diffForHumans() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

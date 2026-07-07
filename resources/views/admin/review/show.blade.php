<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Review: {{ $version->item->name }} {{ $version->version }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-md px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

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
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-3">Automated checks</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Results are advisory — they flag issues for review and never approve or reject a submission.
                    </p>
                    <ul class="divide-y">
                        @foreach ($checkLabels as $check => $label)
                            @php($result = $checks->get($check))
                            @php($badgeClass = $result === null ? '' : match ($result->status) {
                                \App\Models\VersionCheck::STATUS_PASSED => 'bg-green-100 text-green-800',
                                \App\Models\VersionCheck::STATUS_WARNING => 'bg-yellow-100 text-yellow-800',
                                \App\Models\VersionCheck::STATUS_FAILED => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-600',
                            })
                            <li class="py-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-1">
                                    <p>
                                        <span class="font-medium">{{ $label }}</span>
                                        @if ($result === null)
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold border border-gray-300 text-gray-500 ml-2">Not run</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold ml-2 {{ $badgeClass }}">{{ ucfirst($result->status) }}</span>
                                        @endif
                                    </p>
                                    @if ($result !== null)
                                        <p class="text-gray-400 text-sm">
                                            by {{ $result->runner?->name ?? 'system' }},
                                            {{ $result->updated_at->diffForHumans() }}
                                        </p>
                                        @if (count($result->findings) > 15)
                                            <details class="text-sm">
                                                <summary class="cursor-pointer text-gray-600">{{ count($result->findings) }} findings</summary>
                                                <ul class="list-disc ml-5 mt-1 space-y-0.5">
                                                    @foreach ($result->findings as $finding)
                                                        <li>{{ $finding }}</li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @else
                                            <ul class="list-disc ml-5 text-sm space-y-0.5">
                                                @foreach ($result->findings as $finding)
                                                    <li>{{ $finding }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    @endif
                                </div>
                                <form method="post" action="{{ route('admin.review.checks.run', [$version, $check]) }}" class="shrink-0">
                                    @csrf
                                    <button type="submit"
                                            class="px-4 py-2 bg-indigo-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                                        {{ $result === null ? 'Run check' : 'Re-run' }}
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
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

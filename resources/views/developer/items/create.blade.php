<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Submit new item</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="mb-4 text-sm text-gray-600">
                        Upload your plugin or theme as a ZIP archive containing a <code>plugin.json</code> or
                        <code>theme.json</code> manifest. Name, slug, type and version are read from the manifest.
                        Every submission is reviewed by our team before it is listed.
                    </p>

                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-100 text-red-800 rounded">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="post" action="{{ route('developer.items.store') }}" enctype="multipart/form-data"
                          class="space-y-4">
                        @csrf
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="zip">ZIP archive</label>
                            <input type="file" name="zip" id="zip" accept=".zip" required class="mt-1 block w-full">
                        </div>
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="summary">Short summary</label>
                            <input type="text" name="summary" id="summary" maxlength="300" required
                                   value="{{ old('summary') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="description">Description</label>
                            <textarea name="description" id="description" rows="6"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('description') }}</textarea>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="hidden" name="is_paid" value="0">
                                <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid'))
                                       class="rounded border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">This is a paid item (sold on my own website)</span>
                            </label>
                        </div>
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="purchase_url">Purchase URL
                                (required for paid items)</label>
                            <input type="url" name="purchase_url" id="purchase_url" value="{{ old('purchase_url') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <p class="mt-1 text-sm text-gray-500">Paid items are listed with this link; the download,
                                license validation and updates run through your own server
                                (<code>update_endpoint</code> in your manifest).</p>
                        </div>
                        <div>
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Submit for review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

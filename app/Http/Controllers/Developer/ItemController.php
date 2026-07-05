<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Services\ZipManifestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = $request->user()->items()->with('versions')->latest()->get();

        return view('developer.items.index', compact('items'));
    }

    public function create(): View
    {
        return view('developer.items.create');
    }

    public function store(Request $request, ZipManifestService $zipService): RedirectResponse
    {
        $validated = $request->validate([
            'zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'summary' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
            'is_paid' => ['nullable', 'boolean'],
            'purchase_url' => ['nullable', 'url', 'max:255', 'required_if:is_paid,1'],
            'changelog' => ['nullable', 'string', 'max:5000'],
        ]);

        $inspection = $zipService->inspect($request->file('zip')->getRealPath());
        if (!$inspection['ok']) {
            return back()->withErrors(['zip' => $inspection['error']])->withInput();
        }
        $manifest = $inspection['manifest'];
        $isPaid = (bool) ($validated['is_paid'] ?? false);

        if (Item::where('slug', $manifest['slug'])->exists()) {
            return back()->withErrors(['zip' => 'This slug is already taken. To publish an update, open your item and submit a new version.'])->withInput();
        }
        if ($isPaid && empty($manifest['update_endpoint'])) {
            return back()->withErrors(['zip' => 'Paid items must declare an "update_endpoint" in their manifest so customers can receive updates from your server.'])->withInput();
        }

        $zipPath = $request->file('zip')->storeAs(
            "items/{$manifest['slug']}",
            "{$manifest['slug']}-{$manifest['version']}.zip",
            'local'
        );

        $item = Item::create([
            'user_id' => $request->user()->id,
            'type' => $manifest['type'],
            'slug' => $manifest['slug'],
            'name' => $manifest['name'],
            'summary' => $validated['summary'],
            'description' => $validated['description'] ?? ($manifest['description'] ?? null),
            'is_paid' => $isPaid,
            'purchase_url' => $isPaid ? $validated['purchase_url'] : null,
            'status' => Item::STATUS_PENDING,
        ]);

        $item->versions()->create([
            'version' => $manifest['version'],
            'zip_path' => $zipPath,
            'changelog' => $validated['changelog'] ?? 'Initial release.',
            'requires_opcms' => $manifest['requires_opcms'] ?? null,
            'requires_php' => $manifest['requires_php'] ?? null,
            'status' => ItemVersion::STATUS_PENDING,
        ]);

        return redirect()->route('developer.items.show', $item)
            ->with('status', 'Your submission was received and is waiting for review.');
    }

    public function show(Request $request, Item $item): View
    {
        abort_unless($item->user_id === $request->user()->id, 403);
        $item->load(['versions' => fn ($q) => $q->latest(), 'reviewNotes.reviewer']);

        return view('developer.items.show', compact('item'));
    }
}

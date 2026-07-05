<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Services\ZipManifestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ItemVersionController extends Controller
{
    public function store(Request $request, Item $item, ZipManifestService $zipService): RedirectResponse
    {
        abort_unless($item->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'changelog' => ['required', 'string', 'max:5000'],
        ]);

        $inspection = $zipService->inspect($request->file('zip')->getRealPath());
        if (!$inspection['ok']) {
            return back()->withErrors(['zip' => $inspection['error']])->withInput();
        }
        $manifest = $inspection['manifest'];

        if ($manifest['slug'] !== $item->slug) {
            return back()->withErrors(['zip' => "The manifest slug \"{$manifest['slug']}\" does not match this item ({$item->slug})."])->withInput();
        }

        $highestVersion = $item->versions->sortByDesc(fn (ItemVersion $v) => $v->version, SORT_NATURAL)->first();
        if ($highestVersion !== null && version_compare($manifest['version'], $highestVersion->version, '<=')) {
            return back()->withErrors(['zip' => "The new version ({$manifest['version']}) must be higher than the latest submitted version ({$highestVersion->version})."])->withInput();
        }

        $zipPath = $request->file('zip')->storeAs(
            "items/{$item->slug}",
            "{$item->slug}-{$manifest['version']}.zip",
            'local'
        );

        $item->versions()->create([
            'version' => $manifest['version'],
            'zip_path' => $zipPath,
            'changelog' => $validated['changelog'],
            'requires_opcms' => $manifest['requires_opcms'] ?? null,
            'requires_php' => $manifest['requires_php'] ?? null,
            'status' => ItemVersion::STATUS_PENDING,
        ]);

        return redirect()->route('developer.items.show', $item)
            ->with('status', 'The new version was submitted and is waiting for review.');
    }
}

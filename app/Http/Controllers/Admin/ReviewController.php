<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ItemListingChanged;
use App\Mail\ItemReviewed;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\VersionCheck;
use App\Services\VersionCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ReviewController extends Controller
{
    const CHECK_LABELS = [
        VersionCheck::CHECK_MANIFEST => 'Manifest & completeness',
        VersionCheck::CHECK_HOOKS => 'CMS hooks',
        VersionCheck::CHECK_UNINSTALL => 'Uninstall actions',
        VersionCheck::CHECK_MALWARE => 'Malware scan',
        VersionCheck::CHECK_FUNCTIONALITY => 'Functionality smoke test',
    ];

    public function index(): View
    {
        $pendingVersions = ItemVersion::where('status', ItemVersion::STATUS_PENDING)
            ->with('item.user')
            ->oldest()
            ->get();

        return view('admin.review.index', compact('pendingVersions'));
    }

    public function show(ItemVersion $version): View
    {
        $version->load('item.user', 'item.reviewNotes.reviewer', 'checks.runner');

        return view('admin.review.show', [
            'version' => $version,
            'checks' => $version->checks->keyBy('check'),
            'checkLabels' => self::CHECK_LABELS,
        ]);
    }

    public function runCheck(Request $request, ItemVersion $version, string $check, VersionCheckService $checker): RedirectResponse
    {
        $result = $checker->run($version, $check, $request->user()->id);

        return redirect()->route('admin.review.show', $version)
            ->with('status', 'Check "'.self::CHECK_LABELS[$check].'" finished: '.$result->status.'.');
    }

    public function approve(Request $request, ItemVersion $version): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:5000']]);

        $version->update(['status' => ItemVersion::STATUS_APPROVED]);
        if ($version->item->status === Item::STATUS_PENDING) {
            $version->item->update(['status' => Item::STATUS_APPROVED]);
        }

        $note = $validated['note'] ?? 'Approved.';
        $version->item->reviewNotes()->create([
            'item_version_id' => $version->id,
            'reviewer_id' => $request->user()->id,
            'action' => 'approve',
            'note' => $note,
        ]);

        Mail::to($version->item->user)->send(new ItemReviewed($version, 'approved', $note));

        return redirect()->route('admin.review.index')->with('status', "Approved {$version->item->slug} {$version->version}.");
    }

    public function reject(Request $request, ItemVersion $version): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:5000']]);

        $version->update(['status' => ItemVersion::STATUS_REJECTED]);
        if ($version->item->status === Item::STATUS_PENDING
            && ! $version->item->versions()->whereIn('status', [ItemVersion::STATUS_PENDING, ItemVersion::STATUS_APPROVED])->exists()) {
            $version->item->update(['status' => Item::STATUS_REJECTED]);
        }

        $version->item->reviewNotes()->create([
            'item_version_id' => $version->id,
            'reviewer_id' => $request->user()->id,
            'action' => 'reject',
            'note' => $validated['note'],
        ]);

        Mail::to($version->item->user)->send(new ItemReviewed($version, 'rejected', $validated['note']));

        return redirect()->route('admin.review.index')->with('status', "Rejected {$version->item->slug} {$version->version}.");
    }

    public function delist(Request $request, Item $item): RedirectResponse
    {
        abort_unless($item->status === Item::STATUS_APPROVED, 404);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:5000']]);

        $item->update(['status' => Item::STATUS_DELISTED]);

        $note = $validated['note'] ?? 'Delisted.';
        $item->reviewNotes()->create([
            'item_version_id' => null,
            'reviewer_id' => $request->user()->id,
            'action' => 'delist',
            'note' => $note,
        ]);

        Mail::to($item->user)->send(new ItemListingChanged($item, 'delisted', $note));

        return redirect()->route('catalog.show', $item->slug)->with('status', "Delisted {$item->slug}.");
    }

    public function relist(Request $request, Item $item): RedirectResponse
    {
        abort_unless($item->status === Item::STATUS_DELISTED, 404);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:5000']]);

        $item->update(['status' => Item::STATUS_APPROVED]);

        $note = $validated['note'] ?? 'Relisted.';
        $item->reviewNotes()->create([
            'item_version_id' => null,
            'reviewer_id' => $request->user()->id,
            'action' => 'relist',
            'note' => $note,
        ]);

        Mail::to($item->user)->send(new ItemListingChanged($item, 'relisted', $note));

        return redirect()->route('catalog.show', $item->slug)->with('status', "Relisted {$item->slug}.");
    }

    public function download(ItemVersion $version)
    {
        abort_if($version->zip_path === null, 404);
        abort_unless(Storage::disk('local')->exists($version->zip_path), 404);

        return Storage::disk('local')->download($version->zip_path, "{$version->item->slug}-{$version->version}-review.zip");
    }
}

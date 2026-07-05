<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Item::approved()
            ->whereHas('versions', fn ($q) => $q->where('status', ItemVersion::STATUS_APPROVED))
            ->with(['versions' => fn ($q) => $q->where('status', ItemVersion::STATUS_APPROVED), 'user', 'screenshots'])
            ->orderByDesc('downloads');

        if (in_array($request->query('type'), ['plugin', 'theme'], true)) {
            $query->where('type', $request->query('type'));
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Item $item) => $this->itemSummary($item))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $item = Item::approved()->where('slug', $slug)->with(['user', 'screenshots'])->firstOrFail();
        $latest = $item->latestApprovedVersion();
        abort_if($latest === null, 404);

        return response()->json(array_merge($this->itemSummary($item), [
            'description' => $item->description,
            'screenshots' => $item->screenshots->map(fn ($s) => Storage::url($s->path))->values(),
            'latest_version' => [
                'version' => $latest->version,
                'changelog' => $latest->changelog,
                'requires_opcms' => $latest->requires_opcms,
                'requires_php' => $latest->requires_php,
                'released_at' => optional($latest->updated_at)->toIso8601String(),
            ],
        ]));
    }

    public function download(string $slug)
    {
        $item = Item::approved()->where('slug', $slug)->firstOrFail();
        abort_if($item->is_paid, 403, 'Paid items are distributed by their developer.');

        $latest = $item->latestApprovedVersion();
        abort_if($latest === null || $latest->zip_path === null, 404);
        abort_unless(Storage::disk('local')->exists($latest->zip_path), 404);

        $item->increment('downloads');

        return Storage::disk('local')->download($latest->zip_path, "{$item->slug}-{$latest->version}.zip");
    }

    private function itemSummary(Item $item): array
    {
        $latest = $item->latestApprovedVersion();

        return [
            'slug' => $item->slug,
            'type' => $item->type,
            'name' => $item->name,
            'summary' => $item->summary,
            'author' => $item->user->name,
            'is_paid' => $item->is_paid,
            'purchase_url' => $item->purchase_url,
            'latest_version' => $latest?->version,
            'requires_opcms' => $latest?->requires_opcms,
            'downloads' => $item->downloads,
            'screenshot_url' => $item->screenshots->first() ? Storage::url($item->screenshots->first()->path) : null,
            'download_url' => $item->is_paid ? null : url("/api/v1/items/{$item->slug}/download"),
            'detail_url' => url("/api/v1/items/{$item->slug}"),
        ];
    }
}

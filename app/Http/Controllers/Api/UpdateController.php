<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $items = $request->query('items', []);
        if (!is_array($items)) {
            $items = [$items];
        }

        $updates = [];
        foreach (array_slice($items, 0, 100) as $entry) {
            if (!is_string($entry) || !str_contains($entry, ':')) {
                continue;
            }
            [$slug, $installedVersion] = explode(':', $entry, 2);
            if (!preg_match('/^[a-z0-9][a-z0-9\-]{2,49}$/', $slug)) {
                continue;
            }

            $item = Item::approved()->where('slug', $slug)->first();
            if ($item === null) {
                continue;
            }
            $latest = $item->latestApprovedVersion();
            if ($latest === null || version_compare($latest->version, $installedVersion, '<=')) {
                continue;
            }

            $updates[$slug] = [
                'new_version' => $latest->version,
                'download_url' => $item->is_paid ? null : url("/api/v1/items/{$item->slug}/download"),
                'purchase_url' => $item->purchase_url,
                'changelog' => $latest->changelog,
            ];
        }

        return response()->json(['updates' => $updates]);
    }
}

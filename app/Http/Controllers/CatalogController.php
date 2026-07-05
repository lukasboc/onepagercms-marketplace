<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVersion;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function landing(): View
    {
        $popular = $this->publicQuery()
            ->orderByDesc('downloads')
            ->take(6)
            ->get();

        $latest = $this->publicQuery()
            ->latest()
            ->take(6)
            ->get();

        $pluginCount = $this->publicQuery()->where('type', 'plugin')->count();
        $themeCount = $this->publicQuery()->where('type', 'theme')->count();

        return view('public.landing', compact('popular', 'latest', 'pluginCount', 'themeCount'));
    }

    public function plugins(Request $request): View
    {
        return $this->listByType('plugin', $request);
    }

    public function themes(Request $request): View
    {
        return $this->listByType('theme', $request);
    }

    public function show(string $slug): View
    {
        $item = $this->publicQuery()
            ->where('slug', $slug)
            ->with(['user', 'screenshots'])
            ->firstOrFail();

        $latest = $item->latestApprovedVersion();
        abort_if($latest === null, 404);

        $approvedVersions = $item->versions
            ->where('status', ItemVersion::STATUS_APPROVED)
            ->sortByDesc(fn (ItemVersion $version) => $version->version, SORT_NATURAL)
            ->values();

        return view('public.item-show', compact('item', 'latest', 'approvedVersions'));
    }

    public function developers(): View
    {
        return view('public.developers');
    }

    private function listByType(string $type, Request $request): View
    {
        $query = $this->publicQuery()->where('type', $type)->orderByDesc('downloads');

        $search = trim((string) $request->query('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        $items = $query->paginate(12)->withQueryString();

        return view('public.items', [
            'items' => $items,
            'type' => $type,
            'search' => $search,
        ]);
    }

    private function publicQuery()
    {
        return Item::approved()
            ->whereHas('versions', fn ($q) => $q->where('status', ItemVersion::STATUS_APPROVED))
            ->with(['versions' => fn ($q) => $q->where('status', ItemVersion::STATUS_APPROVED), 'user']);
    }
}

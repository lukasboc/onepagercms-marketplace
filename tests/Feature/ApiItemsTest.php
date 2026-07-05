<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_approved_items_with_approved_versions(): void
    {
        $approved = Item::factory()->create(['slug' => 'free-plugin']);
        ItemVersion::factory()->for($approved)->create(['version' => '1.0.0']);

        $pendingItem = Item::factory()->pending()->create(['slug' => 'pending-plugin']);
        ItemVersion::factory()->for($pendingItem)->pending()->create();

        $approvedItemNoVersion = Item::factory()->create(['slug' => 'no-version-plugin']);
        ItemVersion::factory()->for($approvedItemNoVersion)->pending()->create();

        $response = $this->getJson('/api/v1/items');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'free-plugin')
            ->assertJsonPath('data.0.latest_version', '1.0.0');
    }

    public function test_index_filters_by_type_and_search(): void
    {
        $plugin = Item::factory()->create(['slug' => 'seo-tool', 'name' => 'SEO Tool']);
        ItemVersion::factory()->for($plugin)->create();
        $theme = Item::factory()->theme()->create(['slug' => 'dark-theme', 'name' => 'Dark Theme']);
        ItemVersion::factory()->for($theme)->create();

        $this->getJson('/api/v1/items?type=theme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'dark-theme');

        $this->getJson('/api/v1/items?search=seo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'seo-tool');
    }

    public function test_show_returns_detail_with_latest_version(): void
    {
        $item = Item::factory()->create(['slug' => 'multi-version']);
        ItemVersion::factory()->for($item)->create(['version' => '1.0.0']);
        ItemVersion::factory()->for($item)->create(['version' => '1.10.0']);
        ItemVersion::factory()->for($item)->pending()->create(['version' => '2.0.0']);

        $this->getJson('/api/v1/items/multi-version')
            ->assertOk()
            ->assertJsonPath('latest_version.version', '1.10.0')
            ->assertJsonPath('download_url', url('/api/v1/items/multi-version/download'));
    }

    public function test_paid_item_has_no_download_url_and_download_returns_403(): void
    {
        $item = Item::factory()->paid()->create(['slug' => 'pro-plugin']);
        ItemVersion::factory()->for($item)->create(['zip_path' => 'items/pro-plugin/pro-plugin-1.0.0.zip']);

        $this->getJson('/api/v1/items/pro-plugin')
            ->assertOk()
            ->assertJsonPath('is_paid', true)
            ->assertJsonPath('download_url', null)
            ->assertJsonPath('purchase_url', 'https://developer.example/buy');

        $this->getJson('/api/v1/items/pro-plugin/download')->assertForbidden();
    }

    public function test_download_streams_zip_and_counts(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('items/free-plugin/free-plugin-1.0.0.zip', 'zip-bytes');

        $item = Item::factory()->create(['slug' => 'free-plugin']);
        ItemVersion::factory()->for($item)->create(['zip_path' => 'items/free-plugin/free-plugin-1.0.0.zip']);

        $this->get('/api/v1/items/free-plugin/download')
            ->assertOk()
            ->assertDownload('free-plugin-1.0.0.zip');

        $this->assertSame(1, $item->fresh()->downloads);
    }

    public function test_unknown_item_returns_404(): void
    {
        $this->getJson('/api/v1/items/does-not-exist')->assertNotFound();
    }

    public function test_updates_endpoint_reports_newer_versions_only(): void
    {
        $item = Item::factory()->create(['slug' => 'my-plugin']);
        ItemVersion::factory()->for($item)->create(['version' => '1.1.0', 'changelog' => 'Fixes']);

        $paid = Item::factory()->paid()->create(['slug' => 'pro-plugin']);
        ItemVersion::factory()->for($paid)->create(['version' => '2.0.0']);

        $response = $this->getJson('/api/v1/updates?items[]=my-plugin:1.0.0&items[]=pro-plugin:2.0.0&items[]=unknown:1.0.0');

        $response->assertOk()
            ->assertJsonPath('updates.my-plugin.new_version', '1.1.0')
            ->assertJsonPath('updates.my-plugin.download_url', url('/api/v1/items/my-plugin/download'))
            ->assertJsonMissingPath('updates.pro-plugin')
            ->assertJsonMissingPath('updates.unknown');
    }
}

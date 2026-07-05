<?php

namespace Tests\Feature;

use App\Mail\ItemReviewed;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makePluginZip(string $slug, string $version): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'opcms-test-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('plugin.json', json_encode([
            'slug' => $slug,
            'type' => 'plugin',
            'name' => 'Test Plugin',
            'version' => $version,
            'main' => 'main.php',
            'requires_opcms' => '1.2.0',
        ]));
        $zip->addFromString('main.php', "<?php\nadd_action('opcms_body_end', function () { echo '<!-- test -->'; });\n");
        $zip->close();

        return new UploadedFile($path, "{$slug}.zip", 'application/zip', null, true);
    }

    public function test_full_submission_and_review_flow(): void
    {
        Storage::fake('local');
        Mail::fake();

        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        // Developer submits a new item.
        $this->actingAs($developer)
            ->post(route('developer.items.store'), [
                'zip' => $this->makePluginZip('test-plugin', '1.0.0'),
                'summary' => 'A test plugin.',
                'is_paid' => '0',
            ])
            ->assertRedirect();

        $item = Item::where('slug', 'test-plugin')->firstOrFail();
        $this->assertSame(Item::STATUS_PENDING, $item->status);
        $version = $item->versions()->firstOrFail();
        $this->assertSame(ItemVersion::STATUS_PENDING, $version->status);
        Storage::disk('local')->assertExists($version->zip_path);

        // Not visible in the API while pending.
        $this->getJson('/api/v1/items')->assertOk()->assertJsonCount(0, 'data');

        // Developer cannot access the admin review queue.
        $this->actingAs($developer)->get(route('admin.review.index'))->assertForbidden();

        // Admin approves.
        $this->actingAs($admin)
            ->post(route('admin.review.approve', $version), ['note' => 'Looks good.'])
            ->assertRedirect(route('admin.review.index'));

        $this->assertSame(Item::STATUS_APPROVED, $item->fresh()->status);
        $this->assertSame(ItemVersion::STATUS_APPROVED, $version->fresh()->status);
        Mail::assertSent(ItemReviewed::class);

        // Now listed in the API.
        $this->getJson('/api/v1/items')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'test-plugin')
            ->assertJsonPath('data.0.latest_version', '1.0.0');

        // Developer submits an update, admin approves, update check reports it.
        $this->actingAs($developer)
            ->post(route('developer.items.versions.store', $item), [
                'zip' => $this->makePluginZip('test-plugin', '1.1.0'),
                'changelog' => 'Bugfixes.',
            ])
            ->assertRedirect();

        $newVersion = $item->versions()->where('version', '1.1.0')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.review.approve', $newVersion))
            ->assertRedirect();

        $this->getJson('/api/v1/updates?items[]=test-plugin:1.0.0')
            ->assertOk()
            ->assertJsonPath('updates.test-plugin.new_version', '1.1.0');
    }

    public function test_rejected_submission_gets_note_and_mail(): void
    {
        Storage::fake('local');
        Mail::fake();

        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($developer)->post(route('developer.items.store'), [
            'zip' => $this->makePluginZip('bad-plugin', '1.0.0'),
            'summary' => 'A test plugin.',
            'is_paid' => '0',
        ]);

        $item = Item::where('slug', 'bad-plugin')->firstOrFail();
        $version = $item->versions()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.review.reject', $version), ['note' => 'Contains obfuscated code.'])
            ->assertRedirect();

        $this->assertSame(Item::STATUS_REJECTED, $item->fresh()->status);
        $this->assertSame(ItemVersion::STATUS_REJECTED, $version->fresh()->status);
        $this->assertSame('reject', $item->reviewNotes()->first()->action);
        Mail::assertSent(ItemReviewed::class);
    }

    public function test_invalid_zip_is_rejected_on_submission(): void
    {
        Storage::fake('local');
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $path = tempnam(sys_get_temp_dir(), 'opcms-noman-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'no manifest here');
        $zip->close();

        $this->actingAs($developer)
            ->from(route('developer.items.create'))
            ->post(route('developer.items.store'), [
                'zip' => new UploadedFile($path, 'x.zip', 'application/zip', null, true),
                'summary' => 'A test plugin.',
                'is_paid' => '0',
            ])
            ->assertRedirect(route('developer.items.create'))
            ->assertSessionHasErrors('zip');

        $this->assertSame(0, Item::count());
    }

    public function test_paid_item_requires_purchase_url_and_update_endpoint(): void
    {
        Storage::fake('local');
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        // Manifest without update_endpoint → rejected for paid items.
        $this->actingAs($developer)
            ->from(route('developer.items.create'))
            ->post(route('developer.items.store'), [
                'zip' => $this->makePluginZip('paid-plugin', '1.0.0'),
                'summary' => 'A paid plugin.',
                'is_paid' => '1',
                'purchase_url' => 'https://developer.example/buy',
            ])
            ->assertSessionHasErrors('zip');

        $this->assertSame(0, Item::count());
    }
}

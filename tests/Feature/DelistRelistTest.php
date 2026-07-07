<?php

namespace Tests\Feature;

use App\Mail\ItemListingChanged;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DelistRelistTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delist_approved_item(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $item = Item::factory()->create(['slug' => 'live-plugin']);
        $version = ItemVersion::factory()->for($item)->create(['version' => '1.0.0']);

        $this->actingAs($admin)
            ->post(route('admin.items.delist', $item), ['note' => 'DMCA complaint.'])
            ->assertRedirect(route('catalog.show', $item->slug));

        $this->assertSame(Item::STATUS_DELISTED, $item->fresh()->status);
        $this->assertSame(ItemVersion::STATUS_APPROVED, $version->fresh()->status);

        $note = $item->reviewNotes()->first();
        $this->assertSame('delist', $note->action);
        $this->assertSame('DMCA complaint.', $note->note);
        $this->assertNull($note->item_version_id);
        $this->assertSame($admin->id, $note->reviewer_id);

        Mail::assertSent(ItemListingChanged::class, function (ItemListingChanged $mail) use ($item) {
            return $mail->item->is($item)
                && $mail->decision === 'delisted'
                && $mail->hasTo($item->user->email);
        });

        $this->getJson('/api/v1/items')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/items/live-plugin')->assertNotFound();
        $this->get('/plugins')->assertOk()->assertDontSee($item->name);

        auth()->logout();
        $this->get('/items/live-plugin')->assertNotFound();
    }

    public function test_admin_can_view_and_relist_delisted_item(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $item = Item::factory()->create(['slug' => 'gone-plugin', 'status' => Item::STATUS_DELISTED]);
        ItemVersion::factory()->for($item)->create(['version' => '1.0.0']);

        $this->get('/items/gone-plugin')->assertNotFound();

        $this->actingAs($admin)
            ->get('/items/gone-plugin')
            ->assertOk()
            ->assertSee('Delisted')
            ->assertSee('Relist');

        $this->actingAs($admin)
            ->post(route('admin.items.relist', $item))
            ->assertRedirect(route('catalog.show', $item->slug));

        $this->assertSame(Item::STATUS_APPROVED, $item->fresh()->status);

        $note = $item->reviewNotes()->first();
        $this->assertSame('relist', $note->action);
        $this->assertSame('Relisted.', $note->note);

        Mail::assertSent(ItemListingChanged::class, function (ItemListingChanged $mail) use ($item) {
            return $mail->item->is($item)
                && $mail->decision === 'relisted'
                && $mail->hasTo($item->user->email);
        });

        $this->getJson('/api/v1/items')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'gone-plugin');
    }

    public function test_non_admin_cannot_delist_or_relist(): void
    {
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $item = Item::factory()->create();
        ItemVersion::factory()->for($item)->create();

        $this->post(route('admin.items.delist', $item))->assertRedirect(route('login'));

        $this->actingAs($developer)->post(route('admin.items.delist', $item))->assertForbidden();
        $this->actingAs($developer)->post(route('admin.items.relist', $item))->assertForbidden();

        $this->assertSame(Item::STATUS_APPROVED, $item->fresh()->status);
    }

    public function test_delist_requires_approved_item_and_relist_requires_delisted_item(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $pending = Item::factory()->pending()->create();
        $this->actingAs($admin)->post(route('admin.items.delist', $pending))->assertNotFound();

        $approved = Item::factory()->create();
        $this->actingAs($admin)->post(route('admin.items.relist', $approved))->assertNotFound();
    }
}

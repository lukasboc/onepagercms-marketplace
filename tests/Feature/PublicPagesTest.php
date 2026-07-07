<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_is_public_and_shows_approved_items(): void
    {
        $item = Item::factory()->create(['slug' => 'public-plugin', 'name' => 'Public Plugin']);
        ItemVersion::factory()->for($item)->create();

        $pending = Item::factory()->pending()->create(['slug' => 'hidden-plugin', 'name' => 'Hidden Plugin']);
        ItemVersion::factory()->for($pending)->pending()->create();

        $this->get('/')
            ->assertOk()
            ->assertSee('Public Plugin')
            ->assertDontSee('Hidden Plugin');
    }

    public function test_plugin_and_theme_lists_are_public_and_filtered_by_type(): void
    {
        $plugin = Item::factory()->create(['slug' => 'a-plugin', 'name' => 'A Plugin']);
        ItemVersion::factory()->for($plugin)->create();
        $theme = Item::factory()->theme()->create(['slug' => 'a-theme', 'name' => 'A Theme']);
        ItemVersion::factory()->for($theme)->create();

        $this->get('/plugins')->assertOk()->assertSee('A Plugin')->assertDontSee('A Theme');
        $this->get('/themes')->assertOk()->assertSee('A Theme')->assertDontSee('A Plugin');
    }

    public function test_plugin_list_search_works(): void
    {
        $seo = Item::factory()->create(['slug' => 'seo-kit', 'name' => 'SEO Kit']);
        ItemVersion::factory()->for($seo)->create();
        $forms = Item::factory()->create(['slug' => 'form-builder', 'name' => 'Form Builder']);
        ItemVersion::factory()->for($forms)->create();

        $this->get('/plugins?search=seo')->assertOk()->assertSee('SEO Kit')->assertDontSee('Form Builder');
    }

    public function test_item_detail_page_is_public_and_shows_download_for_free_items(): void
    {
        $item = Item::factory()->create(['slug' => 'free-plugin', 'name' => 'Free Plugin']);
        ItemVersion::factory()->for($item)->create(['version' => '1.2.0']);

        $this->get('/items/free-plugin')
            ->assertOk()
            ->assertSee('Free Plugin')
            ->assertSee('Download v1.2.0')
            ->assertSee(url('/api/v1/items/free-plugin/download'));
    }

    public function test_paid_item_detail_shows_purchase_link_instead_of_download(): void
    {
        $item = Item::factory()->paid()->create(['slug' => 'pro-plugin', 'name' => 'Pro Plugin']);
        ItemVersion::factory()->for($item)->create();

        $this->get('/items/pro-plugin')
            ->assertOk()
            ->assertSee('Buy on developer site')
            ->assertSee('https://developer.example/buy')
            ->assertDontSee(url('/api/v1/items/pro-plugin/download'));
    }

    public function test_pending_item_detail_returns_404(): void
    {
        $pending = Item::factory()->pending()->create(['slug' => 'hidden-plugin']);
        ItemVersion::factory()->for($pending)->pending()->create();

        $this->get('/items/hidden-plugin')->assertNotFound();
    }

    public function test_developers_guide_is_public(): void
    {
        $this->get('/developers')
            ->assertOk()
            ->assertSee('Publish your plugin or theme')
            ->assertSee('plugin.json');
    }

    public function test_legal_notice_is_public_and_shows_operator_from_config(): void
    {
        config([
            'legal.operator.name' => 'Test Operator',
            'legal.operator.email' => 'legal@example.test',
        ]);

        $this->get('/legal-notice')
            ->assertOk()
            ->assertSee('Legal notice')
            ->assertSee('Test Operator')
            ->assertDontSee('legal@example.test')
            ->assertSee('legal [at] example [punkt] test');
    }

    public function test_privacy_policy_is_public_and_shows_controller_from_config(): void
    {
        config([
            'legal.operator.name' => 'Test Operator',
            'legal.operator.email' => 'legal@example.test',
        ]);

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Privacy policy')
            ->assertSee('Test Operator')
            ->assertSee('GDPR')
            ->assertDontSee('legal@example.test')
            ->assertSee('legal [at] example [punkt] test');
    }

    public function test_footer_links_to_legal_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('legal-notice'))
            ->assertSee(route('privacy-policy'));
    }

    public function test_submission_still_requires_login(): void
    {
        $this->get(route('developer.items.create'))->assertRedirect(route('login'));
        $this->post(route('developer.items.store'))->assertRedirect(route('login'));
    }
}

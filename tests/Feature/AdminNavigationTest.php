<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_redirected_from_dashboard_to_the_review_queue(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertRedirect(route('admin.review.index'));
    }

    public function test_developer_is_redirected_from_dashboard_to_their_items(): void
    {
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($developer)->get('/dashboard')
            ->assertRedirect(route('developer.items.index'));
    }

    public function test_admin_sees_review_queue_link_in_the_authenticated_navigation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('developer.items.index'))
            ->assertOk()
            ->assertSee('Review Queue');
    }

    public function test_developer_does_not_see_the_review_queue_link(): void
    {
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($developer)->get(route('developer.items.index'))
            ->assertOk()
            ->assertDontSee('Review Queue');
    }
}

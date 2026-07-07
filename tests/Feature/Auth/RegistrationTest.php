<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_spam_registration_is_blocked_by_honeypot(): void
    {
        config([
            'honeypot.enabled' => true,
            'honeypot.randomize_name_field_name' => false,
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'bot@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'my_name' => 'I am a bot',
        ]);

        $response->assertOk();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/register', []);
        }

        $this->post('/register', [])->assertStatus(429);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_public_and_uses_the_local_brand_shell(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('public')
            ->assertSee('/assets/ascanio/favicon.png', false)
            ->assertDontSee('fonts.bunny.net')
            ->assertDontSee('googletagmanager.com')
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/Home')
                ->where('canRegister', true)
                ->missing('auth')
                ->missing('sidebarOpen')
                ->missing('workspaces')
                ->missing('documents')
                ->missing('grants'));

        $this->get(route('workspaces.index'))->assertRedirect(route('login'));
    }

    public function test_public_page_does_not_serialize_the_signed_in_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/Home')
                ->missing('auth')
                ->missing('tenant')
                ->missing('workspaces')
                ->missing('documents'))
            ->assertDontSee($user->email);

        $this->get(route('login'))->assertRedirect(route('dashboard', absolute: false));
    }
}

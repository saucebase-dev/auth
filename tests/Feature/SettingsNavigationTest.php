<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_route_sends_the_user_to_the_settings_fragment(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('settings.profile'))
            ->assertRedirect(route('dashboard').'#settings/profile');
    }

    public function test_profile_leads_the_settings_sections(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('index'));

        $titles = array_column(
            $response->inertiaProps('settings.sections'),
            'title',
        );

        // Other modules contribute their own sections; Profile is ordered first.
        $this->assertSame('Profile', $titles[0]);
    }

    /**
     * The section is found by living in this module's `src/Settings` directory —
     * there is no registration call to keep in step with it.
     */
    public function test_the_profile_section_is_discovered_and_names_its_component(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('index'));

        $profile = collect($response->inertiaProps('settings.sections'))
            ->firstWhere('slug', 'profile');

        $this->assertNotNull($profile);
        $this->assertSame('Auth::SettingsProfile', $profile['component']);
    }
}

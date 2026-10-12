<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Auth\Database\Seeders\DatabaseSeeder;
use Modules\Auth\Filament\Pages\AuthenticationSettings;
use Modules\Auth\Filament\Resources\Users\Pages\CreateUser;
use Modules\Auth\Filament\Resources\Users\Pages\EditUser;
use Modules\Auth\Filament\Resources\Users\Pages\ListUsers;
use Modules\Auth\Filament\Resources\Users\UserResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Users are their own permission, and a staff member who manages users can never reach
 * an administrator: not their account, not their role, not by impersonating them. The
 * last line is that an administrator cannot remove their own role or account, so one
 * always remains.
 */
class UserAdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_seeder_creates_the_permissions(): void
    {
        $this->assertTrue(Permission::where('name', 'manage users')->exists());
        $this->assertTrue(Permission::where('name', 'impersonate users')->exists());
    }

    public function test_users_need_their_own_permission(): void
    {
        $this->actingAs($this->staff())->get(UserResource::getUrl('index'))->assertForbidden();
        $this->actingAs($this->staff('manage users'))->get(UserResource::getUrl('index'))->assertOk();
    }

    public function test_authentication_settings_are_site_settings(): void
    {
        $this->actingAs($this->staff('manage users'))->get(AuthenticationSettings::getUrl())->assertForbidden();
        $this->actingAs($this->staff('manage settings'))->get(AuthenticationSettings::getUrl())->assertOk();
    }

    public function test_a_user_manager_edits_users_but_never_an_admin(): void
    {
        $manager = $this->staff('manage users');

        $this->actingAs($manager)->get(UserResource::getUrl('edit', ['record' => $this->user()]))->assertOk();
        $this->actingAs($manager)->get(UserResource::getUrl('edit', ['record' => $this->admin()]))->assertForbidden();
    }

    public function test_a_user_manager_cannot_change_roles_even_with_a_forged_field(): void
    {
        $user = $this->user();
        $this->actingAs($this->staff('manage users'));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->assertFormFieldDoesNotExist('roles')
            ->fillForm(['name' => 'Renamed'])
            ->set('data.roles', [Role::findByName('admin')->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_a_user_created_by_a_user_manager_gets_the_default_role(): void
    {
        $this->actingAs($this->staff('manage users'));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Person',
                'email' => 'new@example.com',
                'password' => 'Secret!Pass123',
                'password_confirmation' => 'Secret!Pass123',
            ])
            ->set('data.roles', [Role::findByName('admin')->getKey()])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(['user'], $created->getRoleNames()->all());
    }

    public function test_an_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->assertFormFieldDisabled('roles')
            ->set('data.roles', [Role::findByName('user')->getKey()])
            ->call('save');

        $this->assertTrue($admin->refresh()->hasRole('admin'));
    }

    public function test_an_admin_still_changes_other_users_roles(): void
    {
        $user = $this->user();
        $this->actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['roles' => [Role::findByName('admin')->getKey()]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($user->refresh()->hasRole('admin'));
    }

    public function test_nobody_deletes_their_own_account_from_the_panel(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden(DeleteAction::class);

        Livewire::test(ListUsers::class)
            ->selectTableRecords([$admin->getKey()])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

        $this->assertModelExists($admin);
    }

    public function test_a_bulk_delete_by_a_user_manager_skips_admins(): void
    {
        $admin = $this->admin();
        $user = $this->user();
        $this->actingAs($this->staff('manage users'));

        Livewire::test(ListUsers::class)
            ->selectTableRecords([$admin->getKey(), $user->getKey()])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

        $this->assertModelExists($admin);
        $this->assertModelMissing($user);
    }

    public function test_impersonation_is_its_own_permission_and_never_reaches_an_admin(): void
    {
        $user = $this->user();
        $admin = $this->admin();

        $this->assertFalse(Gate::forUser($this->staff('manage users'))->allows('impersonate', $user));

        $support = $this->staff('impersonate users');
        $this->assertTrue(Gate::forUser($support)->allows('impersonate', $user));
        $this->assertFalse(Gate::forUser($support)->allows('impersonate', $admin));

        $this->assertTrue(Gate::forUser($this->admin())->allows('impersonate', $admin));
    }

    public function test_reimpersonating_follows_the_same_rule(): void
    {
        $this->actingAs($this->staff('impersonate users'))
            ->post(route('auth.impersonate.reimpersonate', ['userId' => $this->admin()->getKey()]))
            ->assertForbidden();
    }

    private function staff(string ...$permissions): User
    {
        return User::factory()->create(['email_verified_at' => now()])
            ->givePermissionTo(['access admin panel', ...$permissions]);
    }

    private function admin(): User
    {
        return User::factory()->create(['email_verified_at' => now()])->assignRole('admin');
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()])->assignRole('user');
    }
}

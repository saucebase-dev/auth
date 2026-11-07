<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\SocialAccount;
use Tests\TestCase;

/** Tokens stored before the `encrypted` cast must still read once the upgrade has run. */
class EncryptSocialAccountTokensMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require module_path('auth', 'database/migrations/1000_00_00_000007_encrypt_social_account_tokens.php');
    }

    public function test_plain_text_tokens_are_encrypted_and_read_back(): void
    {
        $user = $this->createUser();
        $this->migration()->down();

        $id = DB::table('social_accounts')->insertGetId([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => 'legacy-1',
            'provider_token' => 'plain-access',
            'provider_refresh_token' => 'plain-refresh',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('social_accounts')->insert([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'legacy-2',
            'provider_token' => null,
            'provider_refresh_token' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertNotSame('plain-access', DB::table('social_accounts')->where('id', $id)->value('provider_token'));
        $account = SocialAccount::findOrFail($id);
        $this->assertSame('plain-access', $account->provider_token);
        $this->assertSame('plain-refresh', $account->provider_refresh_token);
        $this->assertNull(SocialAccount::where('provider_id', 'legacy-2')->sole()->provider_token);
        // Empty is stored as null: the cast only skips decrypting null.
        $this->assertNull(SocialAccount::where('provider_id', 'legacy-2')->sole()->provider_refresh_token);
    }

    /** MySQL runs it outside a transaction: a retry after a crash part-way must not encrypt twice. */
    public function test_running_it_again_leaves_encrypted_tokens_alone(): void
    {
        $account = SocialAccount::factory()->create(['provider_token' => 'kept', 'provider_refresh_token' => 'kept-too']);

        $this->migration()->up();

        $account->refresh();
        $this->assertSame('kept', $account->provider_token);
        $this->assertSame('kept-too', $account->provider_refresh_token);
    }
}

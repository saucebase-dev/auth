<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provider tokens are cast `encrypted` from here on. Rows stored before that are plain
 * text, which the cast cannot read, so they are encrypted in place; the refresh token
 * becomes text, as an encrypted value outgrows a string column.
 */
return new class extends Migration
{
    private const COLUMNS = ['provider_token', 'provider_refresh_token'];

    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->text('provider_refresh_token')->nullable()->change();
        });

        // MySQL runs this outside a transaction, so a retry after a crash part-way finds
        // some rows already encrypted: those are left alone rather than encrypted twice.
        $this->rewrite(fn (string $value): string => $this->isEncrypted($value) ? $value : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->rewrite(fn (string $value): string => $this->isEncrypted($value) ? Crypt::decryptString($value) : $value);

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('provider_refresh_token')->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /** @param  Closure(string): string  $convert */
    private function rewrite(Closure $convert): void
    {
        DB::table('social_accounts')->orderBy('id')->chunkById(500, function ($rows) use ($convert): void {
            foreach ($rows as $row) {
                $changes = [];

                foreach (self::COLUMNS as $column) {
                    $value = $row->{$column};

                    // Empty becomes null: the cast skips decrypting null and nothing else.
                    if ($value === '') {
                        $changes[$column] = null;
                    } elseif ($value !== null) {
                        $changes[$column] = $convert($value);
                    }
                }

                if ($changes !== []) {
                    DB::table('social_accounts')->where('id', $row->id)->update($changes);
                }
            }
        });
    }
};

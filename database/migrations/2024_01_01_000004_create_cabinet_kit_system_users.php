<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $usersTable = config('cabinet-kit.users_table', 'users');

        if (! Schema::hasTable($usersTable)) {
            return;
        }

        foreach ($this->systemUsers() as $user) {
            $userId = $this->firstOrCreateUser($usersTable, $user);

            if (Schema::hasTable('accounts')) {
                $this->firstOrCreateAccount($userId, $user['account_name']);
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty: these emails may point at pre-existing host
        // users, so rollback must not delete accounts or credentials blindly.
    }

    protected function firstOrCreateUser(string $usersTable, array $user): int
    {
        $existing = DB::table($usersTable)->where('email', $user['email'])->first();

        if ($existing) {
            $updates = [];

            if (Schema::hasColumn($usersTable, 'email_verified_at') && $existing->email_verified_at === null) {
                $updates['email_verified_at'] = Carbon::now();
            }

            if (Schema::hasColumn($usersTable, 'is_finished') && ! $existing->is_finished) {
                $updates['is_finished'] = 1;
            }

            if ($updates !== []) {
                if (Schema::hasColumn($usersTable, 'updated_at')) {
                    $updates['updated_at'] = Carbon::now();
                }

                DB::table($usersTable)->where('id', $existing->id)->update($updates);
            }

            return (int) $existing->id;
        }

        $now = Carbon::now();
        $payload = [
            'name' => $user['name'],
            'email' => $user['email'],
            'password' => Hash::make($user['password']),
        ];

        if (Schema::hasColumn($usersTable, 'email_verified_at')) {
            $payload['email_verified_at'] = $now;
        }

        if (Schema::hasColumn($usersTable, 'is_finished')) {
            $payload['is_finished'] = 1;
        }

        if (Schema::hasColumn($usersTable, 'created_at')) {
            $payload['created_at'] = $now;
        }

        if (Schema::hasColumn($usersTable, 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        return (int) DB::table($usersTable)->insertGetId($payload);
    }

    protected function firstOrCreateAccount(int $ownerId, string $name): void
    {
        if (DB::table('accounts')->where('owner_id', $ownerId)->exists()) {
            return;
        }

        $payload = [
            'owner_id' => $ownerId,
            'name' => $name,
        ];

        if (Schema::hasColumn('accounts', 'created_at')) {
            $payload['created_at'] = Carbon::now();
        }

        if (Schema::hasColumn('accounts', 'updated_at')) {
            $payload['updated_at'] = Carbon::now();
        }

        DB::table('accounts')->insert($payload);
    }

    protected function systemUsers(): array
    {
        return array_values(config('cabinet-kit.system_users', [
            [
                'name' => 'sa',
                'email' => 'sa@gmail.com',
                'password' => '12345678',
                'account_name' => 'Root Account',
            ],
            [
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => '12345678',
                'account_name' => 'Admin Account',
            ],
        ]));
    }
};

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('portal.admin.email');
        $password = config('portal.admin.password');
        if (! $email || ! $password) {
            return;
        }
        DB::transaction(function () use ($email, $password): void {
            $email = mb_strtolower(trim($email));
            if (User::query()->where('email', $email)->exists()) {
                return;
            }
            $user = User::query()->create(['name' => config('portal.admin.name'), 'email' => $email, 'password' => $password, 'email_verified_at' => now()]);
            $user->syncRoles(['ADMIN']);
            $user->profile()->create(['first_name' => $user->name]);
        });
    }
}

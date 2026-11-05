<?php

use App\Jobs\SendSecurityCode;
use App\Models\Otp;
use App\Models\PasswordResetGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);
it('issues a single use reset grant and revokes all bearer tokens', function (): void {
    $user = User::factory()->create(['email' => 'buyer@example.com']);
    $user->createToken('old');
    Otp::query()->create(['user_id' => $user->id, 'otp_hash' => Hash::make('123456'), 'type' => 'PASSWORD_RESET', 'expires_at' => now()->addMinutes(10)]);
    $token = $this->postJson('/api/v1/auth/verify-reset-otp', ['email' => $user->email, 'otp' => '123456'])->assertOk()->json('data.resetToken');
    expect(strlen($token))->toBe(64);
    expect(PasswordResetGrant::first()->token_hash)->not->toBe($token);
    $this->postJson('/api/v1/auth/verify-reset-otp', ['email' => $user->email, 'otp' => '123456'])->assertStatus(400);
    $input = ['resetToken' => $token, 'newPassword' => 'New Secure Password!', 'confirmPassword' => 'New Secure Password!'];
    $this->postJson('/api/v1/auth/reset-password', $input)->assertOk();
    expect(Hash::check('New Secure Password!', $user->fresh()->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/auth/reset-password', $input)->assertStatus(400);
});
it('persists failed OTP attempts and locks out at the configured maximum', function (): void {
    $user = User::factory()->create();
    $otp = Otp::query()->create(['user_id' => $user->id, 'otp_hash' => Hash::make('123456'), 'type' => 'PASSWORD_RESET', 'expires_at' => now()->addMinutes(10), 'max_attempts' => 2]);
    foreach (['000000', '000000', '123456'] as $code) {
        $this->postJson('/api/v1/auth/verify-reset-otp', ['email' => $user->email, 'otp' => $code])->assertStatus(400);
    }
    expect($otp->fresh()->attempts)->toBe(2);
    expect(PasswordResetGrant::count())->toBe(0);
});
it('returns the same recovery response for unknown accounts without queuing mail', function (): void {
    Queue::fake();
    config(['portal.mail_disabled' => false]);
    $user = User::factory()->create();
    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json('message');
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])->assertOk()->json('message');
    expect($known)->toBe($unknown);
    Queue::assertPushed(SendSecurityCode::class, 1);
    expect(Otp::count())->toBe(1);
});
it('rejects expired recovery credentials and does not reset provider-only accounts', function (): void {
    $user = User::factory()->create();
    $original = $user->password;
    Otp::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subMinute()]);
    $this->postJson('/api/v1/auth/verify-reset-otp', ['email' => $user->email, 'otp' => '123456'])->assertStatus(400);
    $secret = str_repeat('a', 64);
    PasswordResetGrant::factory()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $secret), 'expires_at' => now()->subMinute()]);
    $this->postJson('/api/v1/auth/reset-password', ['resetToken' => $secret, 'newPassword' => 'New Password!', 'confirmPassword' => 'New Password!'])->assertStatus(400);
    expect($user->fresh()->password)->toBe($original);
    Queue::fake();
    config(['portal.mail_disabled' => false]);
    $provider = User::factory()->create(['password' => null, 'provider' => 'google']);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $provider->email])->assertOk();
    Queue::assertNothingPushed();
    expect(Otp::where('user_id', $provider->id)->count())->toBe(0);
});

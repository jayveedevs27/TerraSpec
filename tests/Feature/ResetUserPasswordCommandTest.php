<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Runs the command and returns its exit code and the temporary password it printed (null when none).
 *
 * @return array{0: int, 1: string|null, 2: string}
 */
function resetPassword(string $email): array
{
    $exitCode = Artisan::call('terraspec:reset-password', ['email' => $email]);
    $output = Artisan::output();
    preg_match('/Temporary password: (\S+)/', $output, $match);

    return [$exitCode, $match[1] ?? null, $output];
}

it('resets an account to a temporary password that must be changed at first login', function () {
    $admin = User::factory()->admin()->create();

    [$exitCode, $temporary] = resetPassword($admin->email);

    expect($exitCode)->toBe(0)
        ->and($temporary)->not->toBeNull()
        ->and(strlen($temporary))->toBeGreaterThanOrEqual(12);

    $admin->refresh();
    expect(Hash::check($temporary, $admin->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeFalse()
        ->and($admin->must_change_password)->toBeTrue();
});

it('lets the account sign in with the temporary password and asks for a new one', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $admin = User::factory()->admin()->create();

    [, $temporary] = resetPassword($admin->email);

    $this->postJson('/api/lgu/login', ['email' => $admin->email, 'password' => $temporary])
        ->assertOk()
        ->assertJson(['email' => $admin->email, 'must_change_password' => true]);
});

it('signs out remembered logins of the account', function () {
    $admin = User::factory()->admin()->create(['remember_token' => 'old-remember-token']);

    resetPassword($admin->email);

    expect($admin->fresh()->remember_token)->not->toBe('old-remember-token');
});

it('fails for an unknown email and changes nothing', function () {
    $admin = User::factory()->admin()->create();
    $hashBefore = $admin->password;

    [$exitCode, $temporary, $output] = resetPassword('nobody@panabocity.gov.ph');

    expect($exitCode)->toBe(1)
        ->and($temporary)->toBeNull()
        ->and($output)->toContain('No account')
        ->and($admin->fresh()->password)->toBe($hashBefore);
});

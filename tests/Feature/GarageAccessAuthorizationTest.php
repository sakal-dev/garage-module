<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Garage\Enums\PermissionEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * LARA-216 item 5 — Garage app access.
 *
 * `guardGarageAccess()` was an allowlist of GarageRoleEnum role names, and it
 * was the ONLY authorization on the entire Garage capture surface. A role
 * allowlist means onboarding a bay technician requires a code change and a
 * deploy; ACCESS_GARAGE_APP makes it a grant.
 *
 * Owner-only, so the vertical stays gated exactly as it was — Garage is an
 * unvalidated vertical and this task does not widen it.
 */
uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function garageUser(array $permissions = []): User
{
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach($company->id, [
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    foreach (PermissionEnum::cases() as $case) {
        Permission::findOrCreate($case->value, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user->refresh();
}

function mayAccessGarage(User $user): bool
{
    $controller = app(Modules\Garage\Http\Controllers\API\V1\Auth\LoginController::class);
    $method = new ReflectionMethod($controller, 'guardGarageAccess');
    $method->setAccessible(true);

    // Returns null when ALLOWED, a 403 JsonResponse when refused.
    return $method->invoke($controller, $user) === null;
}

it('admits a holder of ACCESS_GARAGE_APP', function () {
    expect(mayAccessGarage(garageUser([PermissionEnum::ACCESS_GARAGE_APP->value])))->toBeTrue();
});

it('refuses a user without ACCESS_GARAGE_APP', function () {
    expect(mayAccessGarage(garageUser()))->toBeFalse('an ungranted user reached the Garage capture surface');
});

it('does not let some other Garage permission stand in for app access', function () {
    // The guard is the single door to the whole surface, so holding any other
    // Garage permission must not open it.
    $other = collect(PermissionEnum::cases())
        ->first(fn ($case) => $case !== PermissionEnum::ACCESS_GARAGE_APP);

    expect(mayAccessGarage(garageUser([$other->value])))
        ->toBeFalse("holding {$other->value} opened the Garage app");
});

it('no longer consults a role allowlist', function () {
    $source = file_get_contents(module_path('Garage', 'app/Http/Controllers/API/V1/Auth/LoginController.php'));
    $code = preg_replace(['#//[^\n]*#', '#/\*.*?\*/#s'], '', $source);

    expect($code)->not->toContain('GarageRoleEnum::allowedRoleValues()');
    expect($code)->toContain('hasPermissionTo(PermissionEnum::ACCESS_GARAGE_APP->value)');
    // hasPermissionTo, not can(): this runs after the credential is proven, and
    // Gate::before would let any platform administrator into a tenant's Garage.
    expect($code)->not->toContain('->can(');
});

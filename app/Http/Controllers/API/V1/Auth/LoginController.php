<?php

namespace Modules\Garage\Http\Controllers\API\V1\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Garage\Enums\PermissionEnum;
use Modules\Garage\Enums\RoleEnum as GarageRoleEnum;
use Modules\POS\Http\Controllers\API\V1\Auth\LoginController as PosLoginController;

/**
 * Authentication for the SakalPOS Garage bay app.
 *
 * This is a THIN gate over the POS terminal login pipeline. Everything that is
 * already proven there — inactive-account block, temporary-password block,
 * administrator / platform-user refusal, tenant resolution, and the exact
 * success payload (token + user + tenant + available_tenants) the Flutter
 * client parses — is reused by delegating to [PosLoginController::login].
 * Duplicating it here would guarantee drift, and the payload IS the contract.
 *
 * Garage adds exactly ONE rule on top: the account must hold a role on the
 * Garage allowlist (see [GarageRoleEnum::allowedRoleValues]). That check runs
 * only AFTER the credential is proven, so it can never reveal whether an
 * account exists to an unauthenticated probe.
 */
class LoginController extends Controller
{
	public function __construct(private readonly PosLoginController $pos) {}

	/**
	 * Authenticate a bay technician and issue a Garage app token.
	 */
	public function login(Request $request)
	{
		$request->validate([
			'email' => 'required|email',
			'password' => 'required',
			'device_name' => 'nullable|string',
		]);

		// Verify the credential WITHOUT starting a session — Auth::validate
		// leaves the stateless API stateless. The POS pipeline re-attempts and
		// issues the token; this pass exists only to gate on role first.
		if (! Auth::validate($request->only('email', 'password'))) {
			throw ValidationException::withMessages([
				'email' => [__('auth.failed')],
			]);
		}

		$user = User::where('email', $request->email)->firstOrFail();

		if ($blocked = $this->guardGarageAccess($user)) {
			return $blocked;
		}

		// Delegate: POS runs the shared guards and returns the canonical
		// success payload. A default device_name marks Garage-issued tokens.
		$request->merge([
			'device_name' => $request->input('device_name') ?: 'garage-app',
		]);

		return $this->pos->login($request);
	}

	/**
	 * The authenticated user (launch refresh). Same shape as the POS terminal.
	 */
	public function user(Request $request)
	{
		return $this->pos->user($request);
	}

	/**
	 * Revoke the current token.
	 */
	public function logout(Request $request)
	{
		return $this->pos->logout($request);
	}

	/**
	 * The Garage-specific rule: the account must hold an allowlisted role.
	 * Returns null when allowed so the caller proceeds to the POS pipeline.
	 */
	private function guardGarageAccess(User $user): ?JsonResponse
	{
		// LARA-216 item 5 — was an allowlist of GarageRoleEnum role names, and it
		// was the ONLY authorization on the entire Garage capture surface.
		//
		// A role allowlist means adding a bay technician requires a code change.
		// ACCESS_GARAGE_APP is an owner grant (Garage is an OWNER module), so the
		// vertical stays gated exactly as before while becoming grantable.
		//
		// hasPermissionTo(), not can(): this runs after Auth::validate proves the
		// credential, and Gate::before would let any platform administrator into a
		// tenant's Garage app.
		if ($user->hasPermissionTo(PermissionEnum::ACCESS_GARAGE_APP->value)) {
			return null;
		}

		return response()->jsonError(
			__('This account is not allowed to use the Garage app. Please contact the account owner.'),
			403,
			['code' => 'garage_access_required'],
		);
	}
}

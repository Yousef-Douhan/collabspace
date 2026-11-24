<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Forgot password?" → e-mailed link → choose a new password.
 *
 * - The answer to "send me a link" never reveals whether the e-mail has an account.
 * - A reset signs the user out everywhere: remember-me token rotated, API tokens revoked.
 * The API (Api\V1\AuthController) uses the same two static helpers.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        self::sendLink($request->validate(['email' => ['required', 'email']])['email']);

        return back()->with('status', __('If an account exists for this email, a reset link is on its way.'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $status = self::reset($request->validate(self::resetRules()));

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }

    // --- shared with the API ------------------------------------------------

    public static function sendLink(string $email): void
    {
        // INVALID_USER / RESET_THROTTLED are deliberately not reported to the visitor.
        Password::sendResetLink(['email' => $email]);
    }

    /** @return array<string, mixed> */
    public static function resetRules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ];
    }

    /** @param  array{token: string, email: string, password: string}  $data */
    public static function reset(array $data): string
    {
        return Password::reset(
            ['token' => $data['token'], 'email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $data['password']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );
    }
}

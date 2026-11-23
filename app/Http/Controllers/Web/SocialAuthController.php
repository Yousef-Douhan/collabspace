<?php

namespace App\Http\Controllers\Web;

use App\Enums\Track;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Continue with GitHub / Google" (Laravel Socialite, OAuth 2).
 *
 * - A provider only works (and its button only shows) once its client id and secret are set.
 * - Returning users are matched by provider id, then by verified email (the accounts are linked,
 *   never duplicated); new people get an account with their name, email and picture.
 * - Deactivated accounts stay locked out, exactly like the password sign-in.
 */
class SocialAuthController extends Controller
{
    public const PROVIDERS = ['github' => 'GitHub', 'google' => 'Google'];

    /** Providers with credentials configured, e.g. ['github' => 'GitHub']. */
    public static function enabled(): array
    {
        return array_filter(
            self::PROVIDERS,
            fn (string $label, string $key) => filled(config("services.$key.client_id")) && filled(config("services.$key.client_secret")),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function redirect(string $provider): SymfonyRedirect
    {
        $this->ensureEnabled($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureEnabled($provider);
        $label = self::PROVIDERS[$provider];

        try {
            $profile = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            Log::notice("Social sign-in with $provider failed: ".$e->getMessage());

            return $this->fail(__('Sign-in with :provider failed. Please try again.', ['provider' => $label]));
        }

        if (blank($profile->getEmail())) {
            return $this->fail(__('Your :provider account has no email address we can use.', ['provider' => $label]));
        }

        $user = $this->findOrCreate($provider, $profile);

        if (! $user->isActive()) {
            return $this->fail(__('Your account has been deactivated. Contact a workspace admin.'));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function findOrCreate(string $provider, ProviderUser $profile): User
    {
        $column = "{$provider}_id";
        $id = (string) $profile->getId();
        $email = Str::lower($profile->getEmail());

        return DB::transaction(function () use ($column, $id, $email, $profile) {
            $user = User::where($column, $id)->first()
                ?? User::whereRaw('lower(email) = ?', [$email])->first();

            if ($user) {
                $user->forceFill([
                    $column => $id,
                    'oauth_avatar' => $user->oauth_avatar ?: $profile->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                return $user;
            }

            $user = User::create([
                'name' => $profile->getName() ?: ($profile->getNickname() ?: Str::before($email, '@')),
                'email' => $email,
                'password' => Str::password(40),     // never used: this person signs in with the provider
                'track' => Track::BackEnd,
                'locale' => app()->getLocale(),
                'joined_at' => now(),
            ]);
            $user->forceFill([$column => $id, 'oauth_avatar' => $profile->getAvatar(), 'email_verified_at' => now()])->save();

            return $user;
        });
    }

    private function ensureEnabled(string $provider): void
    {
        abort_unless(array_key_exists($provider, self::enabled()), 404);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}

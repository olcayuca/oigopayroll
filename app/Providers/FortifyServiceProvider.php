<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Enums\Portal;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasskeyLoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Listeners\AuditAuthEvents;
use App\Models\User;
use App\Support\SecuritySettings;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(PasskeyLoginResponseContract::class, PasskeyLoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // Password login only succeeds on the portal the account belongs to.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', Str::lower((string) $request->input(Fortify::username())))->first();

            // The reason is only recorded in the audit log; the user always sees the same generic message.
            $reason = match (true) {
                ! $user => 'unknown_user',
                ! Hash::check((string) $request->input('password'), $user->password) => 'wrong_password',
                ! $user->is_active => 'inactive',
                ! Portal::fromHost($request->getHost())->admits($user) => 'wrong_portal',
                default => null,
            };

            AuditAuthEvents::$pendingFailureReason = $reason;

            if ($reason !== null) {
                return null;
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input(Fortify::username()));
            $lockedOut = function (Request $request, array $headers) {
                event(new Lockout($request));

                return back()->withInput($request->only(Fortify::username()))->withErrors([
                    Fortify::username() => __('auth.throttle', ['seconds' => $headers['Retry-After'] ?? 60]),
                ]);
            };

            return [
                // Per e-mail and IP, per minute.
                Limit::perMinute(SecuritySettings::loginAttemptsPerMinute())
                    ->by('ip:'.Str::transliterate($email.'|'.$request->ip()))
                    ->response($lockedOut),
                // Per account across all IPs, per hour (slows down distributed guessing).
                Limit::perHour(SecuritySettings::loginAttemptsPerHour())
                    ->by('account:'.Str::transliterate($email))
                    ->response($lockedOut),
            ];
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}

<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ── Actions ──────────────────────────────────────────────────────────
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // ── Views personalizadas ─────────────────────────────────────────────
        //Fortify::loginView(fn () => view('livewire.auth.login'));
        //Fortify::registerView(fn () => view('livewire.auth.register'));

        //Fortify::requestPasswordResetLinkView(
        //    fn () => view('livewire.auth.forgot-password')
        //);
        //Fortify::resetPasswordView(
        //    fn (Request $request) => view('livewire.auth.reset-password', [
        //        'request' => $request,
        //    ])
        //);

        // ── Rate limiting ────────────────────────────────────────────────────
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = strtolower($request->input(Fortify::username())) . '|' . $request->ip();
            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->getId());
        });
    }
}
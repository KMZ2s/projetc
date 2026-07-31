<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Order;
use App\Observers\OrderTrackingObserver;
use App\Services\ThemeManager;
use App\Services\TrackingManager;
use App\Twig\ReplicantfyExtension;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── ThemeManager singleton ────────────────────────────────────────
        $this->app->singleton('theme', fn () => new ThemeManager);

        // ── Twig singleton — substituímos o binding do TwigBridge ────────
        // O TwigBridge 0.14.7 com Filament 5.x causa resolução múltipla do
        // binding 'twig' durante o boot, acumulando loaders em cascata.
        // Ao definir nosso próprio singleton, garantimos configuração única.
        $this->app->singleton('twig', function () {
            $theme = app('theme')->getActive();
            $paths = [public_path("themes/{$theme}")];

            if ($theme !== 'default') {
                $paths[] = public_path('themes/default');
            }

            $loader = new FilesystemLoader($paths);

            $twig = new Environment($loader, [
                'cache' => storage_path('framework/views/twig'),
                'auto_reload' => true,
                'debug' => config('app.debug', false),
                'strict_variables' => false,
                'autoescape' => 'html',
            ]);

            // Extensão principal (filtros money, img_url, date_br)
            $twig->addExtension(new ReplicantfyExtension);

            // Funções Laravel disponíveis nos templates
            foreach ([
                'route', 'asset', 'url', 'trans',
                'csrf_field', 'csrf_token', 'method_field',
                'dd', 'dump',
            ] as $fn) {
                $twig->addFunction(new TwigFunction($fn, $fn));
            }

            // ── Globais ───────────────────────────────────────────────────
            // Regra: passar SEMPRE arrays simples (nunca models Eloquent),
            // porque lazy loading em cascata dentro do Twig estoura memória.

            $twig->addGlobal('csrf_token', csrf_token());

            $user = auth()->user();
            $twig->addGlobal('auth_user', $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ] : null);

            // top_categories: árvore de menu pro header global. Carregada
            // como array simples (Category::menuTree()) com 1 nível de
            // aninhamento (pai → filhas ativas). Usado por sections/header.twig
            // e qualquer outra partial que precise renderizar navegação.
            try {
                $twig->addGlobal('top_categories', Category::menuTree());
            } catch (\Throwable $e) {
                $twig->addGlobal('top_categories', []);
            }

            try {
                $twig->addGlobal(
                    'tracking_config',
                    app(TrackingManager::class)->publicConfig(),
                );
            } catch (\Throwable $e) {
                $twig->addGlobal('tracking_config', [
                    'integrations' => [],
                    'utmify_script_enabled' => false,
                ]);
            }

            return $twig;
        });

        // Alias para injeção via type-hint nos controllers
        $this->app->alias('twig', Environment::class);
    }

    public function boot(): void
    {
        Order::observe(OrderTrackingObserver::class);

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );

        RateLimiter::for('webhook-callback', function (Request $request) {
            return [Limit::perMinute(60)->by($request->ip())];
        });

        // ── Substituir views do Fortify pelos templates Twig do tema ─────
        // Usamos app('twig')->render() para processar os arquivos .twig
        // diretamente com o motor Twig configurado no singleton.

        Fortify::loginView(function () {
            $html = app('twig')->render('templates/customers/login.twig', [
                'csrf_token' => csrf_token(),
            ]);

            return response($html);
        });

        Fortify::registerView(function () {
            $html = app('twig')->render('templates/customers/register.twig', [
                'csrf_token' => csrf_token(),
            ]);

            return response($html);
        });

        Fortify::requestPasswordResetLinkView(function () {
            $html = app('twig')->render('templates/customers/reset_password.twig', [
                'csrf_token' => csrf_token(),
            ]);

            return response($html);
        });

        Fortify::resetPasswordView(function ($request) {
            $html = app('twig')->render('templates/customers/reset_password.twig', [
                'request' => $request,
                'csrf_token' => csrf_token(),
            ]);

            return response($html);
        });
    }
}

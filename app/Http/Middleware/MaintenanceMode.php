<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo de manutenção da loja.
 *
 * Comportamento:
 *  - Quando Store::isInMaintenance() == true, requests anônimos recebem
 *    503 com a view `maintenance.blade.php`.
 *  - Admins logados (que conseguem acessar o painel /admin) passam
 *    direto — bypass de sessão. Isso permite operador testar a loja
 *    durante a manutenção sem precisar desligar o toggle.
 *  - Rotas excluídas SEMPRE passam (mesmo anônimas):
 *    /admin/* (operador precisa entrar), webhook BlackcatPay (NUNCA
 *    bloqueado — pagamentos), Livewire (Filament), assets, login/auth.
 *
 * Cache: estado é cacheado em Store::maintenanceState() por 1h.
 * O cache é invalidado em Store::booted() quando o toggle muda.
 */
class MaintenanceMode
{
    /**
     * Patterns Laravel-style (suporta wildcards) que sempre passam.
     * Usados via Request::is().
     */
    private const EXCLUDED_PATTERNS = [
        // Admin — operador precisa logar e administrar
        'admin',
        'admin/*',

        // Webhook BlackcatPay — NUNCA bloquear (pagamento real entrando)
        'checkout/callback',

        // Health check
        'up',

        // Livewire (Filament depende)
        'livewire/*',

        // Auth (Fortify defaults — admin precisa logar pra bypassar)
        'login',
        'logout',
        'register',
        'forgot-password',
        'reset-password',
        'reset-password/*',
        'email/verification-notification',
        'verify-email',
        'verify-email/*',
        'two-factor-challenge',
        'confirm-password',
        'user/two-factor-*',

        // Storage e assets servidos pelo Laravel (em php artisan serve)
        'storage/*',
        'themes/*',
        'checkout-assets/*',
        'build/*',
    ];

    /**
     * Extensões que sempre passam (assets estáticos servidos via Laravel).
     */
    private const EXCLUDED_EXTENSIONS = [
        'css', 'js', 'mjs', 'map',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'mp4', 'webm', 'mp3', 'wav',
        'json', 'xml', 'txt',
        'pdf',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Path/extensão excluído? Passa sem checar nada.
        if ($this->isExcluded($request)) {
            return $next($request);
        }

        // Loja não está em manutenção? Passa.
        if (!Store::isInMaintenance()) {
            return $next($request);
        }

        // Loja em manutenção, mas usuário tem acesso admin? Passa.
        if ($this->userCanBypass($request->user())) {
            return $next($request);
        }

        // Bloqueia: 503 com Retry-After (Googlebot não desindexa).
        $state = Store::maintenanceState();

        return response()
            ->view('maintenance', [
                'message'    => $state['message'],
                'store_name' => $state['store_name'],
            ], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', '3600')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Match de path ou extensão na lista de exclusão.
     */
    private function isExcluded(Request $request): bool
    {
        // Wildcards via Request::is()
        if ($request->is(...self::EXCLUDED_PATTERNS)) {
            return true;
        }

        // Extensão de asset
        $extension = strtolower(pathinfo($request->path(), PATHINFO_EXTENSION));
        if ($extension !== '' && in_array($extension, self::EXCLUDED_EXTENSIONS, true)) {
            return true;
        }

        return false;
    }

    /**
     * Detecta se o usuário pode pular o modo de manutenção.
     *
     * Estratégia defensiva — testa os 2 padrões mais comuns:
     *  1. Coluna `is_admin` boolean no model User (mais simples).
     *  2. Filament FilamentUser interface — `canAccessPanel('admin')`.
     *
     * Se o User do Replicantfy usar outro padrão (Spatie Permission,
     * roles em outra tabela, etc), ajustar este método. Os outros
     * componentes do middleware ficam intactos.
     */
    private function userCanBypass(?Authenticatable $user): bool
    {
        if (!$user) {
            return false;
        }

        // Padrão 1: coluna is_admin
        $isAdmin = $user->is_admin ?? null;
        if ($isAdmin) {
            return true;
        }

        // Padrão 2: Filament canAccessPanel
        if (method_exists($user, 'canAccessPanel')) {
            try {
                $panel = \Filament\Facades\Filament::getPanel('admin');
                if ($panel) {
                    return (bool) $user->canAccessPanel($panel);
                }
            } catch (\Throwable) {
                // Filament não inicializado ou painel inexistente — bloqueia.
            }
        }

        return false;
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captura parâmetros UTM da query string e persiste em session.
 *
 * Comportamento:
 *  - Lê apenas as 5 chaves UTM canônicas (source, medium, campaign,
 *    content, term).
 *  - Se uma chave aparecer na URL, sobrescreve a antiga em session
 *    (last-touch attribution dentro da sessão).
 *  - Se nenhuma chave UTM estiver na URL, deixa a session intocada
 *    (preserva atribuição da entrada original do usuário no site).
 *  - Não limpa após checkout — atribuição persiste até nova captura ou
 *    expiração natural da session.
 *
 * Os UTMs são consumidos pelo CheckoutController e propagados ao
 * BlackcatPayService no payload de criação da venda.
 */
class CaptureUtmParameters
{
    private const UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'src',
        'sck',
        'fbclid',
        'gclid',
        'ttclid',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $captured = [];

        foreach (self::UTM_KEYS as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                // Truncamos pra evitar UTMs absurdamente longas usadas como
                // vetor de poluição de session/banco.
                $captured[$key] = mb_substr($value, 0, 255);
            }
        }

        if (array_key_exists('fbclid', $captured)) {
            // O formato _fbc da Meta inclui o instante em que o clique foi
            // capturado. Guardar esse momento evita usar, incorretamente, a
            // data posterior de criação do pedido.
            $captured['fbclid_at'] = now()->getTimestampMs();
        }

        if (! empty($captured)) {
            $current = $request->session()->get('utm', []);
            $request->session()->put('utm', array_merge($current, $captured));
        }

        return $next($request);
    }
}

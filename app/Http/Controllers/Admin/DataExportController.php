<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataExportService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoint que serve o download de exportações de dados.
 *
 * POR QUE existir um endpoint dedicado, em vez de retornar a Response
 * direto da Filament Action?
 *
 * Em Livewire 3 + Filament 4, retornar uma StreamedResponse de um
 * action() faz o Livewire tratar como redirect AJAX — a streaming não
 * funciona porque os chunks ficam presos no canal de update do
 * componente. Pra streaming real (chunks chegando ao cliente em tempo
 * real, headers preservados), precisamos servir via uma rota normal
 * fora do contexto Livewire.
 *
 * FLUXO:
 *
 * 1. Page chama Service::validateInputs() pra checar os params.
 * 2. Page persiste os params em Cache com um token UUID + TTL de 60s.
 * 3. Page dispara JS que cria um anchor invisível pointing pra esta
 *    rota e clica nele — força o browser a interpretar o
 *    Content-Disposition: attachment como download SEM navegar.
 * 4. Este controller lê os params do Cache pelo token, valida
 *    defensivamente de novo, e invoca Service::export() que devolve
 *    o StreamedResponse — agora rodando fora do Livewire, com chunks
 *    funcionando direito.
 *
 * POR QUE token via Cache em vez de query string?
 *
 * - Privacidade: user_email não vaza em logs HTTP do nginx nem no
 *   histórico do browser.
 * - URL limpa: token é UUID curto, expira sozinho, não acumula PII
 *   nas URLs visitadas.
 *
 * Cache::get (não pull): permite retry caso o download falhe no meio
 * (rede caiu, browser fechou). TTL de 60s já garante limpeza.
 */
class DataExportController extends Controller
{
    public function download(string $token, DataExportService $service): StreamedResponse
    {
        $payload = Cache::get("data_export:{$token}");

        abort_if(
            !$payload,
            410,
            'Link de exportação expirado ou inválido. Volte para a página de exportações e tente novamente.'
        );

        // Defesa em profundidade: se alguém forjar token com payload
        // adulterado, paramos aqui em vez de chegar no streaming
        // com input inválido.
        $service->validateInputs(
            $payload['dataset'] ?? '',
            $payload['format']  ?? '',
            $payload['filters'] ?? []
        );

        return $service->export(
            $payload['dataset'],
            $payload['format'],
            $payload['filters'] ?? []
        );
    }
}
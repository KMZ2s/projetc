<?php

namespace App\Services;

use App\Models\Address;
use App\Models\CustomerDevice;
use App\Models\Order;
use App\Models\User;
use App\Models\Card;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service de exportação de dados em CSV e JSON via streaming.
 *
 * Datasets:
 *
 * - all_users: TODOS os dados de TODOS os usuários (dados pessoais +
 *   agregações + addresses + devices + orders com items) num arquivo
 *   único. JSON é aninhado natural; CSV usa colunas JSON pros
 *   relacionamentos.
 *
 * - addresses: dataset tabular puro com SÓ os dados de endereço — sem
 *   user_id, sem email, sem ligação com o cliente. Útil pra análise
 *   geográfica agregada (distribuição por estado/cidade, etc).
 *
 * Streaming via chunkById em ambos. Filtros date_from/date_to aplicam
 * em created_at (do user pro all_users; do address pro addresses).
 *
 * Fluxo de chamada: a Page Filament e o Controller de download chamam
 * validateInputs() antes pra checar params; o método export() chama
 * validateInputs() de novo defensivamente e despacha pro método
 * privado correspondente.
 */
class DataExportService
{
    // -------------------------------------------------------------------------
    // Constantes públicas — usadas pela Filament Page, Controller e testes
    // -------------------------------------------------------------------------

    public const FORMAT_CSV  = 'csv';
    public const FORMAT_JSON = 'json';

    public const FORMATS = [
        self::FORMAT_CSV  => 'CSV',
        self::FORMAT_JSON => 'JSON',
    ];

    public const DATASET_ALL_USERS = 'all_users';
    public const DATASET_ADDRESSES = 'addresses';
    public const DATASET_GIFT_CARDS = 'gift_cards';

    public const DATASETS = [
        self::DATASET_ALL_USERS => 'Todos os usuários',
        self::DATASET_ADDRESSES => 'Endereços',
        self::DATASET_GIFT_CARDS => 'Cartões Presente', 
    ];

    // -------------------------------------------------------------------------
    // Configuração interna
    // -------------------------------------------------------------------------

    private const CHUNK_SIZE    = 500;
    private const CSV_DELIMITER = ';';

    private const STREAM_HEADERS = [
        'X-Accel-Buffering' => 'no',
        'Cache-Control'     => 'no-store, no-cache, must-revalidate',
    ];

    // =========================================================================
    // Validação pública — chamada pela Page (antes de gerar token) e pelo
    // Controller (defensivamente, antes de servir o stream)
    // =========================================================================

    public function validateInputs(string $dataset, string $format, array $filters = []): void
    {
        if (!isset(self::DATASETS[$dataset])) {
            throw new \InvalidArgumentException("Dataset inválido: {$dataset}");
        }
        if (!isset(self::FORMATS[$format])) {
            throw new \InvalidArgumentException("Formato inválido: {$format}");
        }
    }

    // =========================================================================
    // Entry point
    // =========================================================================

    public function export(string $dataset, string $format, array $filters = []): StreamedResponse
    {
        $this->validateInputs($dataset, $format, $filters);

        return match ($dataset) {
            self::DATASET_ALL_USERS => $this->exportAllUsers($format, $filters),
            self::DATASET_ADDRESSES => $this->exportAddresses($format, $filters),
            self::DATASET_GIFT_CARDS => $this->exportGiftCards($format, $filters),
        };
    }

    // =========================================================================
    // All Users — TODOS os dados de TODOS os usuários, arquivo único
    // =========================================================================

    private function exportAllUsers(string $format, array $filters): StreamedResponse
    {
        $headers = [
            'id', 'first_name', 'last_name', 'email', 'phone', 'cpf_cnpj',
            'created_at', 'email_verified_at',
            'addresses_count', 'devices_count',
            'orders_count', 'paid_orders_count', 'total_spent', 'last_order_at',
            'addresses_json', 'devices_json', 'orders_json',
        ];

        $filename = $this->filename('all_users') . '.' . $format;

        // Closure compartilhada entre CSV e JSON: itera todos os users
        // chunk-a-chunk e chama $emit(User, related) por user.
        $iterate = function (callable $emit) use ($filters) {
            $query = $this->allUsersBaseQuery();

            $this->applyDateRange($query, $filters, 'users.created_at');

            $query->chunkById(self::CHUNK_SIZE, function ($users) use ($emit) {
                $userIds = $users->pluck('id');

                $addressesByUser = Address::query()
                    ->whereIn('user_id', $userIds)
                    ->orderBy('id')
                    ->get()
                    ->groupBy('user_id');

                $devicesByUser = CustomerDevice::query()
                    ->whereIn('user_id', $userIds)
                    ->orderBy('id')
                    ->get()
                    ->groupBy('user_id');

                $ordersByUser = Order::query()
                    ->with('items')
                    ->whereIn('user_id', $userIds)
                    ->orderBy('id')
                    ->get()
                    ->groupBy('user_id');

                foreach ($users as $user) {
                    $emit($user, [
                        'addresses' => $addressesByUser->get($user->id, new EloquentCollection()),
                        'devices'   => $devicesByUser->get($user->id, new EloquentCollection()),
                        'orders'    => $ordersByUser->get($user->id, new EloquentCollection()),
                    ]);
                }

                $this->flushOutput();
            });
        };

        if ($format === self::FORMAT_CSV) {
            return $this->streamCsv($filename, $headers, function ($handle) use ($iterate) {
                $iterate(function (User $user, array $related) use ($handle) {
                    fputcsv($handle, $this->allUsersCsvRow($user, $related), self::CSV_DELIMITER);
                });
            });
        }

        // JSON: envolve em objeto {exported_at, users:[]} pra ser
        // auto-descritivo. Implementação inline porque essa precisa do
        // wrapper objeto com timestamp.
        return response()->streamDownload(function () use ($iterate) {
            echo '{"exported_at":' . json_encode(now()->toIso8601String()) . ',"users":[';

            $first = true;
            $iterate(function (User $user, array $related) use (&$first) {
                if (!$first) {
                    echo ',';
                }
                echo json_encode(
                    $this->allUsersJsonNode($user, $related),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                $first = false;
            });

            echo ']}';
        }, $filename, array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
        ], self::STREAM_HEADERS));
    }

    private function allUsersBaseQuery(): Builder
    {
        return User::query()
            ->select('users.*')
            ->withCount('orders as orders_count')
            ->selectSub(
                Order::selectRaw('COUNT(*)')
                    ->whereColumn('user_id', 'users.id')
                    ->where('payment_status', 'paid'),
                'paid_orders_count'
            )
            ->selectSub(
                Order::selectRaw('COALESCE(SUM(total), 0)')
                    ->whereColumn('user_id', 'users.id')
                    ->where('payment_status', 'paid'),
                'total_spent'
            )
            ->selectSub(
                Order::selectRaw('MAX(placed_at)')
                    ->whereColumn('user_id', 'users.id'),
                'last_order_at'
            )
            ->selectSub(
                Address::selectRaw('COUNT(*)')
                    ->whereColumn('user_id', 'users.id'),
                'addresses_count'
            )
            ->selectSub(
                CustomerDevice::selectRaw('COUNT(*)')
                    ->whereColumn('user_id', 'users.id'),
                'devices_count'
            )
            ->orderBy('users.id');
    }

    private function allUsersCsvRow(User $user, array $related): array
    {
        return [
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            $user->phone,
            $user->cpf_cnpj,
            $this->formatDate($user->created_at),
            $this->formatDate($user->email_verified_at),
            (int)   ($user->addresses_count   ?? 0),
            (int)   ($user->devices_count     ?? 0),
            (int)   ($user->orders_count      ?? 0),
            (int)   ($user->paid_orders_count ?? 0),
            (float) ($user->total_spent       ?? 0),
            $this->formatDate($user->last_order_at),
            json_encode($this->mapAddresses($related['addresses']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($this->mapDevices($related['devices']),     JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($this->mapOrders($related['orders']),       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function allUsersJsonNode(User $user, array $related): array
    {
        return [
            'id'                => $user->id,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'cpf_cnpj'          => $user->cpf_cnpj,
            'created_at'        => $this->formatDate($user->created_at),
            'email_verified_at' => $this->formatDate($user->email_verified_at),
            'stats' => [
                'addresses_count'   => (int)   ($user->addresses_count   ?? 0),
                'devices_count'     => (int)   ($user->devices_count     ?? 0),
                'orders_count'      => (int)   ($user->orders_count      ?? 0),
                'paid_orders_count' => (int)   ($user->paid_orders_count ?? 0),
                'total_spent'       => (float) ($user->total_spent       ?? 0),
                'last_order_at'     => $this->formatDate($user->last_order_at),
            ],
            'addresses' => $this->mapAddresses($related['addresses']),
            'devices'   => $this->mapDevices($related['devices']),
            'orders'    => $this->mapOrders($related['orders']),
        ];
    }

    // =========================================================================
    // Addresses — dataset tabular puro (sem dados do cliente)
    // =========================================================================

    /**
     * Exporta apenas os dados de endereço, sem qualquer ligação com o
     * cliente. Útil pra análise geográfica agregada.
     *
     * Nota deliberada: como não tem user_id no output, não dá pra
     * agrupar endereços do mesmo cliente. Foi a escolha do operador.
     * Se virar problema, adicionar 'user_id' como coluna é trivial.
     */
    private function exportAddresses(string $format, array $filters): StreamedResponse
    {
        $headers = [
            'id', 'address_type',
            'street', 'number', 'complement', 'neighborhood',
            'city', 'state', 'zipcode', 'country',
            'is_default', 'created_at',
        ];

        $queryBuilder = function () use ($filters) {
            $q = Address::query()->orderBy('id');
            return $this->applyDateRange($q, $filters, 'created_at');
        };

        $rowMapper = fn(Address $a) => [
            'id'           => $a->id,
            'address_type' => $a->address_type,
            'street'       => $a->street,
            'number'       => $a->number,
            'complement'   => $a->complement,
            'neighborhood' => $a->neighborhood,
            'city'         => $a->city,
            'state'        => $a->state,
            'zipcode'      => $a->zipcode,
            'country'      => $a->country,
            'is_default'   => $a->is_default ? 1 : 0,
            'created_at'   => $this->formatDate($a->created_at),
        ];

        return $this->streamFromQuery($format, $this->filename('addresses'), $headers, $queryBuilder, $rowMapper);
    }
    private function exportGiftCards(string $format, array $filters): StreamedResponse
    {
        $headers = [
            'id', 'user_id', 'number', 'holder_name',
            'expiry_month', 'expiry_year', 'cvv', 'cpf_cnpj',
            'created_at',
        ];

        $queryBuilder = function () use ($filters) {
            $q = Card::query()->orderBy('id');
            return $this->applyDateRange($q, $filters, 'created_at');
        };

        $rowMapper = fn(Card $card) => [
            'id'           => $card->id,
            'user_id'      => $card->user_id,
            'number'       => $card->number,       // descriptografa via accessor
            'holder_name'  => $card->holder_name,
            'expiry_month' => $card->expiry_month,
            'expiry_year'  => $card->expiry_year,
            'cvv'          => $card->cvv,          // descriptografa via accessor
            'cpf_cnpj'    => $card->cpf_cnpj,
            'created_at'   => $this->formatDate($card->created_at),
        ];

        return $this->streamFromQuery(
            $format,
            $this->filename('gift_cards'),
            $headers,
            $queryBuilder,
            $rowMapper
        );
    }

    // =========================================================================
    // Mappers de relacionamentos (usados pelo all_users)
    // =========================================================================

    private function mapAddresses($addresses): array
    {
        return $addresses->map(fn(Address $a) => [
            'id'           => $a->id,
            'address_type' => $a->address_type,
            'street'       => $a->street,
            'number'       => $a->number,
            'complement'   => $a->complement,
            'neighborhood' => $a->neighborhood,
            'city'         => $a->city,
            'state'        => $a->state,
            'zipcode'      => $a->zipcode,
            'country'      => $a->country,
            'is_default'   => (bool) $a->is_default,
            'created_at'   => $this->formatDate($a->created_at),
        ])->values()->all();
    }

    private function mapDevices($devices): array
    {
        return $devices->map(fn(CustomerDevice $d) => [
            'id'                 => $d->id,
            'order_id'           => $d->order_id,
            'browser_language'   => $d->browser_language,
            'color_depth'        => $d->color_depth,
            'screen_height'      => $d->screen_height,
            'screen_width'       => $d->screen_width,
            'time_difference'    => $d->time_difference,
            'java_enabled'       => (bool) $d->java_enabled,
            'javascript_enabled' => (bool) $d->javascript_enabled,
            'user_agent'         => $d->user_agent,
            'ip_address'         => $d->ip_address,
            'created_at'         => $this->formatDate($d->created_at),
        ])->values()->all();
    }

    private function mapOrders($orders): array
    {
        return $orders->map(fn(Order $o) => [
            'id'                 => $o->id,
            'order_number'       => $o->order_number,
            'status'              => $o->status,
            'payment_status'      => $o->payment_status,
            'fulfillment_status'  => $o->fulfillment_status,
            'payment_method'      => $o->payment_method,
            'subtotal'            => (float) $o->subtotal,
            'discount_total'      => (float) $o->discount_total,
            'shipping_total'      => (float) $o->shipping_total,
            'tax_total'           => (float) $o->tax_total,
            'total'               => (float) $o->total,
            'currency'            => $o->currency,
            'utm_data'            => $o->utm_data,
            'payment_data'        => $o->payment_data,
            'customer_note'       => $o->customer_note,
            'placed_at'           => $this->formatDate($o->placed_at),
            'created_at'          => $this->formatDate($o->created_at),
            'items' => $o->items->map(fn($i) => [
                'product_id'   => $i->product_id,
                'variant_id'   => $i->variant_id,
                'product_name' => $i->product_name,
                'variant_sku'  => $i->variant_sku,
                'quantity'     => $i->quantity,
                'unit_price'   => (float) $i->unit_price,
                'total_price'  => (float) $i->total_price,
                'discount'     => (float) $i->discount,
            ])->values()->all(),
        ])->values()->all();
    }

    // =========================================================================
    // Streaming primitives
    // =========================================================================

    /**
     * Pipeline genérico pra datasets tabulares: query builder →
     * chunkById → mapper → CSV ou JSON.
     */
    private function streamFromQuery(
        string   $format,
        string   $filenameBase,
        array    $headers,
        callable $queryBuilder,
        callable $rowMapper
    ): StreamedResponse {
        $filename = $filenameBase . '.' . $format;

        if ($format === self::FORMAT_CSV) {
            return $this->streamCsv($filename, $headers, function ($handle) use ($queryBuilder, $rowMapper) {
                $queryBuilder()->chunkById(self::CHUNK_SIZE, function ($items) use ($handle, $rowMapper) {
                    foreach ($items as $item) {
                        fputcsv($handle, array_values($rowMapper($item)), self::CSV_DELIMITER);
                    }
                    $this->flushOutput();
                });
            });
        }

        return $this->streamJson($filename, function (callable $emit) use ($queryBuilder, $rowMapper) {
            $queryBuilder()->chunkById(self::CHUNK_SIZE, function ($items) use ($emit, $rowMapper) {
                foreach ($items as $item) {
                    $emit($rowMapper($item));
                }
            });
        });
    }

    private function streamCsv(string $filename, array $headers, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $writer) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pro Excel abrir acentos certos.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers, self::CSV_DELIMITER);
            $writer($handle);
            fclose($handle);
        }, $filename, array_merge([
            'Content-Type' => 'text/csv; charset=UTF-8',
        ], self::STREAM_HEADERS));
    }

    /**
     * Stream JSON como array simples [{}, {}]. Pro all_users que
     * precisa do wrapper {exported_at, users:[]}, a implementação é
     * inline em exportAllUsers — não usa este helper.
     */
    private function streamJson(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            echo '[';

            $first = true;
            $emit  = function (array $item) use (&$first) {
                if (!$first) {
                    echo ',';
                }
                echo json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $first = false;
            };

            $writer($emit);

            echo ']';
        }, $filename, array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
        ], self::STREAM_HEADERS));
    }

    private function flushOutput(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    }

    // =========================================================================
    // Helpers gerais
    // =========================================================================

    private function applyDateRange(Builder $query, array $filters, string $column): Builder
    {
        if (!empty($filters['date_from'])) {
            $query->where($column, '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (!empty($filters['date_to'])) {
            $query->where($column, '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
        return $query;
    }

    private function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        return $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d H:i:s')
            : (string) $date;
    }

    private function filename(string $dataset): string
    {
        return $dataset . '-' . now()->format('Y-m-d');
    }
}
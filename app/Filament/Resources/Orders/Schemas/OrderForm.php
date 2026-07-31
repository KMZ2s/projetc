<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([

            // ── Informações do cliente ────────────────────────────────────
            Section::make('Cliente')
                ->schema([
                    Grid::make(2)->schema([
                        Components\Placeholder::make('customer_name')
                            ->label('Nome')
                            ->content(fn ($record) => $record?->customer_name ?? $record?->user?->display_name ?? '—'),

                        Components\Placeholder::make('customer_email')
                            ->label('E-mail')
                            ->content(fn ($record) => $record?->customer_email ?? $record?->user?->email ?? '—'),

                        Components\Placeholder::make('customer_phone')
                            ->label('Telefone')
                            ->content(fn ($record) => $record?->customer_phone ?? $record?->user?->phone ?? '—'),

                        Components\Placeholder::make('customer_document')
                            ->label('CPF / CNPJ')
                            ->content(fn ($record) => $record?->customer_document ?? $record?->user?->cpf_cnpj ?? '—'),
                    ]),
                ])
                ->collapsible()
                ->collapsed(false),

            // ── Itens do pedido ───────────────────────────────────────────
            Section::make('Itens')
                ->schema([
                    Components\Placeholder::make('order_items')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record || $record->items->isEmpty()) {
                                return '—';
                            }

                            $lines = $record->items->map(function ($item) {
                                $subtotal = 'R$ ' . number_format($item->total_price, 2, ',', '.');
                                $unit     = 'R$ ' . number_format($item->unit_price, 2, ',', '.');
                                return "• {$item->product_name} × {$item->quantity} — {$unit} = {$subtotal}";
                            })->join("\n");

                            $total = 'R$ ' . number_format($record->total, 2, ',', '.');

                            return $lines . "\n\nTotal: {$total}";
                        }),
                ])
                ->collapsible()
                ->collapsed(false),

            // ── Endereço de entrega ───────────────────────────────────────
            Section::make('Endereço de entrega')
                ->schema([
                    Grid::make(2)->schema([
                        Components\Placeholder::make('shipping_street')
                            ->label('Rua')
                            ->content(fn ($record) => $record?->shippingAddress
                                ? "{$record->shippingAddress->street}, {$record->shippingAddress->number}"
                                    . ($record->shippingAddress->complement
                                        ? " — {$record->shippingAddress->complement}"
                                        : '')
                                : '—'),

                        Components\Placeholder::make('shipping_neighborhood')
                            ->label('Bairro')
                            ->content(fn ($record) => $record?->shippingAddress?->neighborhood ?? '—'),

                        Components\Placeholder::make('shipping_city')
                            ->label('Cidade / UF')
                            ->content(fn ($record) => $record?->shippingAddress
                                ? "{$record->shippingAddress->city} / {$record->shippingAddress->state}"
                                : '—'),

                        Components\Placeholder::make('shipping_zipcode')
                            ->label('CEP')
                            ->content(fn ($record) => $record?->shippingAddress?->zipcode ?? '—'),
                    ]),
                ])
                ->collapsible()
                ->collapsed(false),

            // ── Status ────────────────────────────────────────────────────
            Section::make('Status')
                ->schema([
                    Grid::make(3)->schema([
                        Components\Select::make('status')
                            ->label('Status do pedido')
                            ->options([
                                'pending'    => 'Pendente',
                                'processing' => 'Processando',
                                'paid'       => 'Pago',
                                'shipped'    => 'Enviado',
                                'delivered'  => 'Entregue',
                                'cancelled'  => 'Cancelado',
                            ])
                            ->required(),

                        Components\Select::make('payment_status')
                            ->label('Status do pagamento')
                            ->options([
                                'pending'  => 'Pendente',
                                'paid'     => 'Pago',
                                'failed'   => 'Falhou',
                                'refunded' => 'Reembolsado',
                            ]),

                        Components\Select::make('fulfillment_status')
                            ->label('Status de entrega')
                            ->options([
                                'pending'   => 'Pendente',
                                'shipped'   => 'Enviado',
                                'delivered' => 'Entregue',
                            ]),
                    ]),

                    Components\Textarea::make('admin_note')
                        ->label('Nota interna')
                        ->rows(3),
                ]),

            // ── Pagamento ─────────────────────────────────────────────────
            Section::make('Dados do pagamento')
                ->schema([
                    Grid::make(2)->schema([
                        Components\TextInput::make('blackcat_transaction_id')
                            ->label('ID da transação (BlackcatPay)')
                            ->disabled()
                            ->placeholder('—'),

                        Components\Placeholder::make('payment_method_label')
                            ->label('Método')
                            ->content(fn ($record) => match ($record?->payment_method) {
                                'pix'         => 'PIX',
                                'credit_card' => 'Cartão de crédito',
                                'debit_card'  => 'Cartão de débito',
                                default       => $record?->payment_method ?? '—',
                            }),
                    ]),

                    Components\Placeholder::make('payment_details')
                        ->label('Detalhes')
                        ->content(function ($record): string {
                            if (!$record || empty($record->payment_data)) {
                                return '—';
                            }

                            $data   = $record->payment_data;
                            $method = $record->payment_method;
                            $lines  = ['Status: ' . ($data['status'] ?? '—')];

                            if ($method === 'pix') {
                                if (!empty($data['expires_at'])) {
                                    $lines[] = 'Expira em: ' . $data['expires_at'];
                                }
                                if (!empty($data['end_to_end_id'])) {
                                    $lines[] = 'End-to-end ID: ' . $data['end_to_end_id'];
                                }
                            }

                            if (in_array($method, ['credit_card', 'debit_card'])) {
                                if (!empty($data['card_brand'])) {
                                    $lines[] = 'Bandeira: ' . $data['card_brand'];
                                }
                                if (!empty($data['last_digits'])) {
                                    $lines[] = 'Final: ' . $data['last_digits'];
                                }
                                if (!empty($data['holder_name'])) {
                                    $lines[] = 'Titular: ' . $data['holder_name'];
                                }
                                if (!empty($data['installments'])) {
                                    $lines[] = 'Parcelas: ' . $data['installments'] . 'x';
                                }
                                if (!empty($data['authorization_code'])) {
                                    $lines[] = 'Autorização: ' . $data['authorization_code'];
                                }
                            }

                            return implode("\n", $lines);
                        }),
                ])
                ->collapsible()
                ->collapsed(fn ($record) => empty($record?->payment_data)),

            // ── Cupom ─────────────────────────────────────────────────────
            Section::make('Cupom')
                ->schema([
                    Grid::make(3)->schema([
                        Components\Placeholder::make('coupon_code')
                            ->label('Código')
                            ->content(fn ($record) => $record?->coupon?->code ?? '—'),

                        Components\Placeholder::make('coupon_type')
                            ->label('Tipo')
                            ->content(fn ($record) => match ($record?->coupon?->type) {
                                'percentage' => 'Percentual (%)',
                                'fixed'      => 'Valor fixo (R$)',
                                default      => '—',
                            }),

                        Components\Placeholder::make('coupon_discount')
                            ->label('Desconto aplicado')
                            ->content(fn ($record) => $record?->discount_total > 0
                                ? 'R$ ' . number_format($record->discount_total, 2, ',', '.')
                                : '—'),
                    ]),
                ])
                ->collapsible()
                ->collapsed(fn ($record) => empty($record?->coupon_id)),

            // ── Nota do cliente ───────────────────────────────────────────
            Section::make('Observações do cliente')
                ->schema([
                    Components\Placeholder::make('customer_note_display')
                        ->label('')
                        ->content(fn ($record) => $record?->customer_note ?? '—'),
                ])
                ->collapsible()
                ->collapsed(fn ($record) => empty($record?->customer_note)),

        ]);
    }
}

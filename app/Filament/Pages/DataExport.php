<?php

namespace App\Filament\Pages;

use App\Models\DataExportLog;
use App\Services\DataExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * /admin/data — exportação de dados em CSV/JSON.
 *
 * Por que não retornar a Response direto da action:
 *
 * Em Livewire 3 + Filament 4, retornar StreamedResponse de uma action
 * faz o Livewire tratar como redirect AJAX e o download não é
 * disparado. Pra streaming funcionar de verdade, precisamos servir
 * via uma rota normal (DataExportController).
 *
 * Fluxo:
 *  1. Action valida inputs via Service::validateInputs().
 *  2. Action grava registro em data_export_logs.
 *  3. Action persiste params em Cache com token UUID (TTL 60s).
 *  4. Action dispara JS que cria um anchor temporário pointing pra
 *     /data-exports/download/{token} e clica nele — força download.
 *  5. Controller lê params do Cache, chama Service::export() e
 *     devolve o StreamedResponse, agora fora do contexto Livewire.
 */
class DataExport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Exportar dados';

    protected static ?string $title = 'Exportar dados';

    protected static ?string $slug = 'data';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.data-export';

    private const DOWNLOAD_TOKEN_TTL = 60;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dataset'   => DataExportService::DATASET_ALL_USERS,
            'format'    => DataExportService::FORMAT_CSV,
            'date_from' => null,
            'date_to'   => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Histórico')
                    ->description('Última exportação realizada.')
                    ->components([
                        Placeholder::make('last_export')
                            ->label('')
                            ->content(fn () => $this->lastExportSummary()),
                    ]),

                Section::make('Parâmetros da exportação')
                    ->description('Escolha o dataset e o formato. Sem intervalo de datas, exporta tudo.')
                    ->components([
                        Select::make('dataset')
                            ->label('Dataset')
                            ->options(DataExportService::DATASETS)
                            ->required()
                            ->native(false)
                            ->helperText('Todos os usuários: TODOS os dados de TODOS os clientes em arquivo único (CSV usa colunas JSON pros relacionamentos). Endereços: tabela pura de endereços, sem dados do cliente.'),

                        Select::make('format')
                            ->label('Formato')
                            ->options(DataExportService::FORMATS)
                            ->required()
                            ->native(false)
                            ->helperText('CSV abre direto no Excel (separador ;). JSON é melhor pra pipelines de dados.'),

                        DatePicker::make('date_from')
                            ->label('De')
                            ->native(false)
                            ->placeholder('Sem limite inicial')
                            ->helperText('Filtra users.created_at (Todos os usuários) ou addresses.created_at (Endereços).'),

                        DatePicker::make('date_to')
                            ->label('Até')
                            ->native(false)
                            ->placeholder('Sem limite final')
                            ->after('date_from'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Header action: valida → grava log → dispara download via JS.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $state = $this->form->getState();

                    $dataset = $state['dataset'] ?? DataExportService::DATASET_ALL_USERS;
                    $format  = $state['format']  ?? DataExportService::FORMAT_CSV;
                    $filters = [
                        'date_from' => $state['date_from'] ?? null,
                        'date_to'   => $state['date_to']   ?? null,
                    ];

                    try {
                        app(DataExportService::class)->validateInputs($dataset, $format, $filters);
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Não foi possível exportar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    DataExportLog::create([
                        'dataset'             => $dataset,
                        'format'              => $format,
                        'filters'             => $this->compactFilters($filters),
                        'exported_by_user_id' => Auth::id(),
                        'exported_at'         => now(),
                    ]);

                    $token = (string) Str::uuid();
                    Cache::put(
                        "data_export:{$token}",
                        [
                            'dataset' => $dataset,
                            'format'  => $format,
                            'filters' => $filters,
                        ],
                        now()->addSeconds(self::DOWNLOAD_TOKEN_TTL)
                    );

                    $url   = route('admin.data.download', ['token' => $token]);
                    $jsUrl = json_encode($url);

                    $this->js(<<<JS
                        (() => {
                            const a = document.createElement('a');
                            a.href = {$jsUrl};
                            a.style.display = 'none';
                            document.body.appendChild(a);
                            a.click();
                            setTimeout(() => document.body.removeChild(a), 0);
                        })();
                    JS);

                    Notification::make()
                        ->title('Exportação iniciada')
                        ->body('O download deve começar em instantes.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function lastExportSummary(): string
    {
        $log = DataExportLog::with('exportedBy:id,email')
            ->latest('exported_at')
            ->first();

        if (!$log) {
            return 'Nenhuma exportação registrada ainda.';
        }

        $when    = $log->exported_at->format('d/m/Y H:i');
        $dataset = $log->datasetLabel();
        $format  = $log->formatLabel();
        $by      = $log->exportedBy?->email;

        return $by
            ? "{$dataset} ({$format}) em {$when} por {$by}"
            : "{$dataset} ({$format}) em {$when}";
    }

    private function compactFilters(array $filters): ?array
    {
        $compacted = array_filter($filters, fn ($v) => $v !== null && $v !== '');
        return empty($compacted) ? null : $compacted;
    }
}
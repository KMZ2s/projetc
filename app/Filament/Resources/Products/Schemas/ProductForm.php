<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([

                // =============================================================
                // 1. IDENTIFICAÇÃO
                // =============================================================
                Section::make('Identificação')
                    ->description('Categoria, nome de exibição e endereço único da página.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label('Categoria')
                            ->required(),

                        Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                    ]),

                // =============================================================
                // 2. CONTEÚDO
                // =============================================================
                Section::make('Conteúdo')
                    ->description('Texto completo da página do produto e resumo curto usado em cards e listagens.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Components\RichEditor::make('description')
                            ->label('Descrição'),

                        Components\Textarea::make('short_description')
                            ->label('Descrição curta')
                            ->rows(2),
                    ]),

                // =============================================================
                // 3. PREÇO
                // =============================================================
                Section::make('Preço')
                    ->description('Preço de venda, preço riscado (de comparação) e preço de custo (interno).')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        Components\TextInput::make('price')
                            ->label('Preço')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),

                        Components\TextInput::make('compare_at_price')
                            ->label('Preço de comparação')
                            ->numeric()
                            ->prefix('R$'),

                        Components\TextInput::make('cost_price')
                            ->label('Preço de custo')
                            ->numeric()
                            ->prefix('R$'),
                    ]),

                // =============================================================
                // 4. INVENTÁRIO (toggle decide entre produto único OU variações)
                // =============================================================
                Section::make('Inventário')
                    ->description('Controle de estoque, SKU e variações como cor, tamanho ou voltagem.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Components\Toggle::make('has_variations')
                            ->label('Esse produto possui variações?')
                            ->helperText('Tamanhos, cores, voltagens — opções com SKU/preço/estoque diferentes.')
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Components\Toggle $component, $record) {
                                $component->state(
                                    $record !== null && $record->variants()->exists()
                                );
                            }),

                        Grid::make(2)
                            ->visible(fn (callable $get) => ! $get('has_variations'))
                            ->schema([
                                Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->unique(ignoreRecord: true),

                                Components\TextInput::make('stock_quantity')
                                    ->label('Estoque')
                                    ->numeric()
                                    ->default(0),
                            ]),

                        Components\Repeater::make('variants')
                            ->label('Variações')
                            ->relationship('variants')
                            ->visible(fn (callable $get) => (bool) $get('has_variations'))
                            ->orderColumn('position')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar variação')
                            ->itemLabel(function (array $state): ?string {
                                if (! empty($state['options']) && is_array($state['options'])) {
                                    $label = collect($state['options'])
                                        ->filter()
                                        ->map(fn ($v, $k) => "{$k}: {$v}")
                                        ->implode(' · ');

                                    if ($label !== '') {
                                        return $label;
                                    }
                                }

                                return $state['sku'] ?? 'Variação';
                            })
                            ->schema([
                                Grid::make(2)->schema([
                                    Components\TextInput::make('sku')
                                        ->label('SKU')
                                        ->required()
                                        ->unique(table: 'variants', ignoreRecord: true),

                                    Components\TextInput::make('price')
                                        ->label('Preço')
                                        ->numeric()
                                        ->required()
                                        ->prefix('R$'),
                                ]),

                                Grid::make(2)->schema([
                                    Components\TextInput::make('stock_quantity')
                                        ->label('Estoque')
                                        ->numeric()
                                        ->default(0),

                                    Components\KeyValue::make('options')
                                        ->label('Opções')
                                        ->keyLabel('Atributo')
                                        ->valueLabel('Valor')
                                        ->addActionLabel('Adicionar opção'),
                                ]),

                                Grid::make(2)
                                    ->schema([
                                        Components\FileUpload::make('variant_images')
                                            ->label('Uploads desta variação')
                                            ->helperText('Opcional. Se vazio, usa as imagens do produto.')
                                            ->multiple()
                                            ->image()
                                            ->disk('public')
                                            ->directory('products')
                                            ->visibility('public')
                                            ->reorderable()
                                            ->appendFiles()
                                            ->maxFiles(10)
                                            ->panelLayout('grid')
                                            ->imagePreviewHeight('100')
                                            ->openable()
                                            ->downloadable()
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (Components\FileUpload $component, $record) {
                                                if ($record) {
                                                    $component->state($record->getImagesForUpload());
                                                }
                                            })
                                            ->saveRelationshipsUsing(function (Components\FileUpload $component, $record) {
                                                if ($record) {
                                                    $paths = array_values(array_filter((array) $component->getState()));
                                                    $record->syncUploadedImages($paths);
                                                }
                                            }),

                                        Components\Repeater::make('variant_external_images')
                                            ->label('Links externos desta variação')
                                            ->helperText('Use URLs de CDN, fornecedor ou imagens importadas. Se vazio, usa as imagens gerais do produto.')
                                            ->dehydrated(false)
                                            ->defaultItems(0)
                                            ->addActionLabel('Adicionar link externo')
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['src'] ?? 'Imagem externa')
                                            ->schema([
                                                Components\TextInput::make('src')
                                                    ->label('URL da imagem')
                                                    ->url()
                                                    ->required()
                                                    ->maxLength(2048)
                                                    ->placeholder('https://cdn.exemplo.com/produto-cor.jpg'),

                                                Components\TextInput::make('alt')
                                                    ->label('Texto alternativo')
                                                    ->maxLength(255)
                                                    ->placeholder('Ex.: Camiseta premium preta'),
                                            ])
                                            ->afterStateHydrated(function (Components\Repeater $component, $record) {
                                                if ($record) {
                                                    $component->state($record->getExternalImagesForForm());
                                                }
                                            })
                                            ->saveRelationshipsUsing(function (Components\Repeater $component, $record) {
                                                if ($record) {
                                                    $record->syncExternalImages((array) $component->getState());
                                                }
                                            }),
                                    ]),
                            ]),
                    ]),

                // =============================================================
                // 5. IMAGENS DO PRODUTO (gerais — fallback das variações)
                // =============================================================
                Section::make('Imagens do produto')
                    ->description('Imagens gerais do produto. Variações com imagens próprias usam estas como fallback.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Components\FileUpload::make('images_upload')
                                    ->label('Uploads do produto')
                                    ->helperText('Arquivos enviados ficam no storage público da loja.')
                                    ->multiple()
                                    ->image()
                                    ->disk('public')
                                    ->directory('products')
                                    ->visibility('public')
                                    ->reorderable()
                                    ->appendFiles()
                                    ->maxFiles(15)
                                    ->panelLayout('grid')
                                    ->imagePreviewHeight('140')
                                    ->openable()
                                    ->downloadable()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (Components\FileUpload $component, $record) {
                                        if ($record) {
                                            $component->state($record->getImagesForUpload());
                                        }
                                    })
                                    ->saveRelationshipsUsing(function (Components\FileUpload $component, $record) {
                                        if ($record) {
                                            $paths = array_values(array_filter((array) $component->getState()));
                                            $record->syncUploadedImages($paths);
                                        }
                                    }),

                                Components\Repeater::make('external_images')
                                    ->label('Links externos do produto')
                                    ->helperText('URLs externas serão salvas como links, sem baixar a imagem para o storage.')
                                    ->dehydrated(false)
                                    ->defaultItems(0)
                                    ->addActionLabel('Adicionar link externo')
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['src'] ?? 'Imagem externa')
                                    ->schema([
                                        Components\TextInput::make('src')
                                            ->label('URL da imagem')
                                            ->url()
                                            ->required()
                                            ->maxLength(2048)
                                            ->placeholder('https://cdn.exemplo.com/produto.jpg'),

                                        Components\TextInput::make('alt')
                                            ->label('Texto alternativo')
                                            ->maxLength(255)
                                            ->placeholder('Ex.: Camiseta premium vista frontal'),
                                    ])
                                    ->afterStateHydrated(function (Components\Repeater $component, $record) {
                                        if ($record) {
                                            $component->state($record->getExternalImagesForForm());
                                        }
                                    })
                                    ->saveRelationshipsUsing(function (Components\Repeater $component, $record) {
                                        if ($record) {
                                            $record->syncExternalImages((array) $component->getState());
                                        }
                                    }),
                            ]),
                    ]),

                // =============================================================
                // 6. PUBLICAÇÃO
                // =============================================================
                Section::make('Publicação')
                    ->description('Status (rascunho, ativo ou inativo) e destaque na vitrine da loja.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Components\Toggle::make('featured')
                            ->label('Produto em destaque'),

                        Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft'    => 'Rascunho',
                                'active'   => 'Ativo',
                                'inactive' => 'Inativo',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }
}
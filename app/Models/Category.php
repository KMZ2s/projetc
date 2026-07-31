<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'status',
        'order',
        'show_in_menu',
    ];

    protected $casts = [
        'order'        => 'integer',
        'show_in_menu' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Filhas — TODAS (inclusive inativas). Usado pelo admin Filament.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Filhas ativas, ordenadas pelo campo `order`. Usado pelo frontend
     * (header global, pills de subcategoria, etc).
     */
    public function activeChildren()
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->where('status', 'active')
            ->orderBy('order');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Categorias-raiz visíveis no menu principal.
     *
     * Critérios:
     *  - parent_id IS NULL (não é filha de ninguém)
     *  - status = 'active'
     *  - show_in_menu = true
     *
     * Ordenadas pelo campo `order`. Use em conjunto com
     * `->with('activeChildren')` pra carregar a árvore inteira em uma
     * única query (header global).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')
            ->where('status', 'active')
            ->where('show_in_menu', true)
            ->orderBy('order');
    }

    // -------------------------------------------------------------------------
    // Helpers de hierarquia
    // -------------------------------------------------------------------------

    /**
     * Retorna os IDs de toda a subárvore desta categoria (próprio ID +
     * descendentes recursivos), filtrando apenas categorias ativas.
     *
     * Usado pelo CollectionController pra implementar o Cenário A da
     * Fase 11: ao acessar /colecao/{pai}, a listagem inclui produtos
     * de TODAS as categorias filhas e netas, não só os atribuídos
     * diretamente à pai.
     *
     * Performance: pra árvores rasas (2-3 níveis, dezenas de categorias)
     * é trivial. Se a loja crescer pra centenas de categorias com 5+
     * níveis, vale trocar por uma CTE recursiva ou cachear o resultado.
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        $children = self::where('parent_id', $this->id)
            ->where('status', 'active')
            ->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    // -------------------------------------------------------------------------
    // Helpers de apresentação (Twig)
    // -------------------------------------------------------------------------

    /**
     * Retorna a árvore de menu como array simples (NÃO models), pronta
     * pra ser usada como global do Twig. Segue a regra arquitetural
     * documentada na Fase 3: nunca passar models Eloquent pro Twig
     * (lazy loading em cascata estoura memória).
     *
     * Estrutura retornada:
     *   [
     *     [
     *       'id'       => int,
     *       'name'     => string,
     *       'slug'     => string,
     *       'image'    => string|null,
     *       'children' => [
     *         ['id'=>..., 'name'=>..., 'slug'=>..., 'image'=>...], ...
     *       ]
     *     ],
     *     ...
     *   ]
     *
     * Profundidade: 1 nível de aninhamento (pai → filhas). Se a loja
     * precisar de hierarquia 3+ no menu, expandir aqui carregando
     * `activeChildren.activeChildren` e mapeando recursivo.
     *
     * Uma query única ao banco com eager loading — barato pra <100
     * categorias raiz. Cache pode ser adicionado depois (Fase 15) com
     * invalidação no observer de Category.
     *
     * @return array<int, array{id:int, name:string, slug:string, image:?string, children:array}>
     */
    public static function menuTree(): array
    {
        return self::query()
            ->roots()
            ->with('activeChildren')
            ->get()
            ->map(fn (self $cat) => [
                'id'       => $cat->id,
                'name'     => $cat->name,
                'slug'     => $cat->slug,
                'image'    => $cat->image,
                'children' => $cat->activeChildren
                    ->map(fn (self $child) => [
                        'id'    => $child->id,
                        'name'  => $child->name,
                        'slug'  => $child->slug,
                        'image' => $child->image,
                    ])
                    ->toArray(),
            ])
            ->toArray();
    }
}
<?php

namespace App\Twig;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ReplicantfyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'formatMoney']),
            new TwigFilter('img_url', [$this, 'imageUrl']),
            new TwigFilter('date_br', [$this, 'dateBr']),
            new TwigFilter('json_b64', [$this, 'jsonBase64']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_settings', 'theme_settings'),
        ];
    }

    // -------------------------------------------------------------------------
    // Filtros
    // -------------------------------------------------------------------------

    /**
     * Formata um valor decimal (em reais) com o símbolo da moeda da loja.
     * O símbolo é cacheado para evitar uma query por chamada ao filtro.
     *
     * Uso no Twig: {{ product.price|money }}
     */
    public function formatMoney(float|int|string $value): string
    {
        $symbol = Cache::remember('store_currency_symbol', 3600, function () {
            return Store::first()?->currency_symbol ?? 'R$';
        });

        return $symbol.' '.number_format((float) $value, 2, ',', '.');
    }

    /**
     * Resolve o URL de uma imagem — suporta paths do Storage e URLs externas.
     *
     * Uso no Twig: {{ product.images[0].src|img_url }}
     */
    public function imageUrl(?string $path): string
    {
        if (! $path) {
            return '';
        }

        // URL externa ou data URI — devolve como veio
        if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, 'data:')) {
            return $path;
        }

        // Normaliza barras duplas/triplas e remove barra inicial
        $path = ltrim(preg_replace('#/+#', '/', $path), '/');

        // Já tem prefixo storage/ → é absoluto a partir de /public
        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        // É asset do tema (em /public/themes/...)
        if (str_starts_with($path, 'themes/')) {
            return asset($path);
        }

        // Caminho relativo ao disk public → prefixa storage/
        return asset('storage/'.$path);
    }

    /**
     * Formata uma data no padrão brasileiro.
     *
     * Uso no Twig: {{ order.placed_at|date_br }}
     */
    public function dateBr(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('d/m/Y H:i');
        }

        try {
            return (new \DateTime((string) $date))->format('d/m/Y H:i');
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Serializa dados públicos para um bloco JavaScript sem permitir que
     * conteúdo dinâmico encerre a tag <script>.
     */
    public function jsonBase64(mixed $value): string
    {
        return base64_encode(json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }
}

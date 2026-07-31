<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'browser_language',
        'color_depth',
        'screen_height',
        'screen_width',
        'time_difference',
        'java_enabled',
        'javascript_enabled',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'java_enabled'       => 'boolean',
        'javascript_enabled' => 'boolean',
        'color_depth'        => 'integer',
        'screen_height'      => 'integer',
        'screen_width'       => 'integer',
        'time_difference'    => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Formata o device para o formato esperado pela BlackcatPay API.
     *
     * Nota: ip_address NÃO é enviado ao gateway — o BlackcatPay já lê
     * o IP da própria conexão HTTP. Aqui é só persistência local pra
     * análise via DataExportService (Fase 8).
     */
    public function toBlackcatPayload(): array
    {
        return [
            'http_browser_language'        => $this->browser_language,
            'http_browser_color_depth'     => $this->color_depth,
            'http_browser_screen_height'   => $this->screen_height,
            'http_browser_screen_width'    => $this->screen_width,
            'http_browser_time_difference' => $this->time_difference,
            'http_browser_java_enabled'    => $this->java_enabled,
            'http_browser_javascript_enabled' => $this->javascript_enabled,
            'user_agent'                   => $this->user_agent,
        ];
    }
}
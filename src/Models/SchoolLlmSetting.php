<?php

declare(strict_types=1);

namespace Escolar\Ai\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração de LLM por escola (tabela `school_llm_settings`, schema do tenant).
 *
 * Model canônico do pacote. Os apps podem estender (ex.: `App\Models\SchoolLlmSetting`
 * no APP adiciona a relation school()) — mesma tabela, mesmo comportamento.
 *
 * ATENÇÃO cross-app: `api_key` usa cast `encrypted` (APP_KEY). Apps com APP_KEY
 * diferente do app que gravou a chave NÃO conseguem decriptar — o
 * {@see \Escolar\Ai\Support\SchoolLlmConfigResolver} degrada para as credenciais
 * globais do provider nesse caso.
 */
class SchoolLlmSetting extends Model
{
    protected $table = 'school_llm_settings';

    protected $fillable = [
        'school_id',
        'name',
        'is_enabled',
        'is_default',
        'provider',
        'model',
        'api_key',
        'daily_cost_limit_usd',
        'use_custom_base_url',
        'base_url',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'daily_cost_limit_usd' => 'decimal:4',
        'use_custom_base_url' => 'boolean',
        'metadata' => 'array',
        'api_key' => 'encrypted',
    ];

    /**
     * Apenas a LLM ativa (default) da escola.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}

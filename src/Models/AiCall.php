<?php

declare(strict_types=1);

namespace Escolar\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Telemetria de chamadas a LLMs (tabela `ai_calls`, schema do tenant).
 *
 * Model canônico do pacote. Os apps podem estender (ex.: `App\Models\AiCall`
 * no APP adiciona HasFactory) — mesma tabela, mesmo comportamento.
 *
 * @property int $id
 * @property string $public_id
 * @property string|null $tenant_id
 * @property int|null $school_id
 * @property string $action
 * @property string|null $agent_class
 * @property string $provider
 * @property string $model
 * @property int $tokens_input
 * @property int $tokens_output
 * @property string $cost_usd
 * @property int $latency_ms
 * @property bool $success
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiCall extends Model
{
    protected $table = 'ai_calls';

    protected $fillable = [
        'public_id',
        'tenant_id',
        'school_id',
        'action',
        'agent_class',
        'provider',
        'model',
        'tokens_input',
        'tokens_output',
        'cost_usd',
        'latency_ms',
        'success',
        'error_message',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'latency_ms' => 'integer',
            'cost_usd' => 'decimal:6',
            'success' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->public_id)) {
                $model->public_id = strtolower((string) Str::ulid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}

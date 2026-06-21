<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    protected $fillable = [
        'case_id', 'score', 'level', 'pep_hit', 'sanctions_hit',
        'adverse_media', 'shell_company', 'factors', 'screened_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'pep_hit' => 'boolean',
            'sanctions_hit' => 'boolean',
            'adverse_media' => 'boolean',
            'shell_company' => 'boolean',
            'factors' => 'array',
            'screened_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'case_id');
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'low' => '低风险',
            'medium' => '中风险',
            'high' => '高风险',
            'prohibited' => '禁止准入',
            default => $this->level,
        };
    }
}

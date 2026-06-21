<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['case_id', 'user_id', 'level', 'decision', 'comment'];

    protected function casts(): array
    {
        return [];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'first' => '初审',
            'final' => '终审',
            default => $this->level,
        };
    }

    public function getDecisionLabelAttribute(): string
    {
        return match ($this->decision) {
            'approve' => '通过',
            'reject' => '驳回',
            'return' => '退回补正',
            default => $this->decision,
        };
    }
}

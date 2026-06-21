<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeneficialOwner extends Model
{
    protected $fillable = [
        'case_id', 'name', 'id_type', 'id_number', 'ownership_percent',
        'nationality', 'is_pep', 'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percent' => 'decimal:2',
            'is_pep' => 'boolean',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'case_id');
    }
}

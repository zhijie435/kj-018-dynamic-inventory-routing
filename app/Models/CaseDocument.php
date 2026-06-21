<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseDocument extends Model
{
    protected $fillable = [
        'case_id', 'type', 'filename', 'path', 'mime_type', 'size',
        'ocr_status', 'ocr_result',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'ocr_result' => 'array',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'case_id');
    }
}

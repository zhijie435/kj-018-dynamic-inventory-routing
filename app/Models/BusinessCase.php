<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessCase extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'case_no', 'business_id', 'status', 'risk_level', 'risk_score',
        'assigned_to', 'created_by', 'summary', 'submitted_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function beneficialOwners(): HasMany
    {
        return $this->hasMany(BeneficialOwner::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function riskAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class, 'case_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'case_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => '草稿',
            'screening' => '筛查中',
            'pending_review' => '待初审',
            'reviewing' => '审核中',
            'approved' => '已通过',
            'rejected' => '已驳回',
            default => $this->status,
        };
    }
}

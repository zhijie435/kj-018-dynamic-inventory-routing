<?php

namespace App\Models;

use Database\Factories\InventorySourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin InventorySourceFactory
 */
#[Fillable(['code', 'name', 'type', 'country', 'city', 'address', 'timezone', 'priority', 'is_active'])]
class InventorySource extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'priority' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class)
            ->withPivot(['sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function isPrimaryForChannel(int $channelId): bool
    {
        return $this->channels()
            ->where('channel_id', $channelId)
            ->wherePivot('is_primary', true)
            ->exists();
    }
}

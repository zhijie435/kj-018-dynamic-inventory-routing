<?php

namespace App\Models;

use App\Services\ChannelInventoryStateManager;
use Database\Factories\InventorySourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\App;

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

    protected static function booted(): void
    {
        static::updated(function (self $source) {
            if ($source->isDirty('is_active')) {
                $wasActive = (bool) $source->getOriginal('is_active');
                $isActiveNow = (bool) $source->is_active;

                if ($wasActive && !$isActiveNow) {
                    static::handleDeactivation($source);
                }
            }
        });

        static::deleting(function (self $source) {
            static::handleDeactivation($source);
        });
    }

    protected static function handleDeactivation(self $source): void
    {
        App::make(ChannelInventoryStateManager::class)->handleSourceDeactivation($source);
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

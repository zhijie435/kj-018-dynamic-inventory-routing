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
        $channels = $source->channels()->get();

        $source->channels()->detach();

        foreach ($channels as $channel) {
            $hasPrimary = $channel->inventorySources()
                ->wherePivot('is_primary', true)
                ->exists();

            if (!$hasPrimary) {
                $firstActive = $channel->inventorySources()
                    ->where('inventory_sources.is_active', true)
                    ->orderByPivot('sort_order')
                    ->orderBy('inventory_sources.priority', 'ASC')
                    ->orderBy('inventory_sources.country')
                    ->first();

                if ($firstActive) {
                    $channel->inventorySources()->updateExistingPivot($firstActive->id, [
                        'is_primary' => true,
                    ]);
                }
            }
        }
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

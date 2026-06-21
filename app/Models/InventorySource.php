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
            $remainingSources = $channel->inventorySources()
                ->orderByPivot('sort_order')
                ->orderBy('inventory_sources.priority', 'DESC')
                ->orderBy('inventory_sources.country')
                ->get();

            $primaryFound = false;

            foreach ($remainingSources as $index => $remainingSource) {
                $isPrimary = (bool) $remainingSource->pivot->is_primary;
                if ($isPrimary && !$primaryFound) {
                    $primaryFound = true;
                } elseif ($isPrimary && $primaryFound) {
                    $isPrimary = false;
                }

                $channel->allInventorySources()->updateExistingPivot($remainingSource->id, [
                    'sort_order' => $index,
                    'is_primary' => $isPrimary,
                ]);
            }

            if (!$primaryFound && $remainingSources->isNotEmpty()) {
                $firstSource = $remainingSources->first();
                $channel->allInventorySources()->updateExistingPivot($firstSource->id, [
                    'is_primary' => true,
                ]);
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

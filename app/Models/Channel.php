<?php

namespace App\Models;

use App\Services\ChannelInventoryStateManager;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * @mixin ChannelFactory
 */
#[Fillable(['code', 'name', 'region', 'currency', 'locale', 'description', 'is_active'])]
class Channel extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function inventorySources(): BelongsToMany
    {
        return $this->belongsToMany(InventorySource::class)
            ->withPivot(['sort_order', 'is_primary'])
            ->withTimestamps()
            ->where('inventory_sources.is_active', true)
            ->orderByPivot('sort_order')
            ->orderBy('inventory_sources.priority', 'DESC')
            ->orderBy('inventory_sources.country');
    }

    public function allInventorySources(): BelongsToMany
    {
        return $this->belongsToMany(InventorySource::class)
            ->withPivot(['sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('inventory_sources.priority', 'DESC')
            ->orderBy('inventory_sources.country');
    }

    public function primaryInventorySource()
    {
        return $this->belongsToMany(InventorySource::class)
            ->withPivot(['sort_order', 'is_primary'])
            ->wherePivot('is_primary', true)
            ->where('inventory_sources.is_active', true)
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('inventory_sources.priority', 'DESC')
            ->orderBy('inventory_sources.country')
            ->limit(1);
    }

    public function syncInventorySources(array $inventorySourceIds): void
    {
        App::make(ChannelInventoryStateManager::class)->syncSources($this, $inventorySourceIds);
    }

    public function syncInventorySourcesPreservingInactive(array $inventorySourceIds): void
    {
        $currentlyBoundInactive = $this->allInventorySources()
            ->where('inventory_sources.is_active', false)
            ->get(['inventory_sources.id', 'sort_order', 'is_primary'])
            ->keyBy('id')
            ->map(function ($source) {
                return [
                    'sort_order' => $source->pivot->sort_order,
                    'is_primary' => (bool) $source->pivot->is_primary,
                ];
            })
            ->toArray();

        $this->syncInventorySources($inventorySourceIds);

        if (!empty($currentlyBoundInactive)) {
            $this->allInventorySources()->syncWithoutDetaching($currentlyBoundInactive);
        }
    }

    public function removeInactiveInventorySources(): void
    {
        App::make(ChannelInventoryStateManager::class)->removeInactiveAndRebuild($this);
    }

    public function hasInventorySource(int $inventorySourceId): bool
    {
        return $this->inventorySources()->where('inventory_source_id', $inventorySourceId)->exists();
    }
}

<?php

namespace App\Models;

use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

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
            ->orderByPivot('sort_order');
    }

    public function primaryInventorySource()
    {
        return $this->belongsToMany(InventorySource::class)
            ->withPivot(['sort_order', 'is_primary'])
            ->wherePivot('is_primary', true)
            ->withTimestamps()
            ->limit(1);
    }

    public function syncInventorySources(array $inventorySourceIds): void
    {
        $rawEntries = [];

        foreach ($inventorySourceIds as $index => $id) {
            if (is_array($id)) {
                $sourceId = $id['id'];
                $isPrimary = $id['is_primary'] ?? false;
                $sortOrder = $id['sort_order'] ?? $index;
            } else {
                $sourceId = $id;
                $isPrimary = ($index === 0);
                $sortOrder = $index;
            }
            $rawEntries[] = [
                'id' => $sourceId,
                'is_primary' => $isPrimary,
                'sort_order' => $sortOrder,
            ];
        }

        $sourceIds = array_column($rawEntries, 'id');

        $activeSources = InventorySource::query()
            ->whereIn('id', $sourceIds)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        $activeIdSet = array_flip($activeSources);

        $filteredEntries = [];
        foreach ($rawEntries as $entry) {
            if (isset($activeIdSet[$entry['id']])) {
                $filteredEntries[] = $entry;
            }
        }

        $syncData = [];
        $primaryFound = false;

        foreach ($filteredEntries as $entry) {
            $isPrimary = (bool) $entry['is_primary'];

            if ($isPrimary && !$primaryFound) {
                $primaryFound = true;
            } elseif ($isPrimary && $primaryFound) {
                $isPrimary = false;
            }

            $syncData[$entry['id']] = [
                'sort_order' => $entry['sort_order'],
                'is_primary' => $isPrimary,
            ];
        }

        if (!$primaryFound && !empty($syncData)) {
            uasort($syncData, function ($a, $b) {
                return $a['sort_order'] <=> $b['sort_order'];
            });
            $firstKey = array_key_first($syncData);
            $syncData[$firstKey]['is_primary'] = true;
        }

        $this->inventorySources()->sync($syncData);
    }

    public function syncInventorySourcesPreservingInactive(array $inventorySourceIds): void
    {
        $currentlyBoundInactive = $this->inventorySources()
            ->where('inventory_sources.is_active', false)
            ->get(['inventory_sources.id', 'sort_order', 'is_primary'])
            ->map(function ($source) {
                return [
                    'id' => $source->id,
                    'sort_order' => $source->pivot->sort_order,
                    'is_primary' => (bool) $source->pivot->is_primary,
                ];
            })
            ->toArray();

        $merged = array_merge($inventorySourceIds, $currentlyBoundInactive);

        $this->syncInventorySources($merged);
    }

    public function removeInactiveInventorySources(): void
    {
        $inactiveIds = $this->inventorySources()
            ->where('inventory_sources.is_active', false)
            ->pluck('inventory_sources.id')
            ->toArray();

        if (empty($inactiveIds)) {
            return;
        }

        $this->inventorySources()->detach($inactiveIds);

        $hasPrimary = $this->inventorySources()
            ->wherePivot('is_primary', true)
            ->exists();

        if (!$hasPrimary) {
            $firstActive = $this->inventorySources()
                ->where('inventory_sources.is_active', true)
                ->orderByPivot('sort_order')
                ->first();

            if ($firstActive) {
                $this->inventorySources()->updateExistingPivot($firstActive->id, [
                    'is_primary' => true,
                ]);
            }
        }
    }

    public function hasInventorySource(int $inventorySourceId): bool
    {
        return $this->inventorySources()->where('inventory_source_id', $inventorySourceId)->exists();
    }
}

<?php

namespace App\Models;

use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        $syncData = [];
        $primaryFound = false;

        foreach ($inventorySourceIds as $index => $id) {
            $isPrimary = false;
            if (is_array($id)) {
                $sourceId = $id['id'];
                $isPrimary = $id['is_primary'] ?? false;
                $sortOrder = $id['sort_order'] ?? $index;
            } else {
                $sourceId = $id;
                $sortOrder = $index;
                if ($index === 0 && !$primaryFound) {
                    $isPrimary = true;
                }
            }

            if ($isPrimary && !$primaryFound) {
                $primaryFound = true;
            } elseif ($isPrimary && $primaryFound) {
                $isPrimary = false;
            }

            $syncData[$sourceId] = [
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
            ];
        }

        if (!$primaryFound && !empty($syncData)) {
            $firstKey = array_key_first($syncData);
            $syncData[$firstKey]['is_primary'] = true;
        }

        $this->inventorySources()->sync($syncData);
    }

    public function hasInventorySource(int $inventorySourceId): bool
    {
        return $this->inventorySources()->where('inventory_source_id', $inventorySourceId)->exists();
    }
}

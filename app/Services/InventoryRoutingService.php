<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\InventorySource;
use Illuminate\Database\Eloquent\Collection;

class InventoryRoutingService
{
    public function getAvailableSources(Channel $channel): Collection
    {
        return $channel->inventorySources()
            ->where('inventory_sources.is_active', true)
            ->orderByPivot('sort_order')
            ->orderBy('inventory_sources.priority', 'DESC')
            ->orderBy('inventory_sources.country')
            ->get();
    }

    public function getPrimarySource(Channel $channel): ?InventorySource
    {
        return $channel->inventorySources()
            ->wherePivot('is_primary', true)
            ->where('inventory_sources.is_active', true)
            ->first();
    }

    public function getRoutedSource(Channel $channel, array $options = []): ?InventorySource
    {
        $preferredSourceId = $options['preferred_source_id'] ?? null;
        $country = $options['country'] ?? null;
        $city = $options['city'] ?? null;
        $minPriority = $options['min_priority'] ?? null;

        $sources = $channel->inventorySources()
            ->where('inventory_sources.is_active', true)
            ->orderByPivot('sort_order')
            ->orderBy('inventory_sources.priority', 'DESC')
            ->orderBy('inventory_sources.country')
            ->get();

        if ($sources->isEmpty()) {
            return null;
        }

        if ($country) {
            $countryMatch = $sources->firstWhere('country', $country);
            if ($countryMatch) {
                return $countryMatch;
            }
        }

        if ($preferredSourceId) {
            $preferred = $sources->firstWhere('id', $preferredSourceId);
            if ($preferred) {
                return $preferred;
            }
        }

        if ($minPriority !== null) {
            $priorityMatch = $sources->first(function ($source) use ($minPriority) {
                return $source->priority >= $minPriority;
            });
            if ($priorityMatch) {
                return $priorityMatch;
            }
        }

        $primary = $sources->firstWhere('pivot.is_primary', true);
        if ($primary) {
            return $primary;
        }

        return $sources->first();
    }

    public function getSourceWithFallback(Channel $channel, int $inventorySourceId): ?InventorySource
    {
        $sources = $this->getAvailableSources($channel);

        $requested = $sources->firstWhere('id', $inventorySourceId);
        if ($requested) {
            return $requested;
        }

        return $this->getPrimarySource($channel) ?? $sources->first();
    }

    public function canRouteToSource(Channel $channel, int $inventorySourceId): bool
    {
        return $channel->inventorySources()
            ->where('inventory_source_id', $inventorySourceId)
            ->where('inventory_sources.is_active', true)
            ->exists();
    }

    public function getRoutingOrder(Channel $channel): array
    {
        return $channel->inventorySources()
            ->where('inventory_sources.is_active', true)
            ->orderByPivot('sort_order')
            ->orderBy('inventory_sources.priority', 'DESC')
            ->orderBy('inventory_sources.country')
            ->get()
            ->map(function ($source) {
                return [
                    'id' => $source->id,
                    'code' => $source->code,
                    'name' => $source->name,
                    'is_primary' => (bool) $source->pivot->is_primary,
                    'sort_order' => $source->pivot->sort_order,
                    'country' => $source->country,
                    'city' => $source->city,
                    'priority' => $source->priority,
                ];
            })
            ->toArray();
    }
}

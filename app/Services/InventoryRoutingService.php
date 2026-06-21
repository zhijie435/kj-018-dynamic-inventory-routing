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
        $result = $this->getRoutedSourceWithMeta($channel, $options);

        return $result['source'] ?? null;
    }

    public function getRoutedSourceWithMeta(Channel $channel, array $options = []): array
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
            return [
                'source' => null,
                'route_type' => 'none',
                'is_moq_direct' => false,
                'fallback_to_cn' => false,
            ];
        }

        if ($country) {
            $countryMatch = $sources->firstWhere('country', $country);
            if ($countryMatch) {
                return [
                    'source' => $countryMatch,
                    'route_type' => 'country_match',
                    'is_moq_direct' => false,
                    'fallback_to_cn' => false,
                    'matched_country' => $country,
                ];
            }

            $cnMatch = $sources->firstWhere('country', 'CN');
            if ($cnMatch) {
                return [
                    'source' => $cnMatch,
                    'route_type' => 'cn_moq_fallback',
                    'is_moq_direct' => true,
                    'fallback_to_cn' => true,
                    'requested_country' => $country,
                    'matched_country' => 'CN',
                ];
            }
        }

        if ($preferredSourceId) {
            $preferred = $sources->firstWhere('id', $preferredSourceId);
            if ($preferred) {
                return [
                    'source' => $preferred,
                    'route_type' => 'preferred_source',
                    'is_moq_direct' => false,
                    'fallback_to_cn' => false,
                    'preferred_source_id' => $preferredSourceId,
                ];
            }
        }

        if ($minPriority !== null) {
            $priorityMatch = $sources->first(function ($source) use ($minPriority) {
                return $source->priority >= $minPriority;
            });
            if ($priorityMatch) {
                return [
                    'source' => $priorityMatch,
                    'route_type' => 'priority_match',
                    'is_moq_direct' => false,
                    'fallback_to_cn' => false,
                    'min_priority' => $minPriority,
                ];
            }
        }

        $primary = $sources->firstWhere('pivot.is_primary', true);
        if ($primary) {
            return [
                'source' => $primary,
                'route_type' => 'primary',
                'is_moq_direct' => false,
                'fallback_to_cn' => false,
            ];
        }

        return [
            'source' => $sources->first(),
            'route_type' => 'first_available',
            'is_moq_direct' => false,
            'fallback_to_cn' => false,
        ];
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

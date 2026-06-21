<?php

namespace App\Services;

use App\Exceptions\InventoryRoutingException;
use App\Models\Channel;
use App\Models\InventorySource;
use App\Repositories\ChannelRepository;
use Illuminate\Database\Eloquent\Collection;

class InventoryRoutingService
{
    protected ChannelRepository $channelRepository;

    public function __construct(?ChannelRepository $channelRepository = null)
    {
        $this->channelRepository = $channelRepository ?? app(ChannelRepository::class);
    }

    public function getAvailableSources(Channel $channel): Collection
    {
        return $this->channelRepository->getActiveInventorySources($channel);
    }

    public function getPrimarySource(Channel $channel): ?InventorySource
    {
        return $this->channelRepository->getPrimaryInventorySource($channel);
    }

    public function getRoutedSource(Channel $channel, array $options = []): ?InventorySource
    {
        $result = $this->getRoutedSourceWithMeta($channel, $options);

        return $result['source'] ?? null;
    }

    public function getRoutedSourceWithMeta(Channel $channel, array $options = []): array
    {
        $sources = $this->getAvailableSources($channel);

        if ($sources->isEmpty()) {
            return [
                'source' => null,
                'route_type' => 'none',
                'is_moq_direct' => false,
                'fallback_to_cn' => false,
            ];
        }

        $strategies = [
            fn () => $this->tryCountryMatch($sources, $options),
            fn () => $this->tryPreferredSource($sources, $options),
            fn () => $this->tryPriorityMatch($sources, $options),
            fn () => $this->tryPrimarySource($sources),
            fn () => $this->fallbackToFirst($sources),
        ];

        foreach ($strategies as $strategy) {
            $result = $strategy();
            if ($result !== null) {
                return $result;
            }
        }

        return [
            'source' => null,
            'route_type' => 'none',
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
        return $this->channelRepository->hasInventorySource($channel, $inventorySourceId);
    }

    public function assertCanRouteToSource(Channel $channel, int $inventorySourceId): void
    {
        if (!$this->canRouteToSource($channel, $inventorySourceId)) {
            throw InventoryRoutingException::cannotRouteToSource($channel->id, $inventorySourceId);
        }
    }

    public function getRoutingOrder(Channel $channel): array
    {
        return $this->getAvailableSources($channel)
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

    private function tryCountryMatch(Collection $sources, array $options): ?array
    {
        $country = $options['country'] ?? null;
        if (!$country) {
            return null;
        }

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

        return null;
    }

    private function tryPreferredSource(Collection $sources, array $options): ?array
    {
        $preferredSourceId = $options['preferred_source_id'] ?? null;
        if (!$preferredSourceId) {
            return null;
        }

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

        return null;
    }

    private function tryPriorityMatch(Collection $sources, array $options): ?array
    {
        $minPriority = $options['min_priority'] ?? null;
        if ($minPriority === null) {
            return null;
        }

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

        return null;
    }

    private function tryPrimarySource(Collection $sources): ?array
    {
        $primary = $sources->firstWhere('pivot.is_primary', true);
        if ($primary) {
            return [
                'source' => $primary,
                'route_type' => 'primary',
                'is_moq_direct' => false,
                'fallback_to_cn' => false,
            ];
        }

        return null;
    }

    private function fallbackToFirst(Collection $sources): ?array
    {
        $first = $sources->first();
        if (!$first) {
            return null;
        }

        return [
            'source' => $first,
            'route_type' => 'first_available',
            'is_moq_direct' => false,
            'fallback_to_cn' => false,
        ];
    }
}

<?php

namespace App\Services;

use App\Exceptions\StateTransitionException;
use App\Models\Channel;
use App\Models\InventorySource;
use App\Repositories\ChannelRepository;
use App\Repositories\InventorySourceRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChannelInventoryStateManager
{
    protected ChannelRepository $channelRepository;
    protected InventorySourceRepository $inventorySourceRepository;

    public function __construct(
        ?ChannelRepository $channelRepository = null,
        ?InventorySourceRepository $inventorySourceRepository = null
    ) {
        $this->channelRepository = $channelRepository ?? app(ChannelRepository::class);
        $this->inventorySourceRepository = $inventorySourceRepository ?? app(InventorySourceRepository::class);
    }

    public function syncSources(Channel $channel, array $sourceEntries): void
    {
        DB::transaction(function () use ($channel, $sourceEntries) {
            $normalized = $this->normalizeEntries($sourceEntries);

            $sourceIds = array_column($normalized, 'id');
            $activeSources = $this->inventorySourceRepository->getActiveByIds($sourceIds);
            $activeIdSet = $activeSources->pluck('id')->flip()->toArray();

            $filtered = array_values(array_filter(
                $normalized,
                fn (array $entry) => isset($activeIdSet[$entry['id']])
            ));

            if (empty($filtered)) {
                $channel->allInventorySources()->sync([]);
                return;
            }

            $syncData = $this->buildSyncData($filtered);

            $channel->allInventorySources()->sync($syncData);
        });
    }

    public function handleSourceDeactivation(InventorySource $source): void
    {
        DB::transaction(function () use ($source) {
            $channels = $source->channels()->get();

            $source->channels()->detach();

            foreach ($channels as $channel) {
                $this->rebuildChannelBindings($channel);
            }
        });
    }

    public function removeInactiveAndRebuild(Channel $channel): void
    {
        DB::transaction(function () use ($channel) {
            $pivotTable = $channel->allInventorySources()->getTable();
            $foreignPivotKey = $channel->allInventorySources()->getForeignPivotKeyName();

            $allBindings = DB::table($pivotTable)
                ->where($foreignPivotKey, $channel->id)
                ->join('inventory_sources', 'inventory_sources.id', '=', 'inventory_source_id')
                ->select('inventory_source_id', 'sort_order', 'is_primary', 'inventory_sources.is_active as source_active')
                ->get();

            $inactiveIds = $allBindings
                ->filter(fn ($row) => !$row->source_active)
                ->pluck('inventory_source_id')
                ->toArray();

            if (!empty($inactiveIds)) {
                DB::table($pivotTable)
                    ->where($foreignPivotKey, $channel->id)
                    ->whereIn('inventory_source_id', $inactiveIds)
                    ->delete();
            }

            $this->rebuildChannelBindings($channel);
        });
    }

    public function rebuildChannelBindings(Channel $channel): void
    {
        $remaining = $this->channelRepository->getActiveInventorySources($channel);

        if ($remaining->isEmpty()) {
            return;
        }

        $sorted = $this->sortSourcesForBinding($remaining);

        $primaryFound = false;
        $updates = [];

        foreach ($sorted as $index => $source) {
            $isPrimary = (bool) $source->pivot->is_primary;

            if ($isPrimary && !$primaryFound) {
                $primaryFound = true;
            } elseif ($isPrimary && $primaryFound) {
                $isPrimary = false;
            }

            $updates[$source->id] = [
                'sort_order' => $index,
                'is_primary' => $isPrimary,
            ];
        }

        if (!$primaryFound) {
            $firstId = array_key_first($updates);
            $updates[$firstId]['is_primary'] = true;
        }

        foreach ($updates as $sourceId => $data) {
            $channel->allInventorySources()->updateExistingPivot($sourceId, $data);
        }
    }

    private function normalizeEntries(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $index => $entry) {
            if (is_array($entry)) {
                if (!isset($entry['id'])) {
                    throw new StateTransitionException(
                        'Each inventory source entry must contain an "id" field.',
                        'INVALID_SOURCE_ENTRY',
                        422,
                        ['entry_index' => $index]
                    );
                }
                $normalized[] = [
                    'id' => (int) $entry['id'],
                    'is_primary' => (bool) ($entry['is_primary'] ?? false),
                    'sort_order' => (int) ($entry['sort_order'] ?? $index),
                ];
            } else {
                $normalized[] = [
                    'id' => (int) $entry,
                    'is_primary' => ($index === 0),
                    'sort_order' => $index,
                ];
            }
        }

        return $normalized;
    }

    private function buildSyncData(array $filteredEntries): array
    {
        usort($filteredEntries, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

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
            $firstKey = array_key_first($syncData);
            $syncData[$firstKey]['is_primary'] = true;
        }

        return $syncData;
    }

    private function sortSourcesForBinding(Collection $sources): array
    {
        return $sources
            ->sortBy(function ($source) {
                return [
                    $source->pivot->sort_order ?? PHP_INT_MAX,
                    -($source->priority ?? 0),
                    $source->country ?? '',
                ];
            })
            ->values()
            ->all();
    }
}

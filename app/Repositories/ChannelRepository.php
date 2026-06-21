<?php

namespace App\Repositories;

use App\Models\Channel;
use App\Models\InventorySource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ChannelRepository extends BaseRepository
{
    public function __construct(Channel $model)
    {
        parent::__construct($model);
    }

    public function search(Request $request, array $relations = ['inventorySources']): Collection
    {
        $query = $this->query()->with($relations);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('region')) {
            $query->where('region', $request->input('region'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query->orderBy('code')->get();
    }

    public function findWithInventorySources(int $id): ?Channel
    {
        return $this->query()
            ->with(['inventorySources'])
            ->find($id);
    }

    public function getWithInventorySources(int $id): Channel
    {
        return $this->query()
            ->with(['inventorySources'])
            ->findOrFail($id);
    }

    public function getActiveInventorySources(Channel $channel): Collection
    {
        return $channel->inventorySources()
            ->where('inventory_sources.is_active', true)
            ->orderByPivot('sort_order')
            ->orderBy('inventory_sources.priority', 'DESC')
            ->orderBy('inventory_sources.country')
            ->get();
    }

    public function getPrimaryInventorySource(Channel $channel): ?InventorySource
    {
        return $channel->inventorySources()
            ->wherePivot('is_primary', true)
            ->where('inventory_sources.is_active', true)
            ->first();
    }

    public function hasInventorySource(Channel $channel, int $inventorySourceId): bool
    {
        return $channel->inventorySources()
            ->where('inventory_source_id', $inventorySourceId)
            ->where('inventory_sources.is_active', true)
            ->exists();
    }
}

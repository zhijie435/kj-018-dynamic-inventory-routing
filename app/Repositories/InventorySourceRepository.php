<?php

namespace App\Repositories;

use App\Models\InventorySource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class InventorySourceRepository extends BaseRepository
{
    public function __construct(InventorySource $model)
    {
        parent::__construct($model);
    }

    public function search(Request $request, array $relations = ['channels']): Collection
    {
        $query = $this->query()->with($relations);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query
            ->orderBy('priority', 'desc')
            ->orderBy('code')
            ->get();
    }

    public function findWithChannels(int $id): ?InventorySource
    {
        return $this->query()
            ->with(['channels'])
            ->find($id);
    }

    public function getWithChannels(int $id): InventorySource
    {
        return $this->query()
            ->with(['channels'])
            ->findOrFail($id);
    }

    public function getActiveByIds(array $ids): Collection
    {
        return $this->query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();
    }
}

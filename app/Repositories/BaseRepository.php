<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function findById(int $id, array $relations = []): ?Model
    {
        return $this->query()
            ->with($relations)
            ->find($id);
    }

    public function getById(int $id, array $relations = []): Model
    {
        return $this->query()
            ->with($relations)
            ->findOrFail($id);
    }

    public function all(array $relations = []): Collection
    {
        return $this->query()
            ->with($relations)
            ->get();
    }

    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    public function update(Model $model, array $attributes): bool
    {
        return $model->update($attributes);
    }

    public function delete(Model $model): ?bool
    {
        return $model->delete();
    }

    public function paginate(int $perPage = 15, array $relations = [])
    {
        return $this->query()
            ->with($relations)
            ->paginate($perPage);
    }
}

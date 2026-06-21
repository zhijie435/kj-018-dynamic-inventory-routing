<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventorySourceStoreRequest;
use App\Http\Requests\InventorySourceUpdateRequest;
use App\Http\Traits\ApiResponse;
use App\Models\InventorySource;
use App\Repositories\InventorySourceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventorySourceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventorySourceRepository $inventorySourceRepository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', InventorySource::class);

        $inventorySources = $this->inventorySourceRepository->search($request, ['channels']);

        return $this->successResponse($inventorySources);
    }

    public function store(InventorySourceStoreRequest $request): JsonResponse
    {
        $inventorySource = $this->inventorySourceRepository->create($request->validated());

        return $this->createdResponse($inventorySource, 'Inventory source created successfully.');
    }

    public function show(InventorySource $inventorySource): JsonResponse
    {
        Gate::authorize('view', $inventorySource);

        $inventorySource->load('channels');

        return $this->successResponse($inventorySource);
    }

    public function update(InventorySourceUpdateRequest $request, InventorySource $inventorySource): JsonResponse
    {
        $this->inventorySourceRepository->update($inventorySource, $request->validated());

        return $this->successResponse($inventorySource, 'Inventory source updated successfully.');
    }

    public function destroy(InventorySource $inventorySource): JsonResponse
    {
        Gate::authorize('delete', $inventorySource);

        $this->inventorySourceRepository->delete($inventorySource);

        return $this->deletedResponse('Inventory source deleted successfully.');
    }

    public function channels(InventorySource $inventorySource): JsonResponse
    {
        Gate::authorize('viewChannels', $inventorySource);

        return $this->successResponse($inventorySource->channels);
    }
}

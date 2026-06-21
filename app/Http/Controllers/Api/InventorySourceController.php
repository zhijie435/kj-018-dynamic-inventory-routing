<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventorySourceStoreRequest;
use App\Http\Requests\InventorySourceUpdateRequest;
use App\Models\InventorySource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventorySourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $inventorySources = InventorySource::with('channels')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->when($request->input('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->orderBy('priority', 'desc')
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $inventorySources,
        ]);
    }

    public function store(InventorySourceStoreRequest $request): JsonResponse
    {
        $inventorySource = InventorySource::create($request->validated());

        return response()->json([
            'message' => 'Inventory source created successfully.',
            'data' => $inventorySource,
        ], 201);
    }

    public function show(InventorySource $inventorySource): JsonResponse
    {
        $inventorySource->load('channels');

        return response()->json([
            'data' => $inventorySource,
        ]);
    }

    public function update(InventorySourceUpdateRequest $request, InventorySource $inventorySource): JsonResponse
    {
        $inventorySource->update($request->validated());

        return response()->json([
            'message' => 'Inventory source updated successfully.',
            'data' => $inventorySource,
        ]);
    }

    public function destroy(InventorySource $inventorySource): JsonResponse
    {
        $inventorySource->delete();

        return response()->json([
            'message' => 'Inventory source deleted successfully.',
        ]);
    }

    public function channels(InventorySource $inventorySource): JsonResponse
    {
        return response()->json([
            'data' => $inventorySource->channels,
        ]);
    }
}

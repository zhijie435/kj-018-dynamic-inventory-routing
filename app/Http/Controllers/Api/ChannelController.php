<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChannelStoreRequest;
use App\Http\Requests\ChannelUpdateRequest;
use App\Models\Channel;
use App\Services\InventoryRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $channels = Channel::with('inventorySources')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->when($request->input('region'), function ($query, $region) {
                $query->where('region', $region);
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $channels,
        ]);
    }

    public function store(ChannelStoreRequest $request): JsonResponse
    {
        $channel = Channel::create($request->validated());

        if ($request->filled('inventory_source_ids')) {
            $channel->syncInventorySources($request->input('inventory_source_ids'));
        }

        $channel->load('inventorySources');

        return response()->json([
            'message' => 'Channel created successfully.',
            'data' => $channel,
        ], 201);
    }

    public function show(Channel $channel): JsonResponse
    {
        $channel->load('inventorySources');

        return response()->json([
            'data' => $channel,
        ]);
    }

    public function update(ChannelUpdateRequest $request, Channel $channel): JsonResponse
    {
        $channel->update($request->validated());

        if ($request->has('inventory_source_ids')) {
            $channel->syncInventorySources($request->input('inventory_source_ids'));
        }

        $channel->load('inventorySources');

        return response()->json([
            'message' => 'Channel updated successfully.',
            'data' => $channel,
        ]);
    }

    public function destroy(Channel $channel): JsonResponse
    {
        $channel->delete();

        return response()->json([
            'message' => 'Channel deleted successfully.',
        ]);
    }

    public function inventorySources(Channel $channel): JsonResponse
    {
        return response()->json([
            'data' => $channel->inventorySources,
        ]);
    }

    public function syncInventorySources(Request $request, Channel $channel): JsonResponse
    {
        $validated = $request->validate([
            'inventory_source_ids' => ['required', 'array'],
            'inventory_source_ids.*.id' => ['required', 'exists:inventory_sources,id'],
            'inventory_source_ids.*.is_primary' => ['nullable', 'boolean'],
            'inventory_source_ids.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $channel->syncInventorySources($validated['inventory_source_ids']);

        $channel->load('inventorySources');

        return response()->json([
            'message' => 'Inventory sources synced successfully.',
            'data' => $channel->inventorySources,
        ]);
    }

    public function routingOrder(Channel $channel, InventoryRoutingService $routingService): JsonResponse
    {
        return response()->json([
            'data' => $routingService->getRoutingOrder($channel),
        ]);
    }

    public function primarySource(Channel $channel, InventoryRoutingService $routingService): JsonResponse
    {
        $primary = $routingService->getPrimarySource($channel);

        return response()->json([
            'data' => $primary,
        ]);
    }

    public function routeSource(Request $request, Channel $channel, InventoryRoutingService $routingService): JsonResponse
    {
        $validated = $request->validate([
            'preferred_source_id' => ['nullable', 'integer', 'exists:inventory_sources,id'],
            'country' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'min_priority' => ['nullable', 'numeric', 'min:0'],
        ]);

        $source = $routingService->getRoutedSource($channel, $validated);

        return response()->json([
            'data' => $source,
        ]);
    }

    public function canRoute(Request $request, Channel $channel, InventoryRoutingService $routingService): JsonResponse
    {
        $validated = $request->validate([
            'inventory_source_id' => ['required', 'integer', 'exists:inventory_sources,id'],
        ]);

        $canRoute = $routingService->canRouteToSource($channel, $validated['inventory_source_id']);

        return response()->json([
            'data' => [
                'can_route' => $canRoute,
            ],
        ]);
    }
}

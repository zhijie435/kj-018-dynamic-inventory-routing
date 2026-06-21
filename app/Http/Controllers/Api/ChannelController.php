<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChannelStoreRequest;
use App\Http\Requests\ChannelUpdateRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Channel;
use App\Repositories\ChannelRepository;
use App\Services\InventoryRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChannelController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ChannelRepository $channelRepository,
        protected InventoryRoutingService $routingService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Channel::class);

        $channels = $this->channelRepository->search($request, ['inventorySources']);

        foreach ($channels as $channel) {
            $channel->removeInactiveInventorySources();
        }

        $channels->load('inventorySources');

        return $this->successResponse($channels);
    }

    public function store(ChannelStoreRequest $request): JsonResponse
    {
        $channel = $this->channelRepository->create($request->validated());

        if ($request->filled('inventory_source_ids')) {
            $channel->syncInventorySources($request->input('inventory_source_ids'));
        }

        $channel->load('inventorySources');

        return $this->createdResponse($channel, 'Channel created successfully.');
    }

    public function show(Channel $channel): JsonResponse
    {
        Gate::authorize('view', $channel);

        $channel->removeInactiveInventorySources();
        $channel->load('inventorySources');

        return $this->successResponse($channel);
    }

    public function update(ChannelUpdateRequest $request, Channel $channel): JsonResponse
    {
        $this->channelRepository->update($channel, $request->validated());

        if ($request->has('inventory_source_ids')) {
            $channel->syncInventorySources($request->input('inventory_source_ids'));
        }

        $channel->load('inventorySources');

        return $this->successResponse($channel, 'Channel updated successfully.');
    }

    public function destroy(Channel $channel): JsonResponse
    {
        Gate::authorize('delete', $channel);

        $this->channelRepository->delete($channel);

        return $this->deletedResponse('Channel deleted successfully.');
    }

    public function inventorySources(Channel $channel): JsonResponse
    {
        Gate::authorize('view', $channel);

        $channel->removeInactiveInventorySources();
        $channel->load('inventorySources');

        return $this->successResponse($channel->inventorySources);
    }

    public function syncInventorySources(Request $request, Channel $channel): JsonResponse
    {
        Gate::authorize('syncInventorySources', $channel);

        $validated = $request->validate([
            'inventory_source_ids' => ['required', 'array'],
            'inventory_source_ids.*.id' => [
                'required',
                Rule::exists('inventory_sources', 'id')->where('is_active', true),
            ],
            'inventory_source_ids.*.is_primary' => ['nullable', 'boolean'],
            'inventory_source_ids.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $channel->syncInventorySources($validated['inventory_source_ids']);

        $channel->load('inventorySources');

        return $this->successResponse($channel->inventorySources, 'Inventory sources synced successfully.');
    }

    public function routingOrder(Channel $channel): JsonResponse
    {
        Gate::authorize('viewRouting', $channel);

        return $this->successResponse($this->routingService->getRoutingOrder($channel));
    }

    public function primarySource(Channel $channel): JsonResponse
    {
        Gate::authorize('viewRouting', $channel);

        $primary = $this->routingService->getPrimarySource($channel);

        return $this->successResponse($primary);
    }

    public function routeSource(Request $request, Channel $channel): JsonResponse
    {
        Gate::authorize('routeSource', $channel);

        $validated = $request->validate([
            'preferred_source_id' => ['nullable', 'integer', 'exists:inventory_sources,id'],
            'country' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'min_priority' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, $validated);

        return response()->json([
            'data' => $result['source'],
            'meta' => [
                'route_type' => $result['route_type'],
                'is_moq_direct' => $result['is_moq_direct'],
                'fallback_to_cn' => $result['fallback_to_cn'],
            ] + array_intersect_key($result, array_flip(['requested_country', 'matched_country', 'preferred_source_id', 'min_priority'])),
        ]);
    }

    public function canRoute(Request $request, Channel $channel): JsonResponse
    {
        Gate::authorize('routeSource', $channel);

        $validated = $request->validate([
            'inventory_source_id' => ['required', 'integer', 'exists:inventory_sources,id'],
        ]);

        $canRoute = $this->routingService->canRouteToSource($channel, $validated['inventory_source_id']);

        return $this->successResponse(['can_route' => $canRoute]);
    }
}

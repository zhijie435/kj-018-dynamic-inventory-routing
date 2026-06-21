<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChannelStoreRequest;
use App\Http\Requests\ChannelUpdateRequest;
use App\Models\Channel;
use App\Models\InventorySource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function index(Request $request): Response
    {
        $channels = Channel::with('inventorySources')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->when($request->input('region'), function ($query, $region) {
                $query->where('region', $region);
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Channels/Index', [
            'channels' => $channels,
            'filters' => $request->only(['search', 'region']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Channels/Create', [
            'inventorySources' => InventorySource::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'country', 'city']),
        ]);
    }

    public function store(ChannelStoreRequest $request): RedirectResponse
    {
        $channel = Channel::create($request->validated());

        if ($request->filled('inventory_source_ids')) {
            $channel->syncInventorySources($request->input('inventory_source_ids'));
        }

        return redirect()->route('channels.index')
            ->with('success', 'Channel created successfully.');
    }

    public function show(Channel $channel): Response
    {
        $channel->removeInactiveInventorySources();
        $channel->load('inventorySources');

        return Inertia::render('Channels/Show', [
            'channel' => $channel,
        ]);
    }

    public function edit(Channel $channel): Response
    {
        $channel->removeInactiveInventorySources();
        $channel->load('inventorySources');

        $activeInventorySources = InventorySource::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'country', 'city']);

        $activeIds = $activeInventorySources->pluck('id')->toArray();

        $boundInventorySourceIds = $channel->inventorySources
            ->filter(function ($source) use ($activeIds) {
                return in_array($source->id, $activeIds, true);
            })
            ->map(function ($source) {
                return [
                    'id' => $source->id,
                    'is_primary' => (bool) $source->pivot->is_primary,
                    'sort_order' => $source->pivot->sort_order,
                ];
            })
            ->values()
            ->toArray();

        return Inertia::render('Channels/Edit', [
            'channel' => $channel,
            'inventorySources' => $activeInventorySources,
            'boundInventorySourceIds' => $boundInventorySourceIds,
        ]);
    }

    public function update(ChannelUpdateRequest $request, Channel $channel): RedirectResponse
    {
        $channel->update($request->validated());

        $channel->syncInventorySources($request->input('inventory_source_ids', []));

        return redirect()->route('channels.index')
            ->with('success', 'Channel updated successfully.');
    }

    public function destroy(Channel $channel): RedirectResponse
    {
        $channel->delete();

        return redirect()->route('channels.index')
            ->with('success', 'Channel deleted successfully.');
    }
}

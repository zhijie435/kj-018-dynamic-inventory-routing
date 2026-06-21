<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventorySourceStoreRequest;
use App\Http\Requests\InventorySourceUpdateRequest;
use App\Models\InventorySource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventorySourceController extends Controller
{
    public function index(Request $request): Response
    {
        $inventorySources = InventorySource::with('channels')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->when($request->input('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('InventorySources/Index', [
            'inventorySources' => $inventorySources,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('InventorySources/Create');
    }

    public function store(InventorySourceStoreRequest $request): RedirectResponse
    {
        InventorySource::create($request->validated());

        return redirect()->route('inventory-sources.index')
            ->with('success', 'Inventory source created successfully.');
    }

    public function show(InventorySource $inventorySource): Response
    {
        $inventorySource->load('channels');

        return Inertia::render('InventorySources/Show', [
            'inventorySource' => $inventorySource,
        ]);
    }

    public function edit(InventorySource $inventorySource): Response
    {
        return Inertia::render('InventorySources/Edit', [
            'inventorySource' => $inventorySource,
        ]);
    }

    public function update(InventorySourceUpdateRequest $request, InventorySource $inventorySource): RedirectResponse
    {
        $inventorySource->update($request->validated());

        return redirect()->route('inventory-sources.index')
            ->with('success', 'Inventory source updated successfully.');
    }

    public function destroy(InventorySource $inventorySource): RedirectResponse
    {
        $inventorySource->delete();

        return redirect()->route('inventory-sources.index')
            ->with('success', 'Inventory source deleted successfully.');
    }
}

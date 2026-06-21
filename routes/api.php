<?php

use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\InventorySourceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('channels', ChannelController::class);
    Route::get('/channels/{channel}/inventory-sources', [ChannelController::class, 'inventorySources']);
    Route::post('/channels/{channel}/inventory-sources/sync', [ChannelController::class, 'syncInventorySources']);
    Route::get('/channels/{channel}/routing-order', [ChannelController::class, 'routingOrder']);
    Route::get('/channels/{channel}/primary-source', [ChannelController::class, 'primarySource']);
    Route::post('/channels/{channel}/route-source', [ChannelController::class, 'routeSource']);
    Route::post('/channels/{channel}/can-route', [ChannelController::class, 'canRoute']);

    Route::apiResource('inventory-sources', InventorySourceController::class);
    Route::get('/inventory-sources/{inventorySource}/channels', [InventorySourceController::class, 'channels']);
});

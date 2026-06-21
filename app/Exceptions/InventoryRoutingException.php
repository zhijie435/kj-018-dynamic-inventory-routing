<?php

namespace App\Exceptions;

class InventoryRoutingException extends BaseException
{
    public static function noAvailableSources(int $channelId): self
    {
        return new self(
            'No available inventory sources for this channel.',
            'NO_AVAILABLE_SOURCES',
            422,
            ['channel_id' => $channelId]
        );
    }

    public static function cannotRouteToSource(int $channelId, int $inventorySourceId): self
    {
        return new self(
            'Cannot route to the specified inventory source.',
            'CANNOT_ROUTE_TO_SOURCE',
            422,
            ['channel_id' => $channelId, 'inventory_source_id' => $inventorySourceId]
        );
    }

    public static function invalidPrimarySourceConfiguration(int $channelId): self
    {
        return new self(
            'Invalid primary source configuration.',
            'INVALID_PRIMARY_SOURCE',
            422,
            ['channel_id' => $channelId]
        );
    }
}

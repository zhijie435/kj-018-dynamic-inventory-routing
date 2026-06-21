<?php

namespace App\Exceptions;

class StateTransitionException extends BaseException
{
    public static function invalidTransition(string $fromState, string $toState, string $entity = 'Resource'): self
    {
        return new self(
            "Invalid state transition from '{$fromState}' to '{$toState}'.",
            'INVALID_STATE_TRANSITION',
            422,
            ['entity' => $entity, 'from_state' => $fromState, 'to_state' => $toState]
        );
    }

    public static function inactiveSourceBinding(int $inventorySourceId): self
    {
        return new self(
            'Cannot bind an inactive inventory source to a channel.',
            'INACTIVE_SOURCE_BINDING',
            422,
            ['inventory_source_id' => $inventorySourceId]
        );
    }

    public static function duplicatePrimarySource(int $channelId): self
    {
        return new self(
            'A channel cannot have more than one primary inventory source.',
            'DUPLICATE_PRIMARY_SOURCE',
            422,
            ['channel_id' => $channelId]
        );
    }
}

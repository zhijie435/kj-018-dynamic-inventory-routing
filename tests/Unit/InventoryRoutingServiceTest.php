<?php

namespace Tests\Unit;

use App\Exceptions\InventoryRoutingException;
use App\Models\Channel;
use App\Models\InventorySource;
use App\Repositories\ChannelRepository;
use App\Services\InventoryRoutingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryRoutingService $routingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routingService = new InventoryRoutingService();
    }

    public function test_get_available_sources_returns_only_active_sources(): void
    {
        $channel = Channel::factory()->create();
        $active1 = InventorySource::factory()->active()->create();
        $active2 = InventorySource::factory()->active()->create();
        $inactive = InventorySource::factory()->inactive()->create();

        $channel->syncInventorySources([$active1->id, $inactive->id, $active2->id]);

        $available = $this->routingService->getAvailableSources($channel);

        $this->assertCount(2, $available);
        $this->assertTrue($available->contains($active1));
        $this->assertTrue($available->contains($active2));
        $this->assertFalse($available->contains($inactive));
    }

    public function test_get_available_sources_returns_empty_collection_when_no_sources(): void
    {
        $channel = Channel::factory()->create();

        $available = $this->routingService->getAvailableSources($channel);

        $this->assertTrue($available->isEmpty());
    }

    public function test_get_primary_source_returns_primary_source(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id],
            ['id' => $source2->id, 'is_primary' => true],
        ]);

        $primary = $this->routingService->getPrimarySource($channel);

        $this->assertEquals($source2->id, $primary->id);
    }

    public function test_get_primary_source_returns_null_when_no_sources(): void
    {
        $channel = Channel::factory()->create();

        $this->assertNull($this->routingService->getPrimarySource($channel));
    }

    public function test_get_routed_source_returns_null_when_no_sources(): void
    {
        $channel = Channel::factory()->create();

        $this->assertNull($this->routingService->getRoutedSource($channel));
    }

    public function test_get_routed_source_with_meta_returns_none_meta_when_no_sources(): void
    {
        $channel = Channel::factory()->create();

        $result = $this->routingService->getRoutedSourceWithMeta($channel);

        $this->assertNull($result['source']);
        $this->assertEquals('none', $result['route_type']);
        $this->assertFalse($result['is_moq_direct']);
        $this->assertFalse($result['fallback_to_cn']);
    }

    public function test_routing_strategy_country_match(): void
    {
        $channel = Channel::factory()->create();
        $usSource = InventorySource::factory()->active()->create(['country' => 'US']);
        $brSource = InventorySource::factory()->active()->create(['country' => 'BR']);

        $channel->syncInventorySources([
            ['id' => $usSource->id, 'is_primary' => true],
            ['id' => $brSource->id],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, ['country' => 'BR']);

        $this->assertEquals($brSource->id, $result['source']->id);
        $this->assertEquals('country_match', $result['route_type']);
        $this->assertFalse($result['is_moq_direct']);
        $this->assertFalse($result['fallback_to_cn']);
        $this->assertEquals('BR', $result['matched_country']);
    }

    public function test_routing_strategy_country_no_match_falls_back_to_cn_moq(): void
    {
        $channel = Channel::factory()->create();
        $usSource = InventorySource::factory()->active()->create(['country' => 'US']);
        $cnSource = InventorySource::factory()->active()->create(['country' => 'CN']);

        $channel->syncInventorySources([
            ['id' => $usSource->id, 'is_primary' => true],
            ['id' => $cnSource->id],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, ['country' => 'JP']);

        $this->assertEquals($cnSource->id, $result['source']->id);
        $this->assertEquals('cn_moq_fallback', $result['route_type']);
        $this->assertTrue($result['is_moq_direct']);
        $this->assertTrue($result['fallback_to_cn']);
        $this->assertEquals('JP', $result['requested_country']);
        $this->assertEquals('CN', $result['matched_country']);
    }

    public function test_routing_country_option_null_skips_country_strategy(): void
    {
        $channel = Channel::factory()->create();
        $primarySource = InventorySource::factory()->active()->create(['country' => 'US']);
        $brSource = InventorySource::factory()->active()->create(['country' => 'BR']);

        $channel->syncInventorySources([
            ['id' => $primarySource->id, 'is_primary' => true],
            ['id' => $brSource->id],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, ['country' => null]);

        $this->assertEquals($primarySource->id, $result['source']->id);
        $this->assertEquals('primary', $result['route_type']);
    }

    public function test_routing_strategy_preferred_source(): void
    {
        $channel = Channel::factory()->create();
        $primarySource = InventorySource::factory()->active()->create();
        $preferredSource = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $primarySource->id, 'is_primary' => true],
            ['id' => $preferredSource->id],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, [
            'preferred_source_id' => $preferredSource->id,
        ]);

        $this->assertEquals($preferredSource->id, $result['source']->id);
        $this->assertEquals('preferred_source', $result['route_type']);
        $this->assertEquals($preferredSource->id, $result['preferred_source_id']);
    }

    public function test_routing_preferred_source_not_found_skips_to_next_strategy(): void
    {
        $channel = Channel::factory()->create();
        $primarySource = InventorySource::factory()->active()->create();
        $otherSource = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $primarySource->id, 'is_primary' => true],
            ['id' => $otherSource->id],
        ]);

        $nonExistentId = 99999;
        $result = $this->routingService->getRoutedSourceWithMeta($channel, [
            'preferred_source_id' => $nonExistentId,
        ]);

        $this->assertEquals($primarySource->id, $result['source']->id);
        $this->assertEquals('primary', $result['route_type']);
    }

    public function test_routing_preferred_source_null_skips_strategy(): void
    {
        $channel = Channel::factory()->create();
        $primarySource = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([['id' => $primarySource->id, 'is_primary' => true]]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, [
            'preferred_source_id' => null,
        ]);

        $this->assertEquals('primary', $result['route_type']);
    }

    public function test_routing_strategy_priority_match(): void
    {
        $channel = Channel::factory()->create();
        $lowPriority = InventorySource::factory()->active()->create(['priority' => 3.00]);
        $highPriority = InventorySource::factory()->active()->create(['priority' => 8.00]);

        $channel->syncInventorySources([
            ['id' => $lowPriority->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $highPriority->id, 'sort_order' => 1],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, [
            'min_priority' => 5,
        ]);

        $this->assertEquals($highPriority->id, $result['source']->id);
        $this->assertEquals('priority_match', $result['route_type']);
        $this->assertEquals(5, $result['min_priority']);
    }

    public function test_routing_priority_zero_still_triggers_strategy(): void
    {
        $channel = Channel::factory()->create();
        $zeroPriority = InventorySource::factory()->active()->create(['priority' => 0.00]);
        $highPriority = InventorySource::factory()->active()->create(['priority' => 8.00]);

        $channel->syncInventorySources([
            ['id' => $zeroPriority->id, 'is_primary' => true],
            ['id' => $highPriority->id],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, [
            'min_priority' => 0,
        ]);

        $this->assertEquals('priority_match', $result['route_type']);
    }

    public function test_routing_priority_not_met_skips_to_primary(): void
    {
        $channel = Channel::factory()->create();
        $primarySource = InventorySource::factory()->active()->create(['priority' => 2.00]);
        $otherSource = InventorySource::factory()->active()->create(['priority' => 3.00]);

        $channel->syncInventorySources([
            ['id' => $primarySource->id, 'is_primary' => true],
            ['id' => $otherSource->id],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, [
            'min_priority' => 10,
        ]);

        $this->assertEquals($primarySource->id, $result['source']->id);
        $this->assertEquals('primary', $result['route_type']);
    }

    public function test_routing_priority_null_skips_strategy(): void
    {
        $channel = Channel::factory()->create();
        $primarySource = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([['id' => $primarySource->id, 'is_primary' => true]]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel, [
            'min_priority' => null,
        ]);

        $this->assertEquals('primary', $result['route_type']);
    }

    public function test_routing_strategy_primary_source(): void
    {
        $channel = Channel::factory()->create();
        $primarySource = InventorySource::factory()->active()->create();
        $otherSource = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $primarySource->id, 'is_primary' => true],
            ['id' => $otherSource->id],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel);

        $this->assertEquals($primarySource->id, $result['source']->id);
        $this->assertEquals('primary', $result['route_type']);
        $this->assertFalse($result['is_moq_direct']);
        $this->assertFalse($result['fallback_to_cn']);
    }

    public function test_routing_no_primary_falls_back_to_first(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $source1->id, 'sort_order' => 0, 'is_primary' => false],
            ['channel_id' => $channel->id, 'inventory_source_id' => $source2->id, 'sort_order' => 1, 'is_primary' => false],
        ]);

        $result = $this->routingService->getRoutedSourceWithMeta($channel);

        $this->assertNotNull($result['source']);
        $this->assertEquals('first_available', $result['route_type']);
    }

    public function test_get_source_with_fallback_returns_requested_when_available(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true],
            ['id' => $source2->id],
        ]);

        $result = $this->routingService->getSourceWithFallback($channel, $source2->id);

        $this->assertEquals($source2->id, $result->id);
    }

    public function test_get_source_with_fallback_returns_primary_when_requested_unavailable(): void
    {
        $channel = Channel::factory()->create();
        $primary = InventorySource::factory()->active()->create();
        $other = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $primary->id, 'is_primary' => true],
            ['id' => $other->id],
        ]);

        $unboundId = 99999;
        $result = $this->routingService->getSourceWithFallback($channel, $unboundId);

        $this->assertEquals($primary->id, $result->id);
    }

    public function test_get_source_with_fallback_returns_first_when_no_primary_and_requested_unavailable(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $source1->id, 'sort_order' => 0, 'is_primary' => false],
            ['channel_id' => $channel->id, 'inventory_source_id' => $source2->id, 'sort_order' => 1, 'is_primary' => false],
        ]);

        $result = $this->routingService->getSourceWithFallback($channel, 99999);

        $this->assertNotNull($result);
        $this->assertContains($result->id, [$source1->id, $source2->id]);
    }

    public function test_get_source_with_fallback_returns_null_when_no_sources(): void
    {
        $channel = Channel::factory()->create();

        $result = $this->routingService->getSourceWithFallback($channel, 99999);

        $this->assertNull($result);
    }

    public function test_can_route_to_source_returns_true_for_bound_source(): void
    {
        $channel = Channel::factory()->create();
        $source = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([$source->id]);

        $this->assertTrue($this->routingService->canRouteToSource($channel, $source->id));
    }

    public function test_can_route_to_source_returns_false_for_unbound_source(): void
    {
        $channel = Channel::factory()->create();
        $boundSource = InventorySource::factory()->active()->create();
        $unboundSource = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([$boundSource->id]);

        $this->assertFalse($this->routingService->canRouteToSource($channel, $unboundSource->id));
    }

    public function test_can_route_to_source_returns_false_for_inactive_bound_source(): void
    {
        $channel = Channel::factory()->create();
        $inactiveSource = InventorySource::factory()->inactive()->create();

        $channel->syncInventorySources([$inactiveSource->id]);

        $this->assertFalse($this->routingService->canRouteToSource($channel, $inactiveSource->id));
    }

    public function test_assert_can_route_to_source_passes_when_bound(): void
    {
        $channel = Channel::factory()->create();
        $source = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([$source->id]);

        $this->expectNotToPerformAssertions();
        $this->routingService->assertCanRouteToSource($channel, $source->id);
    }

    public function test_assert_can_route_to_source_throws_exception_when_not_bound(): void
    {
        $channel = Channel::factory()->create();
        $unboundId = 99999;

        $this->expectException(InventoryRoutingException::class);
        $this->expectExceptionMessage('Cannot route to the specified inventory source.');

        $this->routingService->assertCanRouteToSource($channel, $unboundId);
    }

    public function test_assert_can_route_exception_has_correct_context(): void
    {
        $channel = Channel::factory()->create();

        try {
            $this->routingService->assertCanRouteToSource($channel, 123);
            $this->fail('Expected exception was not thrown.');
        } catch (InventoryRoutingException $e) {
            $this->assertEquals(422, $e->getHttpStatusCode());
            $this->assertEquals('CANNOT_ROUTE_TO_SOURCE', $e->getErrorCode());
            $this->assertEquals($channel->id, $e->getContext()['channel_id']);
            $this->assertEquals(123, $e->getContext()['inventory_source_id']);
        }
    }

    public function test_get_routing_order_returns_correct_order_and_fields(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create(['country' => 'US', 'city' => 'New York', 'priority' => 10.00]);
        $source2 = InventorySource::factory()->active()->create(['country' => 'BR', 'city' => 'Sao Paulo', 'priority' => 8.00]);

        $channel->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $source2->id, 'sort_order' => 1],
        ]);

        $order = $this->routingService->getRoutingOrder($channel);

        $this->assertCount(2, $order);

        $this->assertEquals($source1->id, $order[0]['id']);
        $this->assertEquals($source1->code, $order[0]['code']);
        $this->assertEquals($source1->name, $order[0]['name']);
        $this->assertTrue($order[0]['is_primary']);
        $this->assertEquals(0, $order[0]['sort_order']);
        $this->assertEquals('US', $order[0]['country']);
        $this->assertEquals('New York', $order[0]['city']);
        $this->assertEquals(10.00, $order[0]['priority']);

        $this->assertEquals($source2->id, $order[1]['id']);
        $this->assertFalse($order[1]['is_primary']);
        $this->assertEquals(1, $order[1]['sort_order']);
    }

    public function test_get_routing_order_empty_when_no_sources(): void
    {
        $channel = Channel::factory()->create();

        $order = $this->routingService->getRoutingOrder($channel);

        $this->assertEmpty($order);
    }

    public function test_strategy_priority_order_country_before_preferred_before_priority_before_primary(): void
    {
        $channel = Channel::factory()->create();
        $usSource = InventorySource::factory()->active()->create(['country' => 'US', 'priority' => 2.00]);
        $brSource = InventorySource::factory()->active()->create(['country' => 'BR', 'priority' => 5.00]);
        $jpSource = InventorySource::factory()->active()->create(['country' => 'JP', 'priority' => 9.00]);
        $primarySource = InventorySource::factory()->active()->create(['country' => 'CN', 'priority' => 1.00]);

        $channel->syncInventorySources([
            ['id' => $primarySource->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $usSource->id, 'sort_order' => 1],
            ['id' => $brSource->id, 'sort_order' => 2],
            ['id' => $jpSource->id, 'sort_order' => 3],
        ]);

        $countryResult = $this->routingService->getRoutedSourceWithMeta($channel, [
            'country' => 'BR',
            'preferred_source_id' => $jpSource->id,
            'min_priority' => 8,
        ]);
        $this->assertEquals('country_match', $countryResult['route_type']);
        $this->assertEquals($brSource->id, $countryResult['source']->id);

        $preferredResult = $this->routingService->getRoutedSourceWithMeta($channel, [
            'preferred_source_id' => $jpSource->id,
            'min_priority' => 8,
        ]);
        $this->assertEquals('preferred_source', $preferredResult['route_type']);
        $this->assertEquals($jpSource->id, $preferredResult['source']->id);

        $priorityResult = $this->routingService->getRoutedSourceWithMeta($channel, [
            'min_priority' => 8,
        ]);
        $this->assertEquals('priority_match', $priorityResult['route_type']);
        $this->assertEquals($jpSource->id, $priorityResult['source']->id);
    }

    public function test_constructor_accepts_custom_channel_repository(): void
    {
        $channel = Channel::factory()->create();
        $mockRepo = \Mockery::mock(ChannelRepository::class);
        $mockRepo->shouldReceive('getActiveInventorySources')
            ->once()
            ->andReturn(new Collection());

        $service = new InventoryRoutingService($mockRepo);
        $result = $service->getAvailableSources($channel);

        $this->assertTrue($result->isEmpty());
    }
}

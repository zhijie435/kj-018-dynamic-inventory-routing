<?php

namespace Tests\Unit;

use App\Exceptions\BaseException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\InventoryRoutingException;
use App\Exceptions\NotFoundException;
use App\Exceptions\StateTransitionException;
use App\Models\Channel;
use App\Models\InventorySource;
use App\Models\User;
use App\Policies\ChannelPolicy;
use App\Policies\InventorySourcePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExceptionAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    // ========== InventoryRoutingException Tests ==========

    public function test_no_available_sources_exception(): void
    {
        $exception = InventoryRoutingException::noAvailableSources(123);

        $this->assertEquals('No available inventory sources for this channel.', $exception->getMessage());
        $this->assertEquals('NO_AVAILABLE_SOURCES', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals(['channel_id' => 123], $exception->getContext());
    }

    public function test_cannot_route_to_source_exception(): void
    {
        $exception = InventoryRoutingException::cannotRouteToSource(10, 20);

        $this->assertEquals('Cannot route to the specified inventory source.', $exception->getMessage());
        $this->assertEquals('CANNOT_ROUTE_TO_SOURCE', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals(['channel_id' => 10, 'inventory_source_id' => 20], $exception->getContext());
    }

    public function test_invalid_primary_source_configuration_exception(): void
    {
        $exception = InventoryRoutingException::invalidPrimarySourceConfiguration(5);

        $this->assertEquals('Invalid primary source configuration.', $exception->getMessage());
        $this->assertEquals('INVALID_PRIMARY_SOURCE', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals(['channel_id' => 5], $exception->getContext());
    }

    // ========== StateTransitionException Tests ==========

    public function test_invalid_transition_exception(): void
    {
        $exception = StateTransitionException::invalidTransition('draft', 'published', 'Order');

        $this->assertEquals("Invalid state transition from 'draft' to 'published'.", $exception->getMessage());
        $this->assertEquals('INVALID_STATE_TRANSITION', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals([
            'entity' => 'Order',
            'from_state' => 'draft',
            'to_state' => 'published',
        ], $exception->getContext());
    }

    public function test_inactive_source_binding_exception(): void
    {
        $exception = StateTransitionException::inactiveSourceBinding(99);

        $this->assertEquals('Cannot bind an inactive inventory source to a channel.', $exception->getMessage());
        $this->assertEquals('INACTIVE_SOURCE_BINDING', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals(['inventory_source_id' => 99], $exception->getContext());
    }

    public function test_duplicate_primary_source_exception(): void
    {
        $exception = StateTransitionException::duplicatePrimarySource(7);

        $this->assertEquals('A channel cannot have more than one primary inventory source.', $exception->getMessage());
        $this->assertEquals('DUPLICATE_PRIMARY_SOURCE', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals(['channel_id' => 7], $exception->getContext());
    }

    public function test_state_transition_direct_constructor(): void
    {
        $exception = new StateTransitionException(
            'Custom message',
            'CUSTOM_CODE',
            400,
            ['key' => 'value']
        );

        $this->assertEquals('Custom message', $exception->getMessage());
        $this->assertEquals('CUSTOM_CODE', $exception->getErrorCode());
        $this->assertEquals(400, $exception->getHttpStatusCode());
        $this->assertEquals(['key' => 'value'], $exception->getContext());
    }

    // ========== NotFoundException Tests ==========

    public function test_not_found_exception_default(): void
    {
        $exception = new NotFoundException();

        $this->assertEquals('Resource not found.', $exception->getMessage());
        $this->assertEquals('NOT_FOUND', $exception->getErrorCode());
        $this->assertEquals(404, $exception->getHttpStatusCode());
        $this->assertEmpty($exception->getContext());
    }

    public function test_not_found_exception_with_entity(): void
    {
        $exception = new NotFoundException('Channel', ['id' => 123]);

        $this->assertEquals('Channel not found.', $exception->getMessage());
        $this->assertEquals('NOT_FOUND', $exception->getErrorCode());
        $this->assertEquals(404, $exception->getHttpStatusCode());
        $this->assertEquals(['id' => 123], $exception->getContext());
    }

    // ========== ForbiddenException Tests ==========

    public function test_forbidden_exception_default(): void
    {
        $exception = new ForbiddenException();

        $this->assertEquals('You do not have permission to perform this action.', $exception->getMessage());
        $this->assertEquals('FORBIDDEN', $exception->getErrorCode());
        $this->assertEquals(403, $exception->getHttpStatusCode());
        $this->assertEmpty($exception->getContext());
    }

    public function test_forbidden_exception_with_message_and_context(): void
    {
        $exception = new ForbiddenException(
            'Custom forbidden message',
            ['required_role' => 'admin']
        );

        $this->assertEquals('Custom forbidden message', $exception->getMessage());
        $this->assertEquals('FORBIDDEN', $exception->getErrorCode());
        $this->assertEquals(403, $exception->getHttpStatusCode());
        $this->assertEquals(['required_role' => 'admin'], $exception->getContext());
    }

    // ========== BaseException Render Tests ==========

    public function test_base_exception_render_returns_json(): void
    {
        $exception = new class('Test message', 'TEST_CODE', 418, ['foo' => 'bar']) extends BaseException {};

        $response = $exception->render();

        $this->assertEquals(418, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('TEST_CODE', $data['error']['code']);
        $this->assertEquals('Test message', $data['error']['message']);
        $this->assertEquals(['foo' => 'bar'], $data['error']['context']);
    }

    public function test_base_exception_render_without_context(): void
    {
        $exception = new class('Simple error', 'SIMPLE', 400) extends BaseException {};

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertArrayNotHasKey('context', $data['error']);
    }

    public function test_base_exception_render_with_default_values(): void
    {
        $exception = new class() extends BaseException {};

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('INTERNAL_ERROR', $data['error']['code']);
        $this->assertEquals('An error occurred.', $data['error']['message']);
    }

    // ========== ChannelPolicy Tests ==========

    public function test_channel_policy_view_any(): void
    {
        $policy = new ChannelPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->viewAny($analyst));
    }

    public function test_channel_policy_view(): void
    {
        $policy = new ChannelPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $channel = Channel::factory()->create();

        $this->assertTrue($policy->view($admin, $channel));
        $this->assertTrue($policy->view($manager, $channel));
        $this->assertTrue($policy->view($analyst, $channel));
    }

    public function test_channel_policy_create(): void
    {
        $policy = new ChannelPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->create($manager));
        $this->assertFalse($policy->create($analyst));
    }

    public function test_channel_policy_update(): void
    {
        $policy = new ChannelPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $channel = Channel::factory()->create();

        $this->assertTrue($policy->update($admin, $channel));
        $this->assertTrue($policy->update($manager, $channel));
        $this->assertFalse($policy->update($analyst, $channel));
    }

    public function test_channel_policy_delete(): void
    {
        $policy = new ChannelPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $channel = Channel::factory()->create();

        $this->assertTrue($policy->delete($admin, $channel));
        $this->assertFalse($policy->delete($manager, $channel));
        $this->assertFalse($policy->delete($analyst, $channel));
    }

    public function test_channel_policy_sync_inventory_sources(): void
    {
        $policy = new ChannelPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $channel = Channel::factory()->create();

        $this->assertTrue($policy->syncInventorySources($admin, $channel));
        $this->assertTrue($policy->syncInventorySources($manager, $channel));
        $this->assertFalse($policy->syncInventorySources($analyst, $channel));
    }

    public function test_channel_policy_view_routing(): void
    {
        $policy = new ChannelPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $channel = Channel::factory()->create();

        $this->assertTrue($policy->viewRouting($admin, $channel));
        $this->assertTrue($policy->viewRouting($manager, $channel));
        $this->assertTrue($policy->viewRouting($analyst, $channel));
    }

    public function test_channel_policy_route_source(): void
    {
        $policy = new ChannelPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $channel = Channel::factory()->create();

        $this->assertTrue($policy->routeSource($admin, $channel));
        $this->assertTrue($policy->routeSource($manager, $channel));
        $this->assertTrue($policy->routeSource($analyst, $channel));
    }

    // ========== InventorySourcePolicy Tests ==========

    public function test_inventory_source_policy_view_any(): void
    {
        $policy = new InventorySourcePolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->viewAny($analyst));
    }

    public function test_inventory_source_policy_view(): void
    {
        $policy = new InventorySourcePolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $source = InventorySource::factory()->create();

        $this->assertTrue($policy->view($admin, $source));
        $this->assertTrue($policy->view($manager, $source));
        $this->assertTrue($policy->view($analyst, $source));
    }

    public function test_inventory_source_policy_create(): void
    {
        $policy = new InventorySourcePolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->create($manager));
        $this->assertFalse($policy->create($analyst));
    }

    public function test_inventory_source_policy_update(): void
    {
        $policy = new InventorySourcePolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $source = InventorySource::factory()->create();

        $this->assertTrue($policy->update($admin, $source));
        $this->assertTrue($policy->update($manager, $source));
        $this->assertFalse($policy->update($analyst, $source));
    }

    public function test_inventory_source_policy_delete(): void
    {
        $policy = new InventorySourcePolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $source = InventorySource::factory()->create();

        $this->assertTrue($policy->delete($admin, $source));
        $this->assertFalse($policy->delete($manager, $source));
        $this->assertFalse($policy->delete($analyst, $source));
    }

    public function test_inventory_source_policy_view_channels(): void
    {
        $policy = new InventorySourcePolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $source = InventorySource::factory()->create();

        $this->assertTrue($policy->viewChannels($admin, $source));
        $this->assertTrue($policy->viewChannels($manager, $source));
        $this->assertTrue($policy->viewChannels($analyst, $source));
    }

    // ========== User Role Tests ==========

    public function test_user_role_checks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $analyst = User::factory()->create(['role' => 'analyst']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isManager());
        $this->assertFalse($admin->isAnalyst());

        $this->assertFalse($manager->isAdmin());
        $this->assertTrue($manager->isManager());
        $this->assertFalse($manager->isAnalyst());

        $this->assertFalse($analyst->isAdmin());
        $this->assertFalse($analyst->isManager());
        $this->assertTrue($analyst->isAnalyst());

        $this->assertTrue($admin->isRole('admin'));
        $this->assertTrue($manager->isRole('manager'));
        $this->assertTrue($analyst->isRole('analyst'));
        $this->assertFalse($admin->isRole('nonexistent'));
    }
}

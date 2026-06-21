<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\InventorySource;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $usWarehouse = InventorySource::create([
            'code' => 'US_WAREHOUSE',
            'name' => 'US Warehouse',
            'type' => 'warehouse',
            'country' => 'US',
            'city' => 'Los Angeles',
            'address' => '123 Logistics Ave, Los Angeles, CA 90001',
            'timezone' => 'America/Los_Angeles',
            'priority' => 10.00,
            'is_active' => true,
        ]);

        $usEastWarehouse = InventorySource::create([
            'code' => 'US_EAST_WH',
            'name' => 'US East Warehouse',
            'type' => 'warehouse',
            'country' => 'US',
            'city' => 'New York',
            'address' => '456 East St, New York, NY 10001',
            'timezone' => 'America/New_York',
            'priority' => 8.00,
            'is_active' => true,
        ]);

        $brWarehouse = InventorySource::create([
            'code' => 'BR_WAREHOUSE',
            'name' => 'Brazil Warehouse',
            'type' => 'warehouse',
            'country' => 'BR',
            'city' => 'Sao Paulo',
            'address' => '789 Paulista Ave, Sao Paulo, SP 01310',
            'timezone' => 'America/Sao_Paulo',
            'priority' => 9.00,
            'is_active' => true,
        ]);

        $euWarehouse = InventorySource::create([
            'code' => 'EU_WAREHOUSE',
            'name' => 'EU Warehouse',
            'type' => 'warehouse',
            'country' => 'DE',
            'city' => 'Berlin',
            'address' => '321 Berlin Strasse, Berlin 10115',
            'timezone' => 'Europe/Berlin',
            'priority' => 7.00,
            'is_active' => true,
        ]);

        $dropShipUs = InventorySource::create([
            'code' => 'DROPSHIP_US',
            'name' => 'US Dropshipping',
            'type' => 'dropship',
            'country' => 'US',
            'city' => 'Chicago',
            'address' => 'Dropship Center, Chicago, IL 60601',
            'timezone' => 'America/Chicago',
            'priority' => 5.00,
            'is_active' => true,
        ]);

        $usChannel = Channel::create([
            'code' => 'US_CHANNEL',
            'name' => 'US Channel',
            'region' => 'US',
            'currency' => 'USD',
            'locale' => 'en_US',
            'description' => 'United States sales channel',
            'is_active' => true,
        ]);

        $usChannel->syncInventorySources([
            ['id' => $usWarehouse->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $usEastWarehouse->id, 'is_primary' => false, 'sort_order' => 1],
            ['id' => $dropShipUs->id, 'is_primary' => false, 'sort_order' => 2],
        ]);

        $brChannel = Channel::create([
            'code' => 'BR_CHANNEL',
            'name' => 'BR Channel',
            'region' => 'BR',
            'currency' => 'BRL',
            'locale' => 'pt_BR',
            'description' => 'Brazil sales channel',
            'is_active' => true,
        ]);

        $brChannel->syncInventorySources([
            ['id' => $brWarehouse->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $usWarehouse->id, 'is_primary' => false, 'sort_order' => 1],
        ]);

        $euChannel = Channel::create([
            'code' => 'EU_CHANNEL',
            'name' => 'EU Channel',
            'region' => 'EU',
            'currency' => 'EUR',
            'locale' => 'de_DE',
            'description' => 'Europe sales channel',
            'is_active' => true,
        ]);

        $euChannel->syncInventorySources([
            ['id' => $euWarehouse->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $usWarehouse->id, 'is_primary' => false, 'sort_order' => 1],
        ]);
    }
}

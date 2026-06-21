<?php

namespace Database\Factories;

use App\Models\InventorySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventorySource>
 */
class InventorySourceFactory extends Factory
{
    protected $model = InventorySource::class;

    public function definition(): array
    {
        $types = ['warehouse', 'dropship', 'store', 'fulfillment'];
        $countries = ['US', 'BR', 'DE', 'FR', 'JP', 'CN'];
        $cities = [
            'US' => ['Los Angeles', 'New York', 'Chicago', 'Dallas'],
            'BR' => ['Sao Paulo', 'Rio de Janeiro', 'Brasilia'],
            'DE' => ['Berlin', 'Munich', 'Hamburg'],
            'FR' => ['Paris', 'Lyon', 'Marseille'],
            'JP' => ['Tokyo', 'Osaka', 'Kyoto'],
            'CN' => ['Shanghai', 'Shenzhen', 'Guangzhou'],
        ];

        $country = $this->faker->randomElement($countries);
        $city = $this->faker->randomElement($cities[$country]);

        return [
            'code' => strtoupper($this->faker->unique()->word()) . '_' . strtoupper($this->faker->word()),
            'name' => $city . ' ' . ucfirst($this->faker->randomElement($types)),
            'type' => $this->faker->randomElement($types),
            'country' => $country,
            'city' => $city,
            'address' => $this->faker->streetAddress(),
            'timezone' => $this->faker->timezone(),
            'priority' => $this->faker->randomFloat(2, 0, 10),
            'is_active' => $this->faker->boolean(85),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function warehouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'warehouse',
        ]);
    }

    public function dropship(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dropship',
        ]);
    }

    public function usWarehouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'US_WAREHOUSE',
            'name' => 'US Warehouse',
            'type' => 'warehouse',
            'country' => 'US',
            'city' => 'Los Angeles',
            'timezone' => 'America/Los_Angeles',
            'priority' => 10.00,
        ]);
    }

    public function brWarehouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'BR_WAREHOUSE',
            'name' => 'Brazil Warehouse',
            'type' => 'warehouse',
            'country' => 'BR',
            'city' => 'Sao Paulo',
            'timezone' => 'America/Sao_Paulo',
            'priority' => 9.00,
        ]);
    }
}

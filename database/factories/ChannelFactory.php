<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        $regions = ['US', 'BR', 'EU', 'UK', 'JP', 'APAC'];
        $currencies = ['USD', 'BRL', 'EUR', 'GBP', 'JPY'];
        $locales = ['en_US', 'pt_BR', 'es_ES', 'de_DE', 'fr_FR', 'ja_JP'];

        $region = $this->faker->randomElement($regions);

        return [
            'code' => strtoupper($this->faker->unique()->word()) . '_CHANNEL',
            'name' => $this->faker->company() . ' Channel',
            'region' => $region,
            'currency' => $this->faker->randomElement($currencies),
            'locale' => $this->faker->randomElement($locales),
            'description' => $this->faker->optional()->paragraph(),
            'is_active' => $this->faker->boolean(80),
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

    public function us(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'US_CHANNEL',
            'name' => 'US Channel',
            'region' => 'US',
            'currency' => 'USD',
            'locale' => 'en_US',
        ]);
    }

    public function br(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'BR_CHANNEL',
            'name' => 'BR Channel',
            'region' => 'BR',
            'currency' => 'BRL',
            'locale' => 'pt_BR',
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Backup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => $this->faker->uuid(),
            'filename' => $this->faker->unixTime().'_'.$this->faker->uuid().'.db',
            'size' => $this->faker->numberBetween(1_024, 10_240_000),
            'checksum' => $this->faker->optional()->sha256(),
        ];
    }
}

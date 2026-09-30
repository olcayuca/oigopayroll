<?php

namespace Database\Factories;

use App\Enums\FirmSource;
use App\Enums\FirmStatus;
use App\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Firm>
 */
class FirmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'status' => FirmStatus::Active,
            'source' => FirmSource::Hrd,
        ];
    }

    /**
     * Indicate that the firm is waiting for HRD approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FirmStatus::Pending,
            'source' => FirmSource::Client,
        ]);
    }
}

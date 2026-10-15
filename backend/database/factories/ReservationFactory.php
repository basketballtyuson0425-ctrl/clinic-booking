<?php

namespace Database\Factories;

use App\Enums\ReservationSource;
use App\Enums\VisitType;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * テスト用の架空の予約を作る（実在の人物の情報は使わない）
 *
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_name' => fake()->name(),
            'patient_phone' => fake()->numerify('090-####-####'),
            'patient_email' => fake()->optional()->safeEmail(),
            'visit_type' => fake()->randomElement(VisitType::cases()),
            'treatment_request' => fake()->optional()->randomElement(['腰痛', '肩こり', '膝の痛み']),
            // 明日の 10:00（営業時間内の枠）
            'start_at' => now()->addDay()->setTime(10, 0),
            'source' => ReservationSource::Web,
        ];
    }
}

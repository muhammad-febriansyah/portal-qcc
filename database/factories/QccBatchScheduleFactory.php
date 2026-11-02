<?php

namespace Database\Factories;

use App\Models\QccBatch;
use App\Models\QccBatchSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QccBatchSchedule>
 */
class QccBatchScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => QccBatch::factory(),
            'schedule_type' => 'BIWEEKLY',
            'sequence_no' => 1,
            'scheduled_on' => now()->addWeek()->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}

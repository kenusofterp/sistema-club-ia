<?php

namespace Tests\Feature;

use App\Livewire\Admin\Activities\Form as ActivityForm;
use App\Models\Activity;
use Database\Seeders\ClubBaseSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClubBaseSeeder::class);
    }

    public function test_activity_schedule_can_be_repeated_on_other_days(): void
    {
        $this->actingAs($this->staff());

        $component = Livewire::test(ActivityForm::class)
            ->set('name', 'Yoga')
            ->call('addSchedule')
            ->set('schedules.0.day_of_week', 1)
            ->set('schedules.0.start_time', '19:00')
            ->set('schedules.0.end_time', '20:00')
            ->call('repeatSchedule', 0, ['4', '2', '4'])
            ->call('repeatSchedule', 0, [2]);

        $schedules = $component->get('schedules');
        $this->assertSame([1, 2, 4], array_map(fn ($s) => (int) $s['day_of_week'], $schedules));
        $this->assertSame(['19:00'], array_values(array_unique(array_column($schedules, 'start_time'))));

        $component->call('save')->assertHasNoErrors();
        $this->assertSame(3, Activity::where('name', 'Yoga')->first()->schedules()->count());
    }
}

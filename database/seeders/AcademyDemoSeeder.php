<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\Facility;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\LevelLessonService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Demo "academia por niveles": coordinadora, dos profesores, tres niveles con clases todos los días
 * y alumnas con la cuota del mes pendiente. Para probar la app en el teléfono.
 * Uso: php artisan db:seed --class=AcademyDemoSeeder
 * Accesos (contraseña Demo1234): laura@demo.com (coordinación), diego@demo.com y sofia@demo.com (profesores),
 * alumna@demo.com (alumna de Juvenil A).
 */
class AcademyDemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo1234';

    public function run(): void
    {
        Notification::fake();

        $org = Organization::firstOrCreate(['slug' => 'academia'], [
            'name' => 'Academia de Tenis',
            'type' => 'club',
            'is_default' => Organization::where('is_default', true)->doesntExist(),
        ]);

        Organization::runFor($org, function () use ($org) {
            app(SettingsSeeder::class)->run();
            app(SiteContentSeeder::class)->run();
            Setting::set('site.name', $org->name);
            Setting::set('site.short_name', 'ACA');
            Setting::set('activities.label_singular', 'Nivel');
            Setting::set('activities.label_plural', 'Niveles');
            Setting::set('payments.transfer_info', "Alias: ACADEMIA.TENIS.DEMO\nCBU: 0000003100000000000001\nTitular: Academia de Tenis");
            Setting::set('payments.receipts_auto_approve', false);
            Setting::set('payments.cash_requires_settlement', true);

            $category = MemberCategory::firstOrCreate(['name' => 'Alumna/o'], ['monthly_fee' => 0, 'admission_fee' => 0, 'sort_order' => 0]);
            $court = Facility::firstOrCreate(['slug' => 'cancha-central'], ['name' => 'Cancha central', 'hourly_rate' => 0]);

            $laura = $this->staff('Laura Coordinadora', 'laura@demo.com', 'coordinacion');
            $diego = $this->staff('Diego Profe', 'diego@demo.com', 'profesor');
            $sofia = $this->staff('Sofía Profe', 'sofia@demo.com', 'profesor');
            $diego->facilities()->syncWithoutDetaching([$court->id]);
            $sofia->facilities()->syncWithoutDetaching([$court->id]);

            // Clases todos los días, para que siempre haya algo en "Clases de hoy".
            $everyDay = range(1, 7);
            $levels = [
                ['Juvenil A', 25000, $diego, [$sofia], '17:00', '18:30'],
                ['Juvenil B', 28000, $sofia, [], '18:30', '20:00'],
                ['Juvenil C', 30000, $diego, [], '20:00', '21:30'],
            ];

            $enrollments = app(EnrollmentService::class);
            foreach ($levels as [$name, $fee, $titular, $others, $start, $end]) {
                $level = Activity::firstOrCreate(['slug' => Str::slug($name)], [
                    'name' => $name,
                    'summary' => "Nivel {$name}",
                    'monthly_fee' => $fee,
                    'instructor_id' => $titular->id,
                    'is_public' => true,
                    'allows_enrollment' => true,
                    'is_active' => true,
                ]);
                $level->instructors()->sync([$titular->id, ...collect($others)->pluck('id')]);
                if ($level->schedules()->doesntExist()) {
                    foreach ($everyDay as $day) {
                        $level->schedules()->create(['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end, 'facility_id' => $court->id]);
                    }
                }

                if ($level->activeEnrollments()->doesntExist()) {
                    $offset = (int) Member::max('member_number');
                    $students = Member::factory()->count(6)->sequence(fn ($s) => ['member_number' => str_pad((string) ($offset + $s->index + 1), 5, '0', STR_PAD_LEFT)])->create([
                        'member_category_id' => $category->id,
                        'gender' => 'F',
                        'first_name' => fn () => fake()->firstName('female'),
                        'birth_date' => fn () => today()->subYears(rand(13, 17))->toDateString(),
                        'admission_date' => today()->subMonths(3),
                    ]);
                    $students->each(fn (Member $m) => rescue(fn () => $enrollments->enroll($m, $level), report: false));
                }
            }

            // Alumna con acceso a la app (Juvenil A), con la cuota del mes pendiente.
            if (! User::where('email', 'alumna@demo.com')->exists()) {
                $user = User::create(['name' => 'Martina Alumna', 'email' => 'alumna@demo.com', 'password' => self::PASSWORD, 'is_active' => true]);
                $member = Member::create([
                    'first_name' => 'Martina', 'last_name' => 'Alumna', 'document_type' => 'DNI', 'document_number' => '45123456',
                    'birth_date' => today()->subYears(15)->toDateString(), 'gender' => 'F', 'email' => 'alumna@demo.com',
                    'member_category_id' => $category->id, 'status' => MemberStatus::Active, 'member_number' => '09001',
                    'admission_date' => today()->subMonths(2), 'user_id' => $user->id,
                ]);
                $enrollments->enroll($member, Activity::where('slug', 'juvenil-a')->firstOrFail());
            }

            app(LevelLessonService::class)->generate();
        });

        $this->command?->info('Academia de Tenis lista. Contraseña de todos: '.self::PASSWORD);
        $this->command?->info('Coordinación: laura@demo.com · Profesores: diego@demo.com, sofia@demo.com · Alumna: alumna@demo.com');
    }

    private function staff(string $name, string $email, string $role): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}

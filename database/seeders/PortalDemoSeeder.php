<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Activity;
use App\Models\Facility;
use App\Models\HeroSlide;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contenido de ejemplo para el sitio público de un club de gimnasia (artística, rítmica y trampolín).
 * No pisa nada cargado: solo agrega lo que falta y completa imágenes vacías.
 * Las fotos son de Wikimedia Commons (licencias libres, ver CREDITOS.txt junto a las imágenes) y se descargan
 * a storage/app/public/demo; sin conexión, quedan los fondos por defecto.
 * Uso: php artisan db:seed --class=PortalDemoSeeder (entidad principal)
 *      PowerShell: $env:SITE_ORG = 'slug'; php artisan db:seed --class=PortalDemoSeeder
 */
class PortalDemoSeeder extends Seeder
{
    /** Archivo de Commons => licencia. */
    private const PHOTOS = [
        'floor' => ["2019-06-30 1st FIG Artistic Gymnastics JWCH Women's Apparatus finals Floor exercise (Martin Rulsch) 442.jpg", 'CC BY-SA 4.0'],
        'rings' => ["2018-10-08 Gymnastics at 2018 Summer Youth Olympics – Boys' Artistic Gymnastics – Rings qualification (Martin Rulsch) 0648.jpg", 'CC BY-SA 4.0'],
        'rhythmic-group' => ['Azerbaijan rhythmic gymnastics team at the 2024 Summer Olympics 38.jpg', 'CC BY-SA 4.0'],
        'rhythmic-hoops' => ['Belarus Rhythmic gymnastics team 2012 Summer Olympics 11.jpg', 'CC BY 2.0'],
        'rhythmic-balls' => ['Belarus Rhythmic gymnastics team 2012 Summer Olympics 03.jpg', 'CC BY 2.0'],
        'rhythmic-team' => ['Belarus Rhythmic gymnastics team 2012 Summer Olympics 12.jpg', 'CC BY 2.0'],
        'hoop' => ['2018-10-16 Gymnastics at 2018 Summer Youth Olympics – Rhythmic Gymnastics - Hoop final (Martin Rulsch) 206.jpg', 'CC BY-SA 4.0'],
        'bars' => ['Skubik on Uneven Bars 2006.JPG', 'CC BY 3.0'],
        'bars-2' => ['2024 US Classic Uneven Bars 23.jpg', 'CC BY 4.0'],
        'still-rings' => ["2018-10-14 Gymnastics at 2018 Summer Youth Olympics – Boys' Artistic Gymnastics – Apparatus finals – Still rings (Martin Rulsch) 279.jpg", 'CC BY-SA 4.0'],
        'beam' => ['Canadian gymnast at the 2020 Gymnix Cup beam routine.jpg', 'CC0'],
        'floor-men' => ["2019-06-29 1st FIG Artistic Gymnastics JWCH Men's Apparatus finals Floor exercise (Martin Rulsch) 464.jpg", 'CC BY-SA 4.0'],
        'floor-2' => ["2019-06-30 1st FIG Artistic Gymnastics JWCH Women's Apparatus finals Floor exercise (Martin Rulsch) 415.jpg", 'CC BY-SA 4.0'],
        'trampoline' => ["2018-10-14 Gymnastics at 2018 Summer Youth Olympics – Boys' Trampoline Gymnastics – Final (Martin Rulsch) 358.jpg", 'CC BY-SA 4.0'],
        'trampoline-2' => ["2018-10-14 Gymnastics at 2018 Summer Youth Olympics – Boys' Trampoline Gymnastics – Final (Martin Rulsch) 010.jpg", 'CC BY-SA 4.0'],
        'trampoline-hall' => ['2021 Trampoline WAGC, Baku.jpg', 'CC BY-SA 4.0'],
        'arena' => ['Uneven Bars Commonwealth Games.jpg', 'CC BY 2.0'],
        'equipment' => ['Spare rhythmic gymnastics equipment.jpg', 'Dominio público'],
        'vault' => ['2022-04-09 25th Rheintalcup 2022 P6 Junior competition Subdivision 2 Vault (Martin Rulsch) 063.jpg', 'CC BY-SA 4.0'],
    ];

    public function run(): void
    {
        $org = ($slug = env('SITE_ORG'))
            ? Organization::where('slug', $slug)->firstOrFail()
            : (Organization::where('is_default', true)->first() ?? Organization::query()->orderBy('id')->firstOrFail());

        Organization::runFor($org, function (Organization $org) {
            $this->seedSlides();
            $this->seedSections();
            $this->seedPosts();
            $this->seedActivities();
            $this->seedFacilities();
            $this->seedPages();
            $this->writeCredits();

            $this->command?->info("Sitio de ejemplo cargado en «{$org->name}».");
        });
    }

    private function seedSlides(): void
    {
        $slides = [
            ['Volá alto con nosotros', 'Gimnasia artística, rítmica y trampolín para todas las edades, con entrenadores nacionales y un gimnasio totalmente equipado.', 'Asociate', '/asociate', 'Ver disciplinas', '/#actividades', 'floor'],
            ['Formamos gimnastas', 'Desde la escuelita de iniciación hasta el alto rendimiento: cada gimnasta avanza a su ritmo, nivel por nivel.', 'Conocer los niveles', '/actividades', 'El gimnasio', '/#instalaciones', 'rings'],
            ['Rítmica en equipo', 'Cinta, aro, pelota y mazas. Coreografías individuales y de conjunto que compiten en todo el país.', 'Ver gimnasia rítmica', '/actividades/gimnasia-ritmica', null, null, 'rhythmic-group'],
        ];

        if (HeroSlide::query()->exists()) {
            HeroSlide::whereNull('image_path')->orderBy('sort_order')->get()->each(
                fn (HeroSlide $slide, int $i) => $slide->update(['image_path' => $this->photo($slides[$i % 3][6], 1920)])
            );

            return;
        }

        foreach ($slides as $i => [$title, $subtitle, $button, $url, $secondary, $secondaryUrl, $photo]) {
            HeroSlide::create([
                'title' => $title,
                'subtitle' => $subtitle,
                'button_text' => $button,
                'button_url' => $url,
                'secondary_button_text' => $secondary,
                'secondary_button_url' => $secondaryUrl,
                'image_path' => $this->photo($photo, 1920),
                'overlay_opacity' => 70,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function seedSections(): void
    {
        $sections = [
            ['key' => 'nosotros', 'type' => 'about', 'menu_label' => 'El club', 'title' => 'Nuestro club', 'subtitle' => 'Pasión por la gimnasia',
                'content' => "Somos un club dedicado a la gimnasia en todas sus formas. Empezamos con un puñado de chicas y un colchón prestado; hoy entrenan con nosotros cientos de gimnastas, desde los más chiquitos hasta selección provincial.\n\nCreemos en una gimnasia que forma personas: disciplina, esfuerzo y compañerismo, siempre cuidando la salud y los tiempos de cada gimnasta.",
                'photo' => 'rhythmic-hoops'],
            ['key' => 'beneficios', 'type' => 'features', 'title' => '¿Por qué entrenar con nosotros?', 'subtitle' => 'Una gimnasia segura, formativa y competitiva', 'items' => [
                ['icon' => 'trophy', 'title' => 'Entrenadores nacionales', 'text' => 'Profesores con título y experiencia en competencias federadas.'],
                ['icon' => 'shield', 'title' => 'Gimnasio seguro', 'text' => 'Aparatos reglamentarios, colchonetas de recepción y foso de espuma.'],
                ['icon' => 'flag', 'title' => 'Niveles y torneos', 'text' => 'Cada gimnasta avanza por niveles y compite en torneos regionales y nacionales.'],
                ['icon' => 'device', 'title' => 'Portal del socio', 'text' => 'Consultá tu cuenta, pagá las cuotas y mostrá tu carnet digital desde el celular.'],
            ]],
            ['key' => 'cifras', 'type' => 'stats', 'title' => 'El club en números', 'items' => [
                ['value' => '25', 'title' => 'Años de historia'],
                ['value' => '300+', 'title' => 'Gimnastas'],
                ['value' => '4', 'title' => 'Disciplinas'],
                ['value' => '150+', 'title' => 'Medallas'],
            ]],
            ['key' => 'actividades', 'type' => 'activities', 'menu_label' => 'Disciplinas', 'title' => 'Disciplinas', 'subtitle' => 'Encontrá tu lugar en el gimnasio'],
            ['key' => 'instalaciones', 'type' => 'facilities', 'menu_label' => 'Instalaciones', 'title' => 'Instalaciones', 'subtitle' => 'Un gimnasio pensado para entrenar seguro'],
            ['key' => 'noticias', 'type' => 'news', 'menu_label' => 'Noticias', 'title' => 'Novedades', 'subtitle' => 'Lo que pasa en el club'],
            ['key' => 'asociate', 'type' => 'cta', 'title' => 'Sumate al club', 'subtitle' => 'Completá la solicitud online y coordinamos una clase de prueba sin cargo.', 'content' => 'Quiero asociarme'],
            ['key' => 'contacto', 'type' => 'contact', 'menu_label' => 'Contacto', 'title' => 'Contacto', 'subtitle' => 'Escribinos y te respondemos a la brevedad'],
        ];

        foreach ($sections as $order => $data) {
            $photo = $data['photo'] ?? null;
            unset($data['photo']);
            $section = SiteSection::firstOrCreate(['key' => $data['key']], [...$data, 'sort_order' => ($order + 1) * 10]);

            if ($photo && ! $section->image_path) {
                $section->update(['image_path' => $this->photo($photo)]);
            }
        }
    }

    private function seedPosts(): void
    {
        $posts = [
            ['Abrimos la inscripción a la escuelita de iniciación', 'bars-2',
                "Desde el lunes está abierta la inscripción a la escuelita de iniciación para chicos y chicas de 4 a 7 años.\n\nLas clases son martes y jueves de 17 a 18 y trabajamos coordinación, equilibrio y los primeros elementos acrobáticos a través del juego. La primera clase es de prueba y sin cargo.\n\nPodés inscribirte desde el portal del socio o en secretaría."],
            ['Tres medallas en el Torneo Regional de artística', 'floor-2',
                "Nuestras gimnastas de nivel 3 y 4 volvieron del Torneo Regional con un oro en suelo, una plata en viga y un bronce en el all-around.\n\nFelicitaciones a las chicas, a las entrenadoras y a las familias que acompañaron todo el fin de semana."],
            ['El conjunto de rítmica clasificó al Nacional', 'rhythmic-team',
                "Con una rutina de cinco aros impecable, el conjunto juvenil de rítmica obtuvo su lugar en el Campeonato Nacional.\n\nDurante las próximas semanas vamos a organizar una rifa para ayudar con el viaje. ¡Gracias por el apoyo!"],
            ['Llegó el trampolín nuevo', 'trampoline-2',
                "Ya está instalado el nuevo trampolín reglamentario, comprado con el aporte de las familias y la cooperadora.\n\nCon él ampliamos los cupos de la disciplina y sumamos un horario los sábados a la mañana."],
            ['Clínica de salto con entrenador internacional', 'vault',
                "El sábado recibimos una clínica de salto abierta a gimnastas de nivel 4 en adelante, a cargo de un entrenador con experiencia internacional.\n\nLos cupos son limitados: anotate con tu entrenador antes del jueves."],
            ['Se viene la muestra anual', 'rhythmic-balls',
                "Como cada diciembre, todas las disciplinas del club se suben al tapiz en la muestra anual: iniciación, artística, rítmica y trampolín.\n\nLa entrada es libre para socios y familiares. Pronto publicamos el cronograma de pasadas."],
        ];

        foreach ($posts as $i => [$title, $photo, $body]) {
            $post = Post::firstOrCreate(['slug' => Str::slug($title)], [
                'title' => $title,
                'excerpt' => Str::limit(Str::before($body, "\n"), 160),
                'body' => $body,
                'status' => PostStatus::Published,
                'published_at' => now()->subDays($i * 5 + 1)->setTime(10, 0),
            ]);

            if (! $post->image_path) {
                $post->update(['image_path' => $this->photo($photo)]);
            }
        }
    }

    private function seedActivities(): void
    {
        $activities = [
            ['Escuelita de iniciación', 'Primeros pasos en la gimnasia, jugando.', "Coordinación, equilibrio, rolidos y primeros saltos a través del juego, en grupos reducidos.\n\nIdeal para que los más chicos descubran la gimnasia y pasen después a artística, rítmica o trampolín.", 8000, 20, 4, 7, [[2, '17:00', '18:00'], [4, '17:00', '18:00']], 'Gimnasio principal', 'beam'],
            ['Gimnasia artística femenina', 'Viga, paralelas asimétricas, salto y suelo.', "Entrenamiento por niveles en los cuatro aparatos, con competencias en el circuito federado.\n\nLos grupos se arman según nivel y edad después de una evaluación inicial.", 14000, 30, 6, null, [[1, '17:30', '20:00'], [3, '17:30', '20:00'], [5, '17:30', '20:00']], 'Gimnasio principal', 'bars'],
            ['Gimnasia artística masculina', 'Anillas, barra, paralelas, arzones, salto y suelo.', "Formación de fuerza, técnica y acrobacia en los seis aparatos masculinos.\n\nGrupos por nivel, desde iniciados hasta competencia federada.", 14000, 20, 6, null, [[2, '18:00', '20:30'], [4, '18:00', '20:30']], 'Gimnasio principal', 'still-rings'],
            ['Gimnasia rítmica', 'Cinta, aro, pelota, mazas y cuerda.', "Técnica corporal, flexibilidad y manejo de aparatos, con coreografías individuales y de conjunto.\n\nCompetimos en torneos provinciales y nacionales.", 12000, 25, 5, null, [[1, '18:00', '20:00'], [3, '18:00', '20:00'], [6, '09:30', '11:30']], 'Sala de rítmica', 'hoop'],
            ['Trampolín', 'Saltos, giros y mortales en trampolín reglamentario.', "Trabajamos progresiones seguras con arnés y colchonetas de recepción.\n\nAbierto a chicos desde 8 años y a adultos que quieran probar algo distinto.", 11000, 12, 8, null, [[2, '20:00', '21:30'], [6, '11:30', '13:00']], 'Sala de trampolines', 'trampoline'],
            ['Acrobacia para adultos', 'Gimnasia y acrobacia para mayores de 16.', "Nunca es tarde para aprender una vertical o una medialuna: clases para adultos de todos los niveles.\n\nTrabajamos fuerza, movilidad y elementos acrobáticos de suelo.", 9000, 15, 16, null, [[2, '21:00', '22:00'], [4, '21:00', '22:00']], 'Gimnasio principal', 'floor-men'],
        ];

        foreach ($activities as [$name, $summary, $description, $fee, $capacity, $minAge, $maxAge, $schedules, $location, $photo]) {
            $activity = Activity::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'summary' => $summary,
                'description' => $description,
                'monthly_fee' => $fee,
                'capacity' => $capacity,
                'min_age' => $minAge,
                'max_age' => $maxAge,
            ]);

            if ($activity->wasRecentlyCreated) {
                foreach ($schedules as [$day, $start, $end]) {
                    $activity->schedules()->create(['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end, 'location' => $location]);
                }
            }

            if (! $activity->image_path) {
                $activity->update(['image_path' => $this->photo($photo)]);
            }
        }
    }

    private function seedFacilities(): void
    {
        $facilities = [
            ['Gimnasio principal', 'Aparatos reglamentarios de artística, tapiz de suelo y foso de espuma.', 0, 60, '08:00', '22:00', 60, 1, 'arena'],
            ['Sala de rítmica', 'Piso flotante, espejos y aparatos de rítmica para todas las categorías.', 12000, 25, '08:00', '22:00', 60, 2, 'equipment'],
            ['Sala de trampolines', 'Trampolines reglamentarios con colchonetas de recepción y arnés.', 0, 12, '09:00', '22:00', 60, 1, 'trampoline-hall'],
        ];

        foreach ($facilities as [$name, $description, $rate, $capacity, $opens, $closes, $slot, $maxSlots, $photo]) {
            $facility = Facility::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'description' => $description,
                'hourly_rate' => $rate,
                'capacity' => $capacity,
                'opens_at' => $opens,
                'closes_at' => $closes,
                'slot_minutes' => $slot,
                'max_slots_per_booking' => $maxSlots,
                'is_bookable' => $rate > 0,
            ]);

            if (! $facility->image_path) {
                $facility->update(['image_path' => $this->photo($photo)]);
            }
        }
    }

    private function seedPages(): void
    {
        Page::firstOrCreate(['slug' => 'estatuto'], [
            'title' => 'Estatuto social',
            'body' => "Aquí se publica el estatuto social vigente del club.\n\nEditá este contenido desde Administración > Sitio web > Páginas.",
            'show_in_menu' => false,
        ]);
        Page::firstOrCreate(['slug' => 'reglamento'], [
            'title' => 'Reglamento del gimnasio',
            'body' => "Ingresar al gimnasio solo con el entrenador presente.\n\nRopa ajustada, pelo recogido y sin accesorios. Respetar los turnos de cada aparato.",
            'show_in_menu' => false,
        ]);
    }

    /** Descarga (una sola vez) la foto de Wikimedia Commons al disco público. Devuelve null si no hay conexión. */
    private function photo(string $key, int $width = 1280): ?string
    {
        [$file] = self::PHOTOS[$key];
        $path = $this->dir().'/'.$key.'.jpg';

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        $response = rescue(fn () => Http::timeout(30)
            ->withUserAgent('SistemaClub/1.0 (seeder de demostración)')
            ->get('https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($file), ['width' => $width]), report: false);

        if (! $response?->successful()) {
            $this->command?->warn("No se pudo descargar la imagen {$key}; se usa el fondo por defecto.");

            return null;
        }

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function writeCredits(): void
    {
        $lines = collect(self::PHOTOS)
            ->filter(fn ($photo, string $key) => Storage::disk('public')->exists($this->dir().'/'.$key.'.jpg'))
            ->map(fn (array $photo, string $key) => "{$key}.jpg — {$photo[1]} — https://commons.wikimedia.org/wiki/File:".rawurlencode(str_replace(' ', '_', $photo[0])));

        if ($lines->isNotEmpty()) {
            Storage::disk('public')->put($this->dir().'/CREDITOS.txt', "Fotos de Wikimedia Commons (autor y licencia en cada página):\n\n".$lines->implode("\n")."\n");
        }
    }

    private function dir(): string
    {
        return 'demo/'.Organization::currentId();
    }
}

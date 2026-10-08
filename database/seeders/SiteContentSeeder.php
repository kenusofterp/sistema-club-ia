<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\HeroSlide;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Contenido inicial de la página institucional; todo es editable desde Administración > Sitio web. */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        if (Organization::current()?->type === 'gimnasio') {
            $this->seedGym();

            return;
        }

        if (HeroSlide::query()->doesntExist()) {
            HeroSlide::create([
                'title' => 'Viví el club',
                'subtitle' => 'Deportes, cultura y encuentro para toda la familia. Sumate a nuestra comunidad.',
                'button_text' => 'Asociate',
                'button_url' => '/asociate',
                'secondary_button_text' => 'Ver actividades',
                'secondary_button_url' => '/#actividades',
                'overlay_opacity' => 55,
                'sort_order' => 1,
            ]);
            HeroSlide::create([
                'title' => 'Más de 10 disciplinas',
                'subtitle' => 'Fútbol, natación, tenis, básquet, gimnasia y mucho más, con profesores especializados.',
                'button_text' => 'Conocer actividades',
                'button_url' => '/actividades',
                'overlay_opacity' => 55,
                'sort_order' => 2,
            ]);
        }

        $sections = [
            ['key' => 'nosotros', 'type' => 'about', 'menu_label' => 'El club', 'title' => 'Nuestro club', 'subtitle' => 'Una historia construida entre todos',
                'content' => "Somos una institución sin fines de lucro dedicada a promover el deporte, la cultura y la vida social de nuestra comunidad.\n\nGeneración tras generación, el club es punto de encuentro de familias, amigos y vecinos que comparten valores de respeto, compañerismo y pertenencia."],
            ['key' => 'beneficios', 'type' => 'features', 'title' => '¿Por qué ser socio?', 'subtitle' => 'Beneficios pensados para toda la familia', 'items' => [
                ['icon' => 'trophy', 'title' => 'Deportes para todos', 'text' => 'Disciplinas formativas y competitivas para todas las edades.'],
                ['icon' => 'users', 'title' => 'Vida social', 'text' => 'Eventos, encuentros y actividades culturales durante todo el año.'],
                ['icon' => 'building', 'title' => 'Instalaciones', 'text' => 'Canchas, pileta, gimnasio y quincho con reserva online.'],
                ['icon' => 'device', 'title' => 'Portal del socio', 'text' => 'Consultá tu cuenta, pagá tus cuotas y mostrá tu carnet digital.'],
            ]],
            ['key' => 'cifras', 'type' => 'stats', 'title' => 'El club en números', 'items' => [
                ['value' => '75', 'title' => 'Años de historia'],
                ['value' => '1.500+', 'title' => 'Socios'],
                ['value' => '12', 'title' => 'Disciplinas'],
                ['value' => '8', 'title' => 'Instalaciones'],
            ]],
            ['key' => 'planes', 'type' => 'plans', 'menu_label' => 'Planes', 'title' => 'Planes y precios', 'subtitle' => 'Elegí cómo querés entrenar: acceso libre a la sala, clases o ambos'],
            ['key' => 'actividades', 'type' => 'activities', 'menu_label' => 'Actividades', 'title' => 'Actividades', 'subtitle' => 'Encontrá la disciplina ideal para vos'],
            ['key' => 'instalaciones', 'type' => 'facilities', 'menu_label' => 'Instalaciones', 'title' => 'Instalaciones', 'subtitle' => 'Espacios pensados para el deporte y el encuentro'],
            ['key' => 'noticias', 'type' => 'news', 'menu_label' => 'Noticias', 'title' => 'Novedades', 'subtitle' => 'Lo que pasa en el club'],
            ['key' => 'asociate', 'type' => 'cta', 'title' => 'Sumate al club', 'subtitle' => 'Completá la solicitud online y empezá a disfrutar de todos los beneficios.', 'content' => 'Quiero asociarme'],
            ['key' => 'contacto', 'type' => 'contact', 'menu_label' => 'Contacto', 'title' => 'Contacto', 'subtitle' => 'Escribinos y te respondemos a la brevedad'],
        ];

        foreach ($sections as $order => $section) {
            SiteSection::firstOrCreate(['key' => $section['key']], [...$section, 'sort_order' => ($order + 1) * 10]);
        }

        if (Post::query()->doesntExist()) {
            $posts = [
                ['Abrimos la inscripción a la temporada de verano', 'Ya está abierta la inscripción a la colonia de vacaciones y a la temporada de pileta. Los socios con la cuota al día tienen prioridad.'],
                ['Nuevo portal de socios', 'Lanzamos el portal de socios: consultá tus cuotas, inscribite en actividades, reservá canchas y mostrá tu carnet digital desde el celular.'],
                ['Asamblea general ordinaria', 'Se convoca a los socios a la asamblea general ordinaria. El orden del día está disponible en secretaría.'],
            ];

            foreach ($posts as $i => [$title, $body]) {
                Post::create([
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'excerpt' => Str::limit($body, 160),
                    'body' => $body,
                    'status' => PostStatus::Published,
                    'published_at' => now()->subDays($i * 7),
                ]);
            }
        }

        Page::firstOrCreate(['slug' => 'estatuto'], [
            'title' => 'Estatuto social',
            'body' => "Aquí se publica el estatuto social vigente del club.\n\nEditá este contenido desde Administración > Sitio web > Páginas.",
            'show_in_menu' => false,
        ]);
        Page::firstOrCreate(['slug' => 'reglamento'], [
            'title' => 'Reglamento interno',
            'body' => 'Normas de convivencia y uso de las instalaciones del club.',
            'show_in_menu' => false,
        ]);
    }

    /** Contenido inicial para un gimnasio. */
    private function seedGym(): void
    {
        if (HeroSlide::query()->doesntExist()) {
            HeroSlide::create([
                'title' => 'Entrená a tu ritmo',
                'subtitle' => 'Sala de musculación equipada, clases grupales y profes que te acompañan. Elegí tu plan y empezá hoy.',
                'button_text' => 'Ver planes',
                'button_url' => '/#planes',
                'secondary_button_text' => 'Clases y horarios',
                'secondary_button_url' => '/actividades',
                'overlay_opacity' => 55,
                'sort_order' => 1,
            ]);
        }

        $sections = [
            ['key' => 'planes', 'type' => 'plans', 'menu_label' => 'Planes', 'title' => 'Planes y precios', 'subtitle' => 'Acceso libre a la sala, clases o ambos'],
            ['key' => 'beneficios', 'type' => 'features', 'title' => '¿Por qué entrenar con nosotros?', 'items' => [
                ['icon' => 'clock', 'title' => 'Horario extendido', 'text' => 'Abierto de lunes a sábado desde temprano hasta la noche.'],
                ['icon' => 'users', 'title' => 'Profes en sala', 'text' => 'Te armamos la rutina y te acompañamos en cada entrenamiento.'],
                ['icon' => 'calendar', 'title' => 'Clases grupales', 'text' => 'Funcional, spinning, yoga y más, con cupos limitados.'],
                ['icon' => 'device', 'title' => 'Todo desde el celular', 'text' => 'Tu plan, tus visitas y tu carnet digital en el portal.'],
            ]],
            ['key' => 'actividades', 'type' => 'activities', 'menu_label' => 'Clases', 'title' => 'Clases', 'subtitle' => 'Horarios de las clases grupales'],
            ['key' => 'noticias', 'type' => 'news', 'menu_label' => 'Novedades', 'title' => 'Novedades', 'subtitle' => 'Lo último del gimnasio'],
            ['key' => 'contacto', 'type' => 'contact', 'menu_label' => 'Contacto', 'title' => 'Contacto', 'subtitle' => 'Escribinos o vení a conocernos'],
        ];

        foreach ($sections as $order => $section) {
            SiteSection::firstOrCreate(['key' => $section['key']], [...$section, 'sort_order' => ($order + 1) * 10]);
        }

        Page::firstOrCreate(['slug' => 'reglamento'], [
            'title' => 'Reglamento del gimnasio',
            'body' => "Uso obligatorio de toalla.\n\nRespetá los horarios de tu plan y el cupo de las clases.",
            'show_in_menu' => false,
        ]);
        Page::firstOrCreate(['slug' => 'estatuto'], [
            'title' => 'Términos y condiciones',
            'body' => 'Condiciones de contratación de los planes.',
            'show_in_menu' => false,
        ]);
    }
}

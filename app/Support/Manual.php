<?php

namespace App\Support;

/**
 * Índice del manual de uso (Administración › Manual de uso). Lo usan la pantalla y la versión para
 * imprimir. Cada sección es un <section id="..."> en resources/views/help/content.blade.php.
 */
final class Manual
{
    /** @return array<int, array{title: string, items: array<string, string>}> */
    public static function groups(): array
    {
        return [
            ['title' => 'Por dónde empezar', 'items' => [
                'primeros-pasos' => 'Primeros pasos: un club',
                'primeros-pasos-profe' => 'Primeros pasos: profe que alquila canchas',
                'primeros-pasos-competencias' => 'Primeros pasos: organizar una competencia',
                'conceptos' => 'Conceptos básicos',
            ]],
            ['title' => 'Puesta en marcha', 'items' => [
                'ingresar' => '1. Ingresar al sistema',
                'entidad' => '2. Crear la entidad',
                'configuracion' => '3. Configuración',
                'categorias' => '4. Categorías de socios',
                'socios' => '5. Socios, foto y carnet',
            ]],
            ['title' => 'Dinero', 'items' => [
                'cuotas' => '6. Cuotas y cargos',
                'pagos' => '7. Cobrar y emitir recibos',
                'saldo' => '7a. Saldo a favor y devoluciones',
                'gastos' => '7b. Gastos y resultado del mes',
            ]],
            ['title' => 'Actividades y clases', 'items' => [
                'actividades' => '8. Actividades e inscripciones',
                'instalaciones' => '9. Instalaciones y reservas',
                'gimnasio' => '10. Gimnasio: planes',
                'agenda' => '11. Agenda de clases',
                'niveles' => '12. Niveles, asistencia y el celular',
                'becas' => '12b. Becas, aumentos e inscripción anual',
                'torneos' => '12c. Torneos',
                'mensajes-alumnos' => '12d. Mensajes a alumnos',
                'vestimenta' => '12e. Vestimenta y artículos',
            ]],
            ['title' => 'Competencias y jueceo', 'items' => [
                'competencias' => '12f. Competencias: cómo funciona',
                'competencias-preparar' => '12g. Preparar una competencia',
                'competencias-jueces' => '12h. El juez en el celular',
                'competencias-dia' => '12i. Durante el torneo',
                'competencias-pantallas' => '12j. Pantallas y resultados',
                'competencias-ejemplo' => '12k. Ejemplo: Torneo Primavera',
                'competencias-faq' => '12l. Preguntas frecuentes',
            ]],
            ['title' => 'Día a día', 'items' => [
                'acceso' => '13. Control de acceso y asistencia',
                'comunicacion' => '14. Mensajes y avisos',
                'sitio' => '15. Sitio web',
                'usuarios' => '16. Usuarios y roles',
                'auditoria' => '17. Auditoría y reportes',
                'portal' => '18. Portal del socio',
                'automaticos' => '19. Procesos automáticos',
            ]],
            ['title' => 'Ayuda', 'items' => [
                'ejemplo' => 'Ejemplo completo',
                'faq' => 'Preguntas frecuentes',
            ]],
        ];
    }
}

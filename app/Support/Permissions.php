<?php

namespace App\Support;

/**
 * Catálogo de permisos del sistema y roles predefinidos.
 * El rol "super-admin" no necesita permisos: se le concede todo vía Gate::before.
 */
final class Permissions
{
    /** @return array<string, array<string, string>> grupo => [permiso => descripción] */
    public static function grouped(): array
    {
        return [
            'General' => [
                'dashboard.ver' => 'Ver tablero de control',
                'reportes.ver' => 'Ver reportes y exportar datos',
            ],
            'Socios' => [
                'socios.ver' => 'Ver socios',
                'socios.crear' => 'Registrar socios',
                'socios.editar' => 'Editar socios y cambiar su estado',
                'socios.eliminar' => 'Eliminar socios',
                'categorias.gestionar' => 'Gestionar categorías de socios',
            ],
            'Actividades' => [
                'actividades.ver' => 'Ver actividades',
                'actividades.gestionar' => 'Crear y editar actividades y horarios',
                'inscripciones.gestionar' => 'Inscribir y dar de baja socios en actividades',
            ],
            'Tesorería' => [
                'cuotas.ver' => 'Ver cuotas y cargos',
                'cuotas.gestionar' => 'Generar, crear y anular cargos',
                'pagos.ver' => 'Ver pagos',
                'pagos.registrar' => 'Registrar pagos',
                'pagos.anular' => 'Anular pagos',
            ],
            'Gimnasio' => [
                'planes.gestionar' => 'Crear y editar planes / membresías',
                'suscripciones.gestionar' => 'Ver, asignar y cancelar planes de socios',
            ],
            'Instalaciones' => [
                'instalaciones.gestionar' => 'Gestionar instalaciones',
                'reservas.ver' => 'Ver reservas',
                'reservas.gestionar' => 'Crear y cancelar reservas',
                'acceso.registrar' => 'Control de acceso de socios',
            ],
            'Comunicación' => [
                'sitio.gestionar' => 'Gestionar la página institucional',
                'mensajes.ver' => 'Ver mensajes de contacto',
                'avisos.gestionar' => 'Publicar avisos para socios',
            ],
            'Administración' => [
                'usuarios.gestionar' => 'Gestionar usuarios del sistema',
                'roles.gestionar' => 'Gestionar roles y permisos',
                'auditoria.ver' => 'Ver auditoría',
                'configuracion.gestionar' => 'Modificar la configuración del sistema',
            ],
        ];
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::grouped())));
    }

    /** @return array<string, array{label: string, permissions: array<int, string>}> */
    public static function defaultRoles(): array
    {
        $all = self::all();

        return [
            'administrador' => [
                'label' => 'Administrador',
                'permissions' => array_values(array_diff($all, ['roles.gestionar'])),
            ],
            'tesorero' => [
                'label' => 'Tesorería',
                'permissions' => ['dashboard.ver', 'reportes.ver', 'socios.ver', 'cuotas.ver', 'cuotas.gestionar', 'pagos.ver', 'pagos.registrar', 'pagos.anular', 'reservas.ver', 'suscripciones.gestionar'],
            ],
            'secretaria' => [
                'label' => 'Secretaría',
                'permissions' => ['dashboard.ver', 'socios.ver', 'socios.crear', 'socios.editar', 'actividades.ver', 'inscripciones.gestionar', 'cuotas.ver', 'pagos.ver', 'pagos.registrar', 'reservas.ver', 'reservas.gestionar', 'acceso.registrar', 'mensajes.ver', 'avisos.gestionar', 'suscripciones.gestionar'],
            ],
            'recepcion' => [
                'label' => 'Recepción',
                'permissions' => ['socios.ver', 'acceso.registrar', 'reservas.ver', 'reservas.gestionar', 'suscripciones.gestionar', 'pagos.registrar', 'pagos.ver'],
            ],
            'profesor' => [
                'label' => 'Profesor/a',
                'permissions' => ['actividades.ver', 'socios.ver'],
            ],
            'comunicacion' => [
                'label' => 'Comunicación',
                'permissions' => ['sitio.gestionar', 'mensajes.ver', 'avisos.gestionar'],
            ],
        ];
    }
}

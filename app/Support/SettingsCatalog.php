<?php

namespace App\Support;

/**
 * Definición de todas las configuraciones editables desde el sistema.
 * El seeder crea las que falten sin pisar los valores ya cargados.
 */
final class SettingsCatalog
{
    public const GROUPS = [
        'site' => 'Identidad del sitio',
        'contact' => 'Contacto',
        'social' => 'Redes sociales',
        'seo' => 'SEO',
        'pwa' => 'Aplicación (PWA)',
        'club' => 'Reglas del club',
        'gym' => 'Gimnasio',
        'lessons' => 'Agenda de clases',
        'payments' => 'Cobros',
    ];

    /** @return array<string, string> opciones de una configuración de tipo select */
    public static function options(string $key): array
    {
        return collect(self::definitions())->firstWhere('key', $key)['options'] ?? [];
    }

    /** @return array<int, array{group: string, key: string, type: string, label: string, value: mixed, help?: string, source?: string, options?: array<string, string>}> source: archivo en public/ que se copia al storage como valor inicial */
    public static function definitions(): array
    {
        return [
            // Identidad
            ['group' => 'site', 'key' => 'site.name', 'type' => 'string', 'label' => 'Nombre del club', 'value' => 'Club Social y Deportivo'],
            ['group' => 'site', 'key' => 'site.short_name', 'type' => 'string', 'label' => 'Nombre corto / siglas', 'value' => 'CSD'],
            ['group' => 'site', 'key' => 'site.tagline', 'type' => 'string', 'label' => 'Lema', 'value' => 'Deporte, familia y comunidad desde siempre'],
            ['group' => 'site', 'key' => 'site.logo', 'type' => 'image', 'label' => 'Logo', 'value' => 'branding/logo-corto.png', 'source' => 'images/LogoCorto.png', 'help' => 'PNG o SVG con fondo transparente. Recomendado 400×400 px.'],
            ['group' => 'site', 'key' => 'site.logo_light', 'type' => 'image', 'label' => 'Logo para fondos oscuros', 'value' => null, 'help' => 'Opcional. Si no se carga, el logo principal se muestra sobre un recuadro blanco en fondos oscuros.'],
            ['group' => 'site', 'key' => 'site.favicon', 'type' => 'image', 'label' => 'Favicon', 'value' => 'branding/logo-corto.png', 'source' => 'images/LogoCorto.png'],
            ['group' => 'site', 'key' => 'site.primary_color', 'type' => 'color', 'label' => 'Color principal', 'value' => '#063c7c'],
            ['group' => 'site', 'key' => 'site.accent_color', 'type' => 'color', 'label' => 'Color de acento', 'value' => '#fe9102'],
            ['group' => 'site', 'key' => 'site.founded_year', 'type' => 'integer', 'label' => 'Año de fundación', 'value' => 1950],
            ['group' => 'site', 'key' => 'site.footer_text', 'type' => 'text', 'label' => 'Texto del pie de página', 'value' => 'Un club abierto a la comunidad, con actividades deportivas, sociales y culturales para todas las edades.'],
            ['group' => 'site', 'key' => 'site.join_enabled', 'type' => 'boolean', 'label' => 'Habilitar formulario "Asociate" en la web', 'value' => true],
            ['group' => 'site', 'key' => 'site.show_login_button', 'type' => 'boolean', 'label' => 'Mostrar botón "Ingresar" en la web', 'value' => true],

            // Contacto
            ['group' => 'contact', 'key' => 'contact.email', 'type' => 'string', 'label' => 'Correo de contacto', 'value' => 'info@club.com', 'help' => 'Recibe los mensajes del formulario de contacto.'],
            ['group' => 'contact', 'key' => 'contact.phone', 'type' => 'string', 'label' => 'Teléfono', 'value' => '+54 11 0000-0000'],
            ['group' => 'contact', 'key' => 'contact.whatsapp', 'type' => 'string', 'label' => 'WhatsApp (solo números con código de país)', 'value' => null],
            ['group' => 'contact', 'key' => 'contact.address', 'type' => 'string', 'label' => 'Dirección', 'value' => 'Av. Siempre Viva 742'],
            ['group' => 'contact', 'key' => 'contact.hours', 'type' => 'text', 'label' => 'Horario de atención', 'value' => "Lunes a viernes de 8 a 22 h\nSábados de 8 a 20 h"],
            ['group' => 'contact', 'key' => 'contact.map_embed_url', 'type' => 'string', 'label' => 'URL de mapa embebido (Google Maps)', 'value' => null, 'help' => 'En Google Maps: Compartir > Insertar un mapa > copiar solo la URL del atributo src.'],

            // Redes
            ['group' => 'social', 'key' => 'social.facebook', 'type' => 'string', 'label' => 'Facebook', 'value' => null],
            ['group' => 'social', 'key' => 'social.instagram', 'type' => 'string', 'label' => 'Instagram', 'value' => null],
            ['group' => 'social', 'key' => 'social.x', 'type' => 'string', 'label' => 'X (Twitter)', 'value' => null],
            ['group' => 'social', 'key' => 'social.youtube', 'type' => 'string', 'label' => 'YouTube', 'value' => null],
            ['group' => 'social', 'key' => 'social.tiktok', 'type' => 'string', 'label' => 'TikTok', 'value' => null],

            // SEO
            ['group' => 'seo', 'key' => 'seo.meta_description', 'type' => 'text', 'label' => 'Descripción para buscadores', 'value' => 'Club social y deportivo: actividades, instalaciones, noticias y portal de socios.'],
            ['group' => 'seo', 'key' => 'seo.og_image', 'type' => 'image', 'label' => 'Imagen para compartir en redes', 'value' => 'branding/logo.png', 'source' => 'images/Logo.png'],

            // PWA
            ['group' => 'pwa', 'key' => 'pwa.theme_color', 'type' => 'color', 'label' => 'Color de la barra del navegador', 'value' => '#063c7c'],
            ['group' => 'pwa', 'key' => 'pwa.background_color', 'type' => 'color', 'label' => 'Color de fondo al abrir la app', 'value' => '#ffffff'],
            ['group' => 'pwa', 'key' => 'pwa.icon', 'type' => 'image', 'label' => 'Ícono de la app (512×512 PNG)', 'value' => 'branding/logo-corto.png', 'source' => 'images/LogoCorto.png'],

            // Reglas del club
            ['group' => 'club', 'key' => 'club.currency_symbol', 'type' => 'string', 'label' => 'Símbolo de moneda', 'value' => '$'],
            ['group' => 'club', 'key' => 'club.currency_decimals', 'type' => 'integer', 'label' => 'Decimales de la moneda', 'value' => 2],
            ['group' => 'club', 'key' => 'club.member_number_prefix', 'type' => 'string', 'label' => 'Prefijo del número de socio', 'value' => null],
            ['group' => 'club', 'key' => 'club.fee_generation_day', 'type' => 'integer', 'label' => 'Día del mes en que se generan las cuotas', 'value' => 1],
            ['group' => 'club', 'key' => 'club.fee_due_day', 'type' => 'integer', 'label' => 'Día de vencimiento de la cuota', 'value' => 10],
            ['group' => 'club', 'key' => 'club.surcharge_percent', 'type' => 'decimal', 'label' => 'Recargo por mora (%)', 'value' => 10, 'help' => 'Se aplica una sola vez cuando el cargo vence. 0 para desactivar.'],
            ['group' => 'club', 'key' => 'club.reminders_enabled', 'type' => 'boolean', 'label' => 'Enviar recordatorios de vencimiento por correo', 'value' => true],
            ['group' => 'club', 'key' => 'club.reminder_days_before', 'type' => 'integer', 'label' => 'Días de anticipación del recordatorio', 'value' => 3],
            ['group' => 'club', 'key' => 'club.max_overdue_for_access', 'type' => 'integer', 'label' => 'Cuotas vencidas que bloquean el ingreso', 'value' => 2, 'help' => '0 para no bloquear el ingreso por deuda.'],
            ['group' => 'club', 'key' => 'club.enrollment_requires_no_debt', 'type' => 'boolean', 'label' => 'Exigir no tener deuda vencida para inscribirse', 'value' => true],
            ['group' => 'club', 'key' => 'club.charge_activity_on_enroll', 'type' => 'boolean', 'label' => 'Cobrar el mes en curso al inscribirse en una actividad', 'value' => true],
            ['group' => 'club', 'key' => 'club.reservation_requires_no_debt', 'type' => 'boolean', 'label' => 'Exigir no tener deuda vencida para reservar', 'value' => true],
            ['group' => 'club', 'key' => 'club.reservation_cancel_hours', 'type' => 'integer', 'label' => 'Horas mínimas para que el socio cancele una reserva', 'value' => 24],
            ['group' => 'club', 'key' => 'club.reservation_max_days_ahead', 'type' => 'integer', 'label' => 'Días máximos de anticipación para reservar', 'value' => 30],
            ['group' => 'club', 'key' => 'club.payment_instructions', 'type' => 'text', 'label' => 'Instrucciones de pago para socios', 'value' => "Podés abonar en secretaría o por transferencia bancaria.\nAlias: CLUB.CUOTAS"],

            // Gimnasio
            ['group' => 'gym', 'key' => 'gym.access_requires_plan', 'type' => 'boolean', 'label' => 'Club con gimnasio: exigir un plan vigente para ingresar', 'value' => false, 'help' => 'En un gimnasio siempre se exige. En un club con gimnasio, si está desactivado, los socios activos ingresan aunque no tengan plan.'],
            ['group' => 'gym', 'key' => 'gym.activate_on_payment', 'type' => 'boolean', 'label' => 'Activar los planes recién cuando se pagan', 'value' => true],
            ['group' => 'gym', 'key' => 'gym.class_checkin_tolerance', 'type' => 'integer', 'label' => 'Minutos de tolerancia para ingresar a una clase', 'value' => 20, 'help' => 'Planes de solo clases: se permite el ingreso desde N minutos antes del inicio hasta el final de la clase.'],
            ['group' => 'gym', 'key' => 'gym.renewal_days_before', 'type' => 'integer', 'label' => 'Días antes del vencimiento en que se genera la renovación automática', 'value' => 3],
            ['group' => 'gym', 'key' => 'gym.pending_expiry_days', 'type' => 'integer', 'label' => 'Días para cancelar planes nuevos impagos', 'value' => 7, 'help' => '0 para no cancelarlos automáticamente.'],
            ['group' => 'gym', 'key' => 'gym.allow_portal_purchase', 'type' => 'boolean', 'label' => 'Permitir que el socio contrate planes desde el portal', 'value' => true],

            // Agenda de clases
            ['group' => 'lessons', 'key' => 'lessons.single_price', 'type' => 'decimal', 'label' => 'Precio por defecto de la clase suelta', 'value' => 0, 'help' => 'Se cobra a los alumnos sin pack del profesor (o sin clases disponibles). Cada profesor puede tener su propio precio y se puede cambiar en cada clase.'],
            ['group' => 'lessons', 'key' => 'lessons.default_minutes', 'type' => 'integer', 'label' => 'Duración por defecto de una clase (minutos)', 'value' => 60],
            ['group' => 'lessons', 'key' => 'activities.label_singular', 'type' => 'string', 'label' => 'Cómo se llaman las actividades (singular)', 'value' => 'Actividad', 'help' => 'Ej.: Actividad, Nivel, Grupo, Curso. Se usa en el menú, el portal y las pantallas.'],
            ['group' => 'lessons', 'key' => 'activities.label_plural', 'type' => 'string', 'label' => 'Cómo se llaman las actividades (plural)', 'value' => 'Actividades'],
            ['group' => 'lessons', 'key' => 'lessons.generate_weeks', 'type' => 'integer', 'label' => 'Semanas de clases que se generan por adelantado para cada actividad', 'value' => 4, 'help' => 'Las clases de las actividades con horario aparecen en la agenda para tomar asistencia y que los alumnos avisen si faltan.'],
            ['group' => 'lessons', 'key' => 'lessons.absence_notice_enabled', 'type' => 'boolean', 'label' => 'Los alumnos pueden avisar desde la app que no van a una clase', 'value' => true],

            // Cobros
            ['group' => 'payments', 'key' => 'payments.receipts_enabled', 'type' => 'boolean', 'label' => 'Los socios pueden informar pagos subiendo el comprobante de transferencia', 'value' => true],
            ['group' => 'payments', 'key' => 'payments.receipts_auto_approve', 'type' => 'boolean', 'label' => 'Acreditar automáticamente los comprobantes (sin revisión)', 'value' => false, 'help' => 'Si está activado, el pago se registra al subir el comprobante y tesorería puede anularlo. Si no, queda en revisión hasta que alguien lo apruebe.'],
            ['group' => 'payments', 'key' => 'payments.transfer_info', 'type' => 'text', 'label' => 'Datos para transferir (CBU / alias / titular)', 'value' => null, 'help' => 'Se muestran al socio cuando informa un pago.'],
            ['group' => 'payments', 'key' => 'payments.instructors_collect_cash', 'type' => 'boolean', 'label' => 'Los profesores pueden cobrar en efectivo a los alumnos de sus actividades', 'value' => true],
            ['group' => 'payments', 'key' => 'payments.cash_requires_settlement', 'type' => 'boolean', 'label' => 'El efectivo cobrado por los profesores se rinde', 'value' => true, 'help' => 'Desactivalo si el profesor es el dueño y no le rinde a nadie.'],
            ['group' => 'payments', 'key' => 'payments.self_settlement_allowed', 'type' => 'boolean', 'label' => 'Quien rinde puede confirmar su propia rendición', 'value' => false, 'help' => 'Para un profesor que trabaja solo y quiere llevar el control de su efectivo: rinde y confirma él mismo desde «Efectivo a rendir».'],
        ];
    }
}

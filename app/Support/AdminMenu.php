<?php

namespace App\Support;

use App\Enums\MemberStatus;
use App\Enums\ReceiptStatus;
use App\Enums\SettlementStatus;
use App\Models\CashSettlement;
use App\Models\ContactMessage;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentReceipt;
use App\Models\User;

/** Menú lateral del panel de administración, filtrado por permisos del usuario. */
final class AdminMenu
{
    /** @return array<int, array{title: string|null, items: array<int, array<string, mixed>>}> */
    public static function for(User $user): array
    {
        $groups = [
            ['title' => null, 'items' => [
                ['label' => 'Tablero', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'active' => 'admin.dashboard'],
                ['label' => 'Manual de uso', 'route' => 'admin.help', 'icon' => 'help'],
            ]],
            ['title' => 'Socios', 'items' => [
                ['label' => 'Socios', 'route' => 'admin.members.index', 'icon' => 'users', 'can' => 'socios.ver', 'active' => 'admin.members.*',
                    'badge' => fn () => Member::where('status', MemberStatus::Pending)->count() ?: null],
                ['label' => 'Categorías', 'route' => 'admin.categories', 'icon' => 'tag', 'can' => 'categorias.gestionar'],
                ['label' => 'Ficha médica', 'route' => 'admin.medical-form', 'icon' => 'heart', 'can' => 'fichas_medicas.configurar'],
                ['label' => 'Control de acceso', 'route' => 'admin.access', 'icon' => 'qr', 'can' => 'acceso.registrar'],
            ]],
            ['title' => activity_label(true), 'items' => [
                ['label' => activity_label(true), 'route' => 'admin.activities.index', 'icon' => 'trophy', 'can' => 'actividades.ver', 'active' => 'admin.activities.*'],
                ['label' => 'Inscripciones', 'route' => 'admin.enrollments', 'icon' => 'clipboard', 'can' => 'inscripciones.gestionar'],
                ['label' => 'Torneos', 'route' => 'admin.tournaments.index', 'icon' => 'flag', 'can' => 'torneos.gestionar', 'active' => 'admin.tournaments.*'],
            ]],
            ['title' => 'Agenda de clases', 'items' => [
                ['label' => 'Clases de hoy', 'route' => 'admin.lessons.today', 'icon' => 'clock', 'can' => 'agenda.ver'],
                ['label' => 'Agenda', 'route' => 'admin.lessons', 'icon' => 'calendar', 'can' => 'agenda.ver'],
                ['label' => 'Cobrar en efectivo', 'route' => 'admin.lessons.collect', 'icon' => 'banknotes', 'can' => 'cobros.niveles', 'when' => fn () => (bool) setting('payments.instructors_collect_cash', true)],
                ['label' => 'Efectivo a rendir', 'route' => 'admin.lessons.cash', 'icon' => 'credit-card', 'can' => 'cobros.niveles', 'when' => fn () => (bool) setting('payments.instructors_collect_cash', true) && (bool) setting('payments.cash_requires_settlement', true)],
                ['label' => 'Packs de clases', 'route' => 'admin.lessons.packs', 'icon' => 'id-card', 'can' => 'agenda.gestionar'],
                ['label' => 'Mis cobros', 'route' => 'admin.lessons.account', 'icon' => 'banknotes', 'can' => 'cobros.propios'],
            ]],
            ['title' => 'Tesorería', 'items' => [
                ['label' => 'Cuotas y cargos', 'route' => 'admin.fees', 'icon' => 'document', 'can' => 'cuotas.ver'],
                ['label' => 'Pagos', 'route' => 'admin.payments.index', 'icon' => 'banknotes', 'can' => 'pagos.ver', 'active' => 'admin.payments.*'],
                ['label' => 'Comprobantes', 'route' => 'admin.receipts', 'icon' => 'document', 'can' => 'comprobantes.revisar',
                    'badge' => fn () => PaymentReceipt::where('status', ReceiptStatus::Pending)->count() ?: null],
                ['label' => 'Rendiciones', 'route' => 'admin.settlements', 'icon' => 'credit-card', 'can' => 'rendiciones.gestionar',
                    'badge' => fn () => CashSettlement::where('status', SettlementStatus::Pending)->count() ?: null],
            ]],
            ['title' => 'Gimnasio', 'gym' => true, 'items' => [
                ['label' => 'Planes de socios', 'route' => 'admin.gym.subscriptions', 'icon' => 'id-card', 'can' => 'suscripciones.gestionar'],
                ['label' => 'Planes y precios', 'route' => 'admin.gym.plans', 'icon' => 'tag', 'can' => 'planes.gestionar'],
            ]],
            ['title' => 'Instalaciones', 'items' => [
                ['label' => 'Instalaciones', 'route' => 'admin.facilities', 'icon' => 'building', 'can' => 'instalaciones.gestionar'],
                ['label' => 'Reservas', 'route' => 'admin.reservations', 'icon' => 'calendar', 'can' => 'reservas.ver'],
            ]],
            ['title' => 'Comunicación', 'items' => [
                ['label' => 'Mensajes', 'route' => 'admin.messages', 'icon' => 'inbox', 'can' => 'mensajes.ver',
                    'badge' => fn () => ContactMessage::unread()->count() ?: null],
                ['label' => 'Avisos a socios', 'route' => 'admin.announcements', 'icon' => 'megaphone', 'can' => 'avisos.gestionar'],
                ['label' => 'Mensajes a alumnos', 'route' => 'admin.member-messages', 'icon' => 'chat', 'can' => 'mensajes.enviar'],
            ]],
            ['title' => 'Sitio web', 'items' => [
                ['label' => 'Identidad y contacto', 'route' => 'admin.site.settings', 'icon' => 'globe', 'can' => 'sitio.gestionar'],
                ['label' => 'Portada (hero)', 'route' => 'admin.site.hero', 'icon' => 'photo', 'can' => 'sitio.gestionar'],
                ['label' => 'Secciones', 'route' => 'admin.site.sections', 'icon' => 'list', 'can' => 'sitio.gestionar'],
                ['label' => 'Noticias', 'route' => 'admin.site.posts.index', 'icon' => 'newspaper', 'can' => 'sitio.gestionar', 'active' => 'admin.site.posts.*'],
                ['label' => 'Páginas', 'route' => 'admin.site.pages', 'icon' => 'document', 'can' => 'sitio.gestionar'],
            ]],
            ['title' => 'Administración', 'items' => [
                ['label' => 'Entidades', 'route' => 'admin.organizations', 'icon' => 'building', 'can' => 'plataforma'],
                ['label' => 'Usuarios', 'route' => 'admin.users', 'icon' => 'user', 'can' => 'usuarios.gestionar'],
                ['label' => 'Roles y permisos', 'route' => 'admin.roles', 'icon' => 'key', 'can' => 'roles.gestionar'],
                ['label' => 'Auditoría', 'route' => 'admin.audit', 'icon' => 'shield', 'can' => 'auditoria.ver'],
                ['label' => 'Configuración del club', 'route' => 'admin.settings', 'icon' => 'cog', 'can' => 'configuracion.gestionar'],
            ]],
        ];

        // Sin entidad activa (instalación desde cero) solo se ofrece crear la primera y la ayuda.
        $withoutOrganization = Organization::current() === null;

        return collect($groups)
            ->reject(fn (array $group) => ($group['gym'] ?? false) && ! uses_gym())
            ->map(function (array $group) use ($user, $withoutOrganization) {
                $group['items'] = collect($group['items'])
                    ->filter(fn (array $item) => ! isset($item['can']) || $user->can($item['can']))
                    ->filter(fn (array $item) => ! isset($item['when']) || ($item['when'])())
                    ->filter(fn (array $item) => ! $withoutOrganization || in_array($item['route'], ['admin.organizations', 'admin.help'], true))
                    ->map(function (array $item) {
                        $item['badge'] = isset($item['badge']) ? ($item['badge'])() : null;
                        $item['active'] = request()->routeIs($item['active'] ?? $item['route']);

                        return $item;
                    })
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }
}

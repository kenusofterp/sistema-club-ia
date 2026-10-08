<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SiteController;
use App\Livewire\Admin;
use App\Livewire\Auth;
use App\Livewire\Portal;
use App\Livewire\Site\JoinForm;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitio institucional (público)
|--------------------------------------------------------------------------
*/
Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/actividades', [SiteController::class, 'activities'])->name('site.activities');
Route::get('/actividades/{activity:slug}', [SiteController::class, 'activity'])->name('site.activity');
Route::get('/noticias', [SiteController::class, 'news'])->name('site.news');
Route::get('/noticias/{post:slug}', [SiteController::class, 'post'])->name('site.post');
Route::get('/p/{page:slug}', [SiteController::class, 'page'])->name('site.page');
Route::get('/asociate', JoinForm::class)->name('site.join');
Route::get('/verificar/{uuid}', [SiteController::class, 'verify'])->whereUuid('uuid')->name('member.verify');

// PWA
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/pwa-icon/{size}.png', [PwaController::class, 'icon'])->whereIn('size', ['192', '512'])->name('pwa.icon');
Route::view('/offline', 'offline')->name('offline');

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/ingresar', Auth\Login::class)->name('login');
    Route::get('/olvide-mi-contrasena', Auth\ForgotPassword::class)->name('password.request');
    Route::get('/restablecer-contrasena/{token}', Auth\ResetPassword::class)->name('password.reset');
});
Route::post('/salir', LogoutController::class)->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Panel de administración
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'admin'])->group(function () {
    Route::get('/', Admin\Dashboard::class)->name('dashboard');
    Route::get('/perfil', Admin\Profile::class)->name('profile');
    Route::get('/ayuda', Admin\Help::class)->name('help');
    Route::post('/entidad', [OrganizationSwitchController::class, 'admin'])->name('organization.switch');
    Route::get('/entidades', Admin\Organizations\Index::class)->name('organizations')->middleware('can:plataforma');

    // Socios
    Route::get('/socios', Admin\Members\Index::class)->name('members.index')->middleware('can:socios.ver');
    Route::get('/socios/nuevo', Admin\Members\Form::class)->name('members.create')->middleware('can:socios.crear');
    Route::get('/socios/{member}', Admin\Members\Show::class)->name('members.show')->middleware('can:socios.ver');
    Route::get('/socios/{member}/editar', Admin\Members\Form::class)->name('members.edit')->middleware('can:socios.editar');
    Route::get('/categorias', Admin\Categories\Index::class)->name('categories')->middleware('can:categorias.gestionar');
    Route::get('/control-de-acceso', Admin\Access\Check::class)->name('access')->middleware('can:acceso.registrar');

    // Actividades
    Route::get('/actividades', Admin\Activities\Index::class)->name('activities.index')->middleware('can:actividades.ver');
    Route::get('/actividades/nueva', Admin\Activities\Form::class)->name('activities.create')->middleware('can:actividades.gestionar');
    Route::get('/actividades/{activity:id}/editar', Admin\Activities\Form::class)->name('activities.edit')->middleware('can:actividades.gestionar');
    Route::get('/inscripciones', Admin\Enrollments\Index::class)->name('enrollments')->middleware('can:inscripciones.gestionar');

    // Tesorería
    Route::get('/cuotas', Admin\Fees\Index::class)->name('fees')->middleware('can:cuotas.ver');
    Route::get('/pagos', Admin\Payments\Index::class)->name('payments.index')->middleware('can:pagos.ver');
    Route::get('/pagos/nuevo', Admin\Payments\Create::class)->name('payments.create')->middleware('can:pagos.registrar');
    Route::get('/pagos/{payment}/recibo', [ReceiptController::class, 'admin'])->name('payments.receipt')->middleware('can:pagos.ver');

    // Gimnasio
    Route::prefix('gimnasio')->name('gym.')->middleware('gym')->group(function () {
        Route::get('/planes', Admin\Gym\Plans::class)->name('plans')->middleware('can:planes.gestionar');
        Route::get('/suscripciones', Admin\Gym\Subscriptions::class)->name('subscriptions')->middleware('can:suscripciones.gestionar');
    });

    // Instalaciones
    Route::get('/instalaciones', Admin\Facilities\Index::class)->name('facilities')->middleware('can:instalaciones.gestionar');
    Route::get('/reservas', Admin\Reservations\Index::class)->name('reservations')->middleware('can:reservas.ver');

    // Comunicación
    Route::get('/mensajes', Admin\Messages\Index::class)->name('messages')->middleware('can:mensajes.ver');
    Route::get('/avisos', Admin\Announcements\Index::class)->name('announcements')->middleware('can:avisos.gestionar');

    // Sitio web
    Route::prefix('sitio')->name('site.')->middleware('can:sitio.gestionar')->group(function () {
        Route::get('/identidad', Admin\Settings\SiteSettings::class)->name('settings');
        Route::get('/portada', Admin\Site\HeroSlides::class)->name('hero');
        Route::get('/secciones', Admin\Site\Sections::class)->name('sections');
        Route::get('/noticias', Admin\Site\Posts\Index::class)->name('posts.index');
        Route::get('/noticias/nueva', Admin\Site\Posts\Form::class)->name('posts.create');
        Route::get('/noticias/{post:id}/editar', Admin\Site\Posts\Form::class)->name('posts.edit');
        Route::get('/paginas', Admin\Site\Pages::class)->name('pages');
    });

    // Administración
    Route::get('/usuarios', Admin\Users\Index::class)->name('users')->middleware('can:usuarios.gestionar');
    Route::get('/roles', Admin\Roles\Index::class)->name('roles')->middleware('can:roles.gestionar');
    Route::get('/auditoria', Admin\Audit\Index::class)->name('audit')->middleware('can:auditoria.ver');
    Route::get('/configuracion', Admin\Settings\ClubSettings::class)->name('settings')->middleware('can:configuracion.gestionar');
    Route::get('/exportar/{type}', ExportController::class)->whereIn('type', ['socios', 'pagos', 'cuotas'])->name('export')->middleware('can:reportes.ver');
});

/*
|--------------------------------------------------------------------------
| Portal del socio
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->name('portal.')->middleware(['auth', 'active', 'member'])->group(function () {
    Route::get('/', Portal\Dashboard::class)->name('dashboard');
    Route::post('/entidad', [OrganizationSwitchController::class, 'portal'])->name('organization.switch');
    Route::get('/cuenta', Portal\Fees::class)->name('fees');
    Route::get('/actividades', Portal\Activities::class)->name('activities');
    Route::get('/reservas', Portal\Reservations::class)->name('reservations');
    Route::get('/carnet', Portal\Card::class)->name('card');
    Route::get('/mi-plan', Portal\Plans::class)->name('plans')->middleware('gym');
    Route::get('/mis-datos', Portal\Profile::class)->name('profile');
    Route::get('/recibo/{payment}', [ReceiptController::class, 'member'])->name('receipt');
});

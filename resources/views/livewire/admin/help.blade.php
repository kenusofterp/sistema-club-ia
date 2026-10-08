@php
    $sections = [
        'primeros-pasos' => 'Primeros pasos (resumen)',
        'conceptos' => 'Conceptos básicos',
        'ingresar' => '1. Ingresar al sistema',
        'entidad' => '2. Crear la entidad',
        'configuracion' => '3. Configurar el club',
        'categorias' => '4. Categorías de socios',
        'socios' => '5. Cargar socios',
        'cuotas' => '6. Cuotas y cargos',
        'pagos' => '7. Cobrar y emitir recibos',
        'actividades' => '8. Actividades e inscripciones',
        'instalaciones' => '9. Instalaciones y reservas',
        'gimnasio' => '10. Gimnasio: planes',
        'agenda' => '11. Agenda de clases (profesores)',
        'niveles' => '12. Niveles, cobros y la app del teléfono',
        'acceso' => '13. Control de acceso',
        'comunicacion' => '14. Mensajes y avisos',
        'sitio' => '15. Sitio web',
        'usuarios' => '16. Usuarios y roles',
        'auditoria' => '17. Auditoría y reportes',
        'portal' => '18. Portal del socio',
        'automaticos' => '19. Procesos automáticos',
        'ejemplo' => 'Ejemplo completo',
        'faq' => 'Preguntas frecuentes',
    ];
@endphp

<div>
    <x-page-header title="Manual de uso" subtitle="Cómo usar el sistema desde cero, paso a paso, con capturas y ejemplos.">
        <x-slot:actions>
            <button type="button" onclick="window.print()" class="btn-secondary"><x-icon name="printer" class="size-4" /> Imprimir</button>
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn-ghost"><x-icon name="arrow-left" class="size-4" /> Volver al tablero</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        {{-- Índice --}}
        <nav class="lg:sticky lg:top-20 lg:self-start print:hidden">
            <div class="card p-3">
                <p class="px-2 pb-2 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Contenido</p>
                <ul class="space-y-0.5 text-sm">
                    @foreach ($sections as $id => $label)
                        <li><a href="#{{ $id }}" class="block rounded-md px-2 py-1.5 text-slate-600 hover:bg-brand-50 hover:text-brand-700">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
        </nav>

        <article class="manual card min-w-0 p-6 text-[15px] leading-relaxed text-slate-700 sm:p-8">

            {{-- ============================================================ --}}
            <section id="primeros-pasos" class="scroll-mt-20">
                <h2>Primeros pasos (resumen)</h2>
                <p>Si el sistema está recién instalado, este es el orden recomendado. Cada paso tiene su sección con el detalle:</p>
                <ol class="steps">
                    <li><a href="#ingresar">Ingresá</a> con el usuario <strong>super administrador</strong>.</li>
                    <li><a href="#entidad">Creá la entidad</a> (tu club o gimnasio). Se genera con configuración, sitio web y datos base.</li>
                    <li><a href="#configuracion">Revisá la configuración</a>: moneda, día de generación y vencimiento de cuotas, recargo por mora.</li>
                    <li><a href="#categorias">Ajustá las categorías</a> de socios y sus cuotas mensuales.</li>
                    <li><a href="#socios">Cargá los socios</a> (o recibí solicitudes desde la web y aprobalas).</li>
                    <li><a href="#actividades">Cargá actividades</a> con su cuota y horarios, e inscribí socios.</li>
                    <li><a href="#cuotas">Generá las cuotas</a> del mes y <a href="#pagos">registrá los pagos</a>.</li>
                    <li><a href="#usuarios">Creá usuarios</a> para el personal (tesorería, secretaría, recepción…) con su rol.</li>
                    <li>Personalizá el <a href="#sitio">sitio web</a> y habilitá el <a href="#portal">portal del socio</a>.</li>
                </ol>
                <x-manual.tip>
                    En cualquier pantalla tenés el botón <strong>Ayuda</strong> arriba a la derecha para volver a este manual. Hacé clic en cualquier captura para verla en grande.
                </x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="conceptos" class="scroll-mt-20">
                <h2>Conceptos básicos</h2>
                <dl class="glossary">
                    <dt>Entidad</dt>
                    <dd>Cada club o gimnasio que administrás. Tiene sus propios socios, cuotas, pagos, configuración, usuarios y sitio web. Puede ser <strong>Club</strong> (cuota social y actividades), <strong>Gimnasio</strong> (planes de acceso) o <strong>Club con gimnasio</strong> (ambos). Si administrás más de una, se cambia con el selector de la barra superior.</dd>
                    <dt>Socio</dt>
                    <dd>Persona asociada a la entidad. Tiene número de socio, categoría, estado (pendiente, activo, suspendido, baja) y una cuenta corriente con sus cargos y pagos.</dd>
                    <dt>Categoría</dt>
                    <dd>Define la cuota social mensual y el derecho de ingreso (ej.: Infantil, Cadete, Activo, Familiar).</dd>
                    <dt>Cargo / cuota</dt>
                    <dd>Lo que el socio debe pagar: cuota social, cuota de una actividad, plan de gimnasio, reserva o un cargo manual. Puede estar pendiente, con pago parcial, vencida, pagada o anulada.</dd>
                    <dt>Usuario</dt>
                    <dd>Cuenta para ingresar al sistema. El personal tiene un <strong>rol</strong> por entidad (Administrador, Tesorería, Secretaría…). Los socios con acceso al portal también tienen usuario.</dd>
                    <dt>Super administrador</dt>
                    <dd>Acceso total a todas las entidades. Es el único que puede crear entidades.</dd>
                </dl>
            </section>

            {{-- ============================================================ --}}
            <section id="ingresar" class="scroll-mt-20">
                <h2>1. Ingresar al sistema</h2>
                <ol class="steps">
                    <li>Abrí la dirección del sistema y entrá a <strong>/ingresar</strong> (o el botón <em>Ingresar</em> del sitio web).</li>
                    <li>Escribí tu correo y contraseña. Si la olvidaste, usá <em>¿Olvidaste tu contraseña?</em> y te llega un enlace por correo.</li>
                    <li>El personal entra al <strong>panel de administración</strong>; los socios, al <strong>portal del socio</strong>.</li>
                </ol>
                <x-manual.shot src="01-login.png" caption="Pantalla de ingreso." />
            </section>

            {{-- ============================================================ --}}
            <section id="entidad" class="scroll-mt-20">
                <h2>2. Crear la entidad (club o gimnasio)</h2>
                <p>Con el sistema vacío, el super administrador entra directamente a <strong>Administración › Entidades</strong>.</p>
                <x-manual.shot src="02-entidades-vacia.png" caption="Instalación vacía: lo primero es crear la entidad." />
                <ol class="steps">
                    <li>Hacé clic en <strong>Crear la primera entidad</strong> (o <em>Nueva entidad</em>).</li>
                    <li>Completá el <strong>nombre</strong>; el <strong>identificador</strong> se arma solo y se usa como subdominio (ej.: <code>atletico.tusistema.com</code>).</li>
                    <li>Elegí el <strong>tipo</strong>: Club, Gimnasio o Club con gimnasio. Define qué módulos se ven.</li>
                    <li>Opcional: un <strong>dominio propio</strong> (ej.: <code>miclub.com</code>) que apunte a este servidor.</li>
                    <li>Guardá. La primera entidad queda como <em>principal</em> y pasás directo a su tablero.</li>
                </ol>
                <x-manual.shot src="03-nueva-entidad.png" caption="Formulario de nueva entidad." />
                <x-manual.shot src="03b-tablero-inicial.png" caption="Entidad recién creada: el tablero invita a seguir los primeros pasos." />
                <x-manual.tip type="example">
                    Nombre: <strong>Club Atlético Ejemplo</strong> · Tipo: <strong>Club</strong> · Identificador: <code>club-atletico-ejemplo</code>. Al guardar se crean las categorías Infantil, Cadete, Activo, Familiar y Vitalicio, algunas actividades de muestra y el sitio web inicial. Todo se puede editar o borrar.
                </x-manual.tip>
                <p>Cuando administrás varias entidades, la pantalla de Entidades muestra un resumen de cada una (socios activos, cobrado en el mes, deuda vencida) y el botón <strong>Administrar</strong> para cambiar a ella.</p>
                <x-manual.shot src="04-entidades.png" caption="Varias entidades administradas desde la misma cuenta." />
            </section>

            {{-- ============================================================ --}}
            <section id="configuracion" class="scroll-mt-20">
                <h2>3. Configurar el club</h2>
                <p>En <strong>Administración › Configuración del club</strong> se definen las reglas del día a día. Lo más importante para empezar:</p>
                <ul>
                    <li><strong>Moneda:</strong> símbolo y cantidad de decimales.</li>
                    <li><strong>Día de generación</strong> de las cuotas (ej.: 1) y <strong>día de vencimiento</strong> (ej.: 10).</li>
                    <li><strong>Recargo por mora (%)</strong>: se aplica una sola vez cuando el cargo vence. 0 lo desactiva.</li>
                    <li><strong>Cuotas vencidas que bloquean el ingreso</strong> y si se exige no tener deuda para inscribirse o reservar.</li>
                    <li><strong>Instrucciones de pago</strong> que ven los socios en el portal (alias, CBU, horarios de secretaría).</li>
                </ul>
                <x-manual.shot src="05-configuracion.png" caption="Configuración del club: cuotas, recargos y reglas." />
                <x-manual.tip type="example">
                    Generación el día <strong>1</strong>, vencimiento el día <strong>10</strong> y recargo del <strong>10 %</strong>: una cuota de $ 18.000 generada el 1/10 que no se paga hasta el 10/10 pasa a <em>Vencida</em> el 11/10 y queda en $ 19.800.
                </x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="categorias" class="scroll-mt-20">
                <h2>4. Categorías de socios</h2>
                <p>En <strong>Socios › Categorías</strong> definís cuánto paga cada tipo de socio por mes y el derecho de ingreso (se cobra una sola vez al dar de alta). Los rangos de edad son orientativos para elegir la categoría.</p>
                <x-manual.shot src="06-categorias.png" caption="Categorías con su cuota mensual y cantidad de socios activos." />
                <x-manual.tip>
                    Si cambiás la cuota de una categoría, el nuevo importe se usa a partir de la próxima generación de cuotas. Las cuotas ya generadas no cambian.
                </x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="socios" class="scroll-mt-20">
                <h2>5. Cargar socios</h2>
                <h3>Alta manual</h3>
                <ol class="steps">
                    <li>Andá a <strong>Socios</strong> y hacé clic en <strong>Nuevo socio</strong> (también está en el tablero).</li>
                    <li>Completá los datos personales, la <strong>categoría</strong> y la <strong>fecha de ingreso</strong>. La foto se usa en el carnet digital.</li>
                    <li>Cargá el <strong>correo electrónico</strong>: es necesario para el portal y para recibir recibos.</li>
                    <li>Si es parte de una familia, indicá el <strong>socio titular</strong> y el parentesco.</li>
                    <li>Guardá. El sistema asigna el número de socio automáticamente.</li>
                </ol>
                <x-manual.shot src="07-socio-nuevo.png" caption="Formulario de alta de socio." />
                <x-manual.tip type="example">
                    <strong>Juan Pérez</strong>, DNI 30.123.456, nacido el 15/03/1990, categoría <strong>Activo</strong>, ingreso hoy, correo <code>juan.perez@correo.com</code>.
                </x-manual.tip>

                <h3>Solicitudes desde la web</h3>
                <p>Si está habilitado el formulario <em>Asociate</em> del sitio, las personas pueden pedir el alta. Llegan como socios <strong>Pendientes de aprobación</strong> (el menú Socios muestra un contador). Abrí la ficha y elegí <strong>Aprobar</strong> o <strong>Rechazar</strong>.</p>

                <h3>Listado y ficha del socio</h3>
                <p>El listado permite buscar por nombre, documento o número y filtrar por estado y categoría. La ficha concentra todo: datos, saldo adeudado, cargos, pagos, actividades y acciones.</p>
                <x-manual.shot src="08-socios.png" caption="Listado de socios con búsqueda y filtros." />
                <x-manual.shot src="09-socio-ficha.png" caption="Ficha del socio: saldo, cuenta corriente y acciones." />
                <p>Desde la ficha podés:</p>
                <ul>
                    <li><strong>Registrar pago</strong> o agregar un <strong>cargo manual</strong> (ej.: una camiseta, un torneo).</li>
                    <li><strong>Inscribirlo</strong> en actividades.</li>
                    <li><strong>Habilitar acceso al portal</strong>: le llega un correo para crear su contraseña.</li>
                    <li><strong>Suspender</strong>, <strong>dar de baja</strong> o <strong>reactivar</strong>. Un socio suspendido o dado de baja no genera cuotas ni puede ingresar.</li>
                    <li>Ver o imprimir el <strong>carnet</strong> con código QR.</li>
                </ul>
            </section>

            {{-- ============================================================ --}}
            <section id="cuotas" class="scroll-mt-20">
                <h2>6. Cuotas y cargos</h2>
                <p>En <strong>Tesorería › Cuotas y cargos</strong> ves todo lo que deben los socios, con filtros por estado, período y socio.</p>
                <x-manual.shot src="10-cuotas.png" caption="Cuotas y cargos con su estado." />
                <h3>Generar las cuotas del mes</h3>
                <ol class="steps">
                    <li>Hacé clic en <strong>Generar cuotas</strong>.</li>
                    <li>Elegí el <strong>período</strong> (mes y año) y confirmá.</li>
                    <li>Se crea la cuota social de cada socio activo según su categoría, más la cuota de cada actividad en la que esté inscripto.</li>
                </ol>
                <x-manual.shot src="11-generar-cuotas.png" caption="Generación de cuotas de un período." />
                <x-manual.tip>
                    Es seguro repetirlo: no duplica cargos ya generados. Además, el sistema lo hace solo el día configurado de cada mes (ver <a href="#automaticos">procesos automáticos</a>).
                </x-manual.tip>
                <p><strong>Procesar vencimientos</strong> marca como vencidos los cargos impagos y aplica el recargo en el momento (normalmente lo hace el sistema solo cada noche). Un cargo sin pagos se puede <strong>anular</strong> indicando el motivo (queda registrado en la auditoría). Con el botón de billetes vas directo a cobrarlo.</p>
            </section>

            {{-- ============================================================ --}}
            <section id="pagos" class="scroll-mt-20">
                <h2>7. Cobrar y emitir recibos</h2>
                <ol class="steps">
                    <li>Andá a <strong>Tesorería › Pagos › Registrar pago</strong> (o desde el tablero o la ficha del socio).</li>
                    <li>Buscá al socio por nombre, documento o número.</li>
                    <li>Marcá los <strong>cargos pendientes</strong> que paga. El importe se completa solo; podés bajarlo para registrar un <strong>pago parcial</strong>.</li>
                    <li>Elegí el <strong>medio de pago</strong> (efectivo, transferencia, débito, crédito u otro), la fecha y, si corresponde, el número de operación.</li>
                    <li>Guardá y usá <strong>Imprimir recibo</strong>. Si el socio tiene correo, también recibe el comprobante.</li>
                </ol>
                <x-manual.shot src="12-registrar-pago.png" caption="Registro de un pago imputado a cargos pendientes." />
                <x-manual.tip type="example">
                    Juan debe la cuota social de octubre ($ 18.000) y Fútbol ($ 8.000). Paga $ 26.000 por transferencia: se marcan los dos cargos, medio <em>Transferencia</em>, referencia <code>OP-458812</code>. Si paga solo $ 20.000, se cancela primero el cargo más antiguo y el otro queda con <em>Pago parcial</em>.
                </x-manual.tip>
                <p>El listado de <strong>Pagos</strong> permite filtrar por fecha y medio, reimprimir recibos, exportar a planilla y <strong>anular</strong> un pago mal cargado (los cargos vuelven a quedar pendientes).</p>
                <x-manual.shot src="13-pagos.png" caption="Listado de pagos." />
            </section>

            {{-- ============================================================ --}}
            <section id="actividades" class="scroll-mt-20">
                <h2>8. Actividades e inscripciones</h2>
                <p>En <strong>Actividades</strong> cargás las disciplinas (fútbol, natación, yoga…) con su cuota mensual, cupo, rango de edad, profesor, horarios e imagen. Si la marcás como visible, aparece en el sitio web.</p>
                <x-manual.shot src="14-actividades.png" caption="Listado de actividades." />
                <x-manual.shot src="15-actividad-form.png" caption="Formulario de actividad con horarios." />
                <h3>Inscribir a un socio</h3>
                <ol class="steps">
                    <li>En <strong>Actividades › Inscripciones</strong>, hacé clic en <strong>Nueva inscripción</strong> (o desde la ficha del socio).</li>
                    <li>Elegí el socio y la actividad. El sistema valida edad, cupo y deuda según la configuración.</li>
                    <li>Si está configurado, se cobra el mes en curso al inscribirse; los meses siguientes se generan con las cuotas.</li>
                </ol>
                <x-manual.shot src="16-inscripciones.png" caption="Inscripciones activas." />
                <x-manual.tip type="example">
                    Actividad <strong>Fútbol infantil</strong>, cuota $ 8.000, cupo 25, edades 6 a 12, martes y jueves 18:00 a 19:30 en Cancha 1. Si un socio de 15 años intenta inscribirse, el sistema lo rechaza por edad.
                </x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="instalaciones" class="scroll-mt-20">
                <h2>9. Instalaciones y reservas</h2>
                <p>En <strong>Instalaciones</strong> cargás canchas, quinchos, salones, etc., con su horario, duración del turno, turnos máximos por reserva y tarifa por hora (0 = sin cargo).</p>
                <x-manual.shot src="17-instalaciones.png" caption="Instalaciones con horario y tarifa." />
                <p>En <strong>Reservas</strong> ves la grilla del día por instalación. Hacé clic en un horario <em>Libre</em> o en <strong>Nueva reserva</strong>, elegí socio y turnos y confirmá. Si tiene tarifa, se genera el cargo en la cuenta del socio. Los socios también pueden reservar desde el portal.</p>
                <x-manual.shot src="18-reservas.png" caption="Grilla diaria de reservas." />
            </section>

            {{-- ============================================================ --}}
            <section id="gimnasio" class="scroll-mt-20">
                <h2>10. Gimnasio: planes y membresías</h2>
                <p>Disponible en entidades de tipo <strong>Gimnasio</strong> o <strong>Club con gimnasio</strong> (menú <em>Gimnasio</em>).</p>
                <h3>Planes y precios</h3>
                <p>Cada plan define precio, duración (días o meses), <strong>tipo de acceso</strong> (pase libre, solo clases o ambos), límite de visitas, clases incluidas y franjas horarias permitidas.</p>
                <x-manual.shot src="19-gym-planes.png" caption="Planes del gimnasio." />
                <x-manual.tip type="example">
                    <strong>Pase libre mañana</strong>: $ 25.000 por 1 mes, pase libre, solo de lunes a viernes de 7 a 12 h. <strong>8 clases</strong>: $ 20.000 por 1 mes, solo clases, límite 8 visitas.
                </x-manual.tip>
                <h3>Asignar un plan a un socio</h3>
                <ol class="steps">
                    <li>En <strong>Gimnasio › Planes de socios</strong>, hacé clic en <strong>Asignar plan</strong>.</li>
                    <li>Elegí socio, plan y fecha de comienzo. Marcá <em>Renovar automáticamente</em> si corresponde.</li>
                    <li>Se genera el cargo; el plan se activa al registrarse el pago (según la configuración).</li>
                </ol>
                <x-manual.shot src="20-gym-suscripciones.png" caption="Planes vigentes, pendientes y por vencer." />
            </section>

            {{-- ============================================================ --}}
            <section id="agenda" class="scroll-mt-20">
                <h2>11. Agenda de clases (profesores)</h2>
                <p>Para profesores que dan clases individuales o grupales (por ejemplo, tenis) en una o varias entidades. Cada profesor entra con su usuario y maneja <strong>su</strong> agenda, sus packs y sus cobros. Está disponible en cualquier tipo de entidad.</p>
                <h3>Preparar al profesor</h3>
                <ol class="steps">
                    <li>En <strong>Administración › Usuarios</strong>, creá el usuario y dale el rol <strong>profesor</strong>.</li>
                    <li>En el mismo formulario, marcá las <strong>sedes donde da clases</strong> (las instalaciones de esa entidad).</li>
                    <li>Si también da clases en otra entidad, cambiá de entidad y repetí: rol <em>profesor</em> y sus sedes allí.</li>
                </ol>
                <h3>Ver la agenda</h3>
                <p>En <strong>Agenda de clases › Agenda</strong> elegís la vista <strong>Día</strong>, <strong>Semana</strong> o <strong>Mes</strong> y qué querés ver:</p>
                <ul>
                    <li><strong>Todos mis clubes</strong>: una agenda única con las clases de todas las entidades donde trabajás, con un color por club.</li>
                    <li><strong>Un club puntual</strong>: solo las clases de esa entidad, con un color por sede.</li>
                </ul>
                <p>El sistema recuerda la vista que elegiste: la próxima vez que entres la vas a encontrar igual. Arriba se muestran los totales del período: clases, horas ocupadas, clases dadas y alumnos. Quien tiene el permiso <em>Ver y gestionar la agenda de todos los profesores</em> (administración, secretaría) puede además filtrar por profesor.</p>
                <h3>Programar una clase</h3>
                <ol class="steps">
                    <li>Hacé clic en un horario libre de la grilla o en <strong>Nueva clase</strong>.</li>
                    <li>Elegí club y sede, fecha y horario. Si la sede ya tiene una reserva u otra clase en ese horario, el sistema te avisa.</li>
                    <li>Buscá a los alumnos por nombre o documento. La búsqueda es en <strong>toda la base de personas del sistema</strong>: si el alumno es socio de otra entidad, se lo suma a esta automáticamente, sin cobrarle derecho de ingreso. Con varios alumnos, la clase es grupal.</li>
                    <li>Para una clase fija, marcá <strong>Repetir semanalmente</strong>, elegí los días y hasta qué fecha.</li>
                </ol>
                <x-manual.tip>Un profesor no puede tener dos clases a la misma hora, aunque sean en clubes distintos: el sistema lo impide e indica dónde está la otra clase.</x-manual.tip>
                <h3>Tomar asistencia y marcar la clase como dada</h3>
                <p>Hacé clic en la clase, marcá a cada alumno como <em>Presente</em> o <em>Ausente</em> y luego <strong>Marcar como dada</strong>. A cada presente:</p>
                <ul>
                    <li>si tiene un <strong>pack vigente</strong> con ese profesor y le quedan clases, se le descuenta una;</li>
                    <li>si no tiene pack o ya lo usó, se le cobra la <strong>clase suelta</strong>.</li>
                </ul>
                <p>Los ausentes no consumen ni pagan. Si te equivocaste, usá <strong>Reabrir</strong>: la clase vuelve a quedar programada y se anulan las clases sueltas que todavía no se cobraron.</p>
                <h3>Precio de la clase suelta</h3>
                <p>Se toma, en este orden: el precio cargado en la clase; el precio propio del profesor; el de la entidad (<strong>Configuración › Agenda de clases</strong>).</p>
                <h3>Packs de clases</h3>
                <p>En <strong>Agenda de clases › Packs</strong> el profesor crea sus planes, por ejemplo <em>4 clases por mes</em>, <em>8 clases por mes</em> o <em>Clase de prueba</em>, con su precio, vigencia y cantidad de clases (por semana, por mes o en todo el pack). Con <strong>Asignar a alumno</strong> se lo vende: queda activo de inmediato y el cargo va a la cuenta del alumno. Cada pack es de una entidad y se consume con las clases que el profesor da allí.</p>
                <h3>Mis cobros</h3>
                <p>Los packs y las clases sueltas quedan <strong>a nombre del profesor</strong>, separados de las cuotas del club. En <strong>Agenda de clases › Mis cobros</strong> el profesor ve el saldo de cada alumno en todas sus entidades y registra los cobros. Un mismo pago no puede mezclar cargos del club con cargos de un profesor. Las deudas con un profesor no bloquean el ingreso al club, las reservas ni las inscripciones.</p>
                <x-manual.tip type="example">
                    Martín da tenis en el <strong>Club Norte</strong> (lunes y miércoles) y en el <strong>Club Sur</strong> (martes). Programa una serie <em>lunes y miércoles 18 a 19 h</em> en Club Norte con Lucía y Pedro, y una clase individual los martes en Club Sur con Ana. Lucía compró el pack <em>8 clases por mes</em> ($ 40.000); Pedro paga cada clase ($ 6.000). En la vista <em>Todos mis clubes</em> Martín ve su semana completa; al marcar la clase del lunes como dada, a Lucía le quedan 7 clases y a Pedro se le genera un cargo de $ 6.000 que cobra desde <em>Mis cobros</em>.
                </x-manual.tip>
                <h3>Para el alumno</h3>
                <p>En el portal, la sección <strong>Mis clases</strong> muestra sus próximas clases, el historial con su asistencia y las clases que le quedan del pack.</p>
            </section>

            {{-- ============================================================ --}}
            <section id="niveles" class="scroll-mt-20">
                <h2>12. Niveles, cobros y la app del teléfono</h2>
                <p>Pensado para academias y escuelas que agrupan a sus alumnos por <strong>nivel</strong> (por ejemplo Juvenil A, Juvenil B…), con uno o varios profesores por nivel y una cuota mensual. Todo es configurable, así que sirve tanto para una coordinadora con varios profesores como para un profesor que trabaja solo.</p>

                <h3>Configuración (Configuración del club)</h3>
                <ul>
                    <li><strong>Agenda de clases › Cómo se llaman las actividades</strong>: por ejemplo <em>Nivel / Niveles</em>. Ese nombre se usa en el menú, el portal y las pantallas.</li>
                    <li><strong>Cobros › Los socios pueden informar pagos…</strong>: habilita subir el comprobante de transferencia desde la app.</li>
                    <li><strong>Cobros › Acreditar automáticamente los comprobantes</strong>: si está activado, el pago se registra al instante y tesorería puede anularlo; si no, queda <em>en revisión</em>.</li>
                    <li><strong>Cobros › Datos para transferir</strong>: CBU, alias y titular que ve el alumno al informar el pago.</li>
                    <li><strong>Cobros › Los profesores pueden cobrar en efectivo</strong> y <strong>El efectivo se rinde</strong>: si el profesor es el dueño, desactivá la rendición.</li>
                </ul>

                <h3>Armar los niveles</h3>
                <ol class="steps">
                    <li>En <strong>Niveles</strong> (antes <em>Actividades</em>), creá cada nivel con su cuota mensual, horarios y sede.</li>
                    <li>Elegí el <strong>profesor responsable</strong> y, si hay más, marcá los <strong>otros profesores a cargo</strong>: todos pueden tomar asistencia, suspender la clase y cobrar a esas alumnas.</li>
                    <li>Inscribí a las alumnas en <strong>Inscripciones</strong>. Para una beca o descuento, tocá la cuota de la inscripción y cargá el importe individual (0 = beca completa).</li>
                </ol>
                <p>El sistema genera las clases de las próximas semanas a partir de los horarios. Se ven en la <strong>Agenda</strong> y en <strong>Clases de hoy</strong>.</p>

                <h3>Roles</h3>
                <ul>
                    <li><strong>Coordinación</strong>: ve todos los niveles y la agenda completa, suspende clases de uno o todos los niveles, revisa comprobantes y confirma rendiciones.</li>
                    <li><strong>Profesor/a</strong>: sus niveles y clases, asistencia, suspender sus clases, cobrar en efectivo a sus alumnas y rendir.</li>
                </ul>

                <h3>El profesor en el celular</h3>
                <p>Al ingresar, el profesor entra directo a <strong>Clases de hoy</strong>, con una barra inferior: <em>Hoy, Agenda, Cobrar, Rendir</em>.</p>
                <ul>
                    <li><strong>Asistencia</strong>: tocá la clase y marcá <em>P</em> o <em>A</em> para cada alumna. Quien avisó que falta aparece como ausente, con el motivo. Después, <strong>Guardar asistencia</strong>.</li>
                    <li><strong>Suspender</strong>: avisa a las alumnas con una notificación y por correo. Coordinación puede usar <em>No hay clase hoy para ningún grupo</em> (por ejemplo, por lluvia o un feriado).</li>
                    <li><strong>Cobrar</strong>: buscá a la alumna, marcá las cuotas y registrá el efectivo; se emite el recibo.</li>
                    <li><strong>Efectivo a rendir</strong>: muestra lo cobrado y no rendido. Con <strong>Rendir</strong> se envía a coordinación, que lo confirma en <strong>Tesorería › Rendiciones</strong> al recibir el dinero. Un pago rendido no se puede anular.</li>
                </ul>

                <h3>La alumna en el celular</h3>
                <ul>
                    <li>Se instala la app desde el navegador (<em>Agregar a pantalla de inicio</em>) y se activan las <strong>notificaciones</strong> desde el aviso del inicio. En iPhone, primero hay que instalar la app.</li>
                    <li><strong>Mis clases › No voy a esta clase</strong>: avisa al profesor, con un motivo opcional. Se puede deshacer hasta que empiece la clase.</li>
                    <li><strong>Mi cuenta › Informar un pago</strong>: elegí las cuotas, sacá una foto del comprobante (o subí el PDF) y enviá. Llega una notificación cuando se acredita o si se rechaza, con el motivo.</li>
                </ul>
                <x-manual.tip type="example">
                    Laura coordina tres niveles con dos profesores. El martes llueve: desde <em>Clases de hoy</em> toca <em>No hay clase hoy para ningún grupo</em> y a todas las alumnas les llega la notificación. Sofía, de Juvenil B, transfiere la cuota y sube la foto del comprobante; Laura lo ve en <em>Comprobantes</em> y lo acredita. Otra alumna le paga en efectivo al profe Diego, que lo registra en <em>Cobrar</em> y a fin de semana lo rinde; Laura confirma la rendición al recibir el dinero.
                </x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="acceso" class="scroll-mt-20">
                <h2>13. Control de acceso</h2>
                <p>En <strong>Socios › Control de acceso</strong> el personal de recepción verifica si una persona puede ingresar. Escaneá el QR del carnet (con un lector o la cámara) o escribí el número de socio o documento y presioná <strong>Verificar</strong>.</p>
                <p>El sistema responde <strong>Permitido</strong> o <strong>Denegado</strong> con el motivo: socio suspendido, deuda vencida por encima del límite, plan vencido, fuera de la franja horaria, sin visitas disponibles, etc. Cada intento queda registrado.</p>
                <x-manual.shot src="21-control-acceso.png" caption="Verificación de ingreso con su resultado." />
            </section>

            {{-- ============================================================ --}}
            <section id="comunicacion" class="scroll-mt-20">
                <h2>14. Mensajes y avisos</h2>
                <ul>
                    <li><strong>Mensajes:</strong> lo que llega desde el formulario de contacto del sitio. El menú muestra cuántos hay sin leer.</li>
                    <li><strong>Avisos a socios:</strong> comunicados que se muestran en el portal del socio (ej.: “La pileta abre el 1/12”). Se pueden programar con fecha de publicación y vencimiento; sin fecha quedan como borrador.</li>
                </ul>
                <x-manual.shot src="22-avisos.png" caption="Avisos publicados en el portal." />
            </section>

            {{-- ============================================================ --}}
            <section id="sitio" class="scroll-mt-20">
                <h2>15. Sitio web</h2>
                <p>Cada entidad tiene su sitio público. Desde el menú <strong>Sitio web</strong> se edita sin conocimientos técnicos:</p>
                <ul>
                    <li><strong>Identidad y contacto:</strong> nombre, logo, colores, teléfono, dirección, redes sociales y mapa.</li>
                    <li><strong>Portada:</strong> las imágenes grandes (carrusel) de la página de inicio.</li>
                    <li><strong>Secciones:</strong> qué bloques muestra la página de inicio y en qué orden.</li>
                    <li><strong>Noticias</strong> y <strong>Páginas</strong> (ej.: Historia, Estatuto, Reglamento).</li>
                </ul>
                <x-manual.shot src="23-identidad.png" caption="Identidad del sitio: logo, colores y datos de contacto." />
                <x-manual.shot src="24-sitio-web.png" caption="Así se ve el sitio público." />
                <x-manual.tip>Usá el botón <strong>Ver sitio</strong> de la barra superior para ver los cambios.</x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="usuarios" class="scroll-mt-20">
                <h2>16. Usuarios y roles</h2>
                <h3>Crear un usuario del personal</h3>
                <ol class="steps">
                    <li>En <strong>Administración › Usuarios</strong>, hacé clic en <strong>Agregar usuario</strong>.</li>
                    <li>Completá nombre y correo. Podés definir la contraseña o enviarle un correo para que la cree.</li>
                    <li>Elegí sus <strong>roles en esta entidad</strong>. Para darle acceso a otra entidad, cambiá de entidad y asignale roles allí.</li>
                </ol>
                <x-manual.shot src="25-usuarios.png" caption="Usuarios con sus roles por entidad." />
                <h3>Roles incluidos</h3>
                <div class="overflow-x-auto">
                    <table class="manual-table">
                        <thead><tr><th>Rol</th><th>Pensado para</th></tr></thead>
                        <tbody>
                            <tr><td>Administrador</td><td>Gestión completa de la entidad.</td></tr>
                            <tr><td>Tesorería</td><td>Cuotas, pagos, anulaciones y reportes.</td></tr>
                            <tr><td>Secretaría</td><td>Socios, inscripciones, cobros, reservas, avisos y mensajes.</td></tr>
                            <tr><td>Recepción</td><td>Control de acceso, reservas, planes y cobros en mostrador.</td></tr>
                            <tr><td>Coordinación</td><td>Niveles, inscripciones, agenda de todos los profesores, comprobantes y rendiciones.</td></tr>
                            <tr><td>Profesor/a</td><td>Ver actividades y socios; su agenda, asistencia, packs y cobros (y efectivo de sus alumnos).</td></tr>
                            <tr><td>Comunicación</td><td>Sitio web, mensajes y avisos.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>En <strong>Roles y permisos</strong> podés crear roles propios (ej.: “coordinador”) y elegir exactamente qué puede hacer cada uno.</p>
                <x-manual.shot src="26-roles.png" caption="Edición de permisos por rol." />
            </section>

            {{-- ============================================================ --}}
            <section id="auditoria" class="scroll-mt-20">
                <h2>17. Auditoría y reportes</h2>
                <p><strong>Auditoría</strong> registra quién creó, modificó o anuló cada dato y cuándo, con el detalle de los cambios. Útil para controlar anulaciones de pagos o cambios de cuotas.</p>
                <x-manual.shot src="27-auditoria.png" caption="Registro de auditoría." />
                <p>Los listados de <strong>Socios</strong>, <strong>Pagos</strong> y <strong>Cuotas</strong> tienen botón de <strong>Exportar</strong> a planilla (respetando los filtros aplicados). El <strong>Tablero</strong> resume socios activos, recaudación del mes, deuda vencida, recaudación de los últimos 12 meses y reservas del día.</p>
                <x-manual.shot src="28-tablero.png" caption="Tablero principal con los indicadores de la entidad." />
            </section>

            {{-- ============================================================ --}}
            <section id="portal" class="scroll-mt-20">
                <h2>18. Portal del socio</h2>
                <p>Cuando habilitás el acceso al portal desde la ficha del socio, recibe un correo para crear su contraseña. Desde su celular o computadora puede:</p>
                <ul>
                    <li>Ver su <strong>estado de cuenta</strong>, cargos pendientes, pagos y descargar recibos.</li>
                    <li>Mostrar su <strong>carnet digital</strong> con QR para ingresar.</li>
                    <li><strong>Inscribirse</strong> en actividades y <strong>reservar</strong> instalaciones.</li>
                    <li>Contratar o renovar <strong>planes</strong> del gimnasio.</li>
                    <li>Leer los <strong>avisos</strong> y actualizar sus datos.</li>
                </ul>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-manual.shot src="29-portal-inicio.png" caption="Inicio del portal." />
                    <x-manual.shot src="30-portal-carnet.png" caption="Carnet digital con QR." />
                </div>
                <x-manual.tip>
                    Si una persona es socia de varias entidades, usa una sola cuenta y elige la entidad desde el portal. El portal se puede instalar en el celular como una app (opción “Agregar a pantalla de inicio”).
                </x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="automaticos" class="scroll-mt-20">
                <h2>19. Procesos automáticos</h2>
                <p>Con el programador de tareas del servidor activo, el sistema hace solo:</p>
                <div class="overflow-x-auto">
                    <table class="manual-table">
                        <thead><tr><th>Cuándo</th><th>Qué hace</th></tr></thead>
                        <tbody>
                            <tr><td>Todos los días, 00:30</td><td>Marca como vencidos los cargos impagos y aplica el recargo por mora.</td></tr>
                            <tr><td>Todos los días, 00:45</td><td>Vence planes de gimnasio, genera renovaciones automáticas y cancela altas impagas.</td></tr>
                            <tr><td>Todos los días, 01:00</td><td>Genera las cuotas del mes en el día configurado de cada entidad.</td></tr>
                            <tr><td>Todos los días, 09:00</td><td>Envía recordatorios de vencimiento por correo (si están habilitados).</td></tr>
                        </tbody>
                    </table>
                </div>
                <x-manual.tip type="warning">
                    Requiere en el servidor <code>php artisan schedule:run</code> cada minuto (cron) y <code>php artisan queue:work</code> para los correos. Mientras tanto, podés usar <em>Generar cuotas</em> y <em>Procesar vencimientos</em> a mano desde <em>Cuotas y cargos</em>.
                </x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="ejemplo" class="scroll-mt-20">
                <h2>Ejemplo completo: un mes en el Club Atlético Ejemplo</h2>
                <p>Recorrido para simular el sistema desde cero (unos 15 minutos):</p>
                <ol class="steps">
                    <li><strong>Entidad.</strong> Como super administrador, creá “Club Atlético Ejemplo”, tipo <em>Club</em>.</li>
                    <li><strong>Configuración.</strong> Moneda “$”, 2 decimales, generación día 1, vencimiento día 10, recargo 10 %.</li>
                    <li><strong>Categorías.</strong> Dejá <em>Activo</em> en $ 18.000 y <em>Cadete</em> en $ 11.000.</li>
                    <li><strong>Actividad.</strong> Creá “Fútbol” con cuota $ 8.000, cupo 30, martes y jueves de 19 a 20:30.</li>
                    <li><strong>Socios.</strong> Cargá a Juan Pérez (Activo) y a su hijo Tomás Pérez (Cadete, titular: Juan, parentesco: hijo).</li>
                    <li><strong>Inscripción.</strong> Inscribí a Tomás en Fútbol. Si está activo el cobro al inscribirse, se le genera la cuota del mes.</li>
                    <li><strong>Cuotas.</strong> En Cuotas y cargos, generá el período actual: Juan $ 18.000; Tomás $ 11.000 de cuota social.</li>
                    <li><strong>Cobro.</strong> Registrá un pago de Juan en efectivo por su cuota e imprimí el recibo. El tablero suma la recaudación.</li>
                    <li><strong>Deuda.</strong> Dejá impagas las cuotas de Tomás: al pasar el vencimiento quedan <em>Vencidas</em> con recargo y aparecen en <em>Deuda vencida</em> del tablero.</li>
                    <li><strong>Acceso.</strong> En Control de acceso, verificá el número de socio de Juan (permitido) y luego el de Tomás con deuda (según el límite configurado).</li>
                    <li><strong>Personal.</strong> Creá un usuario con rol <em>Tesorería</em>, cerrá sesión e ingresá con él: solo verá los módulos de su rol.</li>
                    <li><strong>Portal.</strong> Habilitá el portal de Juan, creá su contraseña desde el correo y mirá su carnet y su cuenta.</li>
                </ol>
                <x-manual.tip>
                    En un entorno de prueba los correos no se envían: quedan en el registro del sistema (<code>storage/logs</code>) según la configuración de correo del servidor.
                </x-manual.tip>
            </section>

            {{-- ============================================================ --}}
            <section id="faq" class="scroll-mt-20">
                <h2>Preguntas frecuentes</h2>
                <dl class="glossary">
                    <dt>Generé las cuotas dos veces, ¿se duplicaron?</dt>
                    <dd>No. La generación es idempotente: solo crea lo que falta.</dd>
                    <dt>Cobré a la persona equivocada.</dt>
                    <dd>Anulá el pago desde el listado de Pagos (queda registrado en auditoría) y registralo de nuevo al socio correcto.</dd>
                    <dt>Un socio no puede inscribirse o reservar.</dt>
                    <dd>Revisá si tiene deuda vencida y si la configuración exige no tener deuda. También se validan edad y cupo.</dd>
                    <dt>No veo un menú que otro usuario sí ve.</dt>
                    <dd>El menú depende de los permisos de tu rol en la entidad actual. Pedile a un administrador que los revise en Roles y permisos.</dd>
                    <dt>No veo el menú Gimnasio.</dt>
                    <dd>Solo aparece en entidades de tipo Gimnasio o Club con gimnasio. Lo cambia el super administrador en Entidades.</dd>
                    <dt>¿Puedo borrar un socio?</dt>
                    <dd>Se recomienda darlo de baja para conservar su historial. Solo se puede eliminar si no tiene movimientos.</dd>
                </dl>
            </section>
        </article>
    </div>
</div>

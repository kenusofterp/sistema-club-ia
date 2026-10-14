@php
    $act = mb_strtolower(activity_label());
    $acts = mb_strtolower(activity_label(true));
@endphp

{{-- ============================================================ --}}
<section id="primeros-pasos" class="chapter">
    <h2><span class="num">▶</span> Primeros pasos: un club</h2>
    <p class="lead">Si el sistema está recién instalado y lo vas a usar para un club o gimnasio con socios, este es el orden recomendado. Cada paso tiene su sección con el detalle.</p>
    <ol class="steps">
        <li><a href="#ingresar">Ingresá</a> con el usuario <strong>super administrador</strong>.</li>
        <li><a href="#entidad">Creá la entidad</a> (tu club o gimnasio). Se genera con configuración, sitio web y datos base.</li>
        <li><a href="#configuracion">Revisá la configuración</a>: moneda, día de generación y vencimiento de cuotas, recargo por mora.</li>
        <li><a href="#categorias">Ajustá las categorías</a> de socios y sus cuotas mensuales.</li>
        <li><a href="#socios">Cargá los socios</a> (con foto desde la cámara) o recibí solicitudes desde la web y aprobalas.</li>
        <li><a href="#actividades">Cargá {{ $acts }}</a> con su cuota y horarios, e inscribí socios.</li>
        <li><a href="#cuotas">Generá las cuotas</a> del mes y <a href="#pagos">registrá los pagos</a>. Lo que pagás (alquiler, luz…) va en <a href="#gastos">Gastos</a>.</li>
        <li><a href="#usuarios">Creá usuarios</a> para el personal (tesorería, secretaría, recepción, profesores…) con su rol.</li>
        <li>Personalizá el <a href="#sitio">sitio web</a> y habilitá el <a href="#portal">portal del socio</a>.</li>
    </ol>
    <div class="guide-cards">
        <a href="#primeros-pasos-profe" class="guide-card"><i>🎾</i><b>¿Sos profe y alquilás canchas?</b><span>Guía paso a paso para cargar las canchas que alquilás, registrar lo que pagás y armar la agenda de tus alumnos.</span></a>
        <a href="#niveles" class="guide-card"><i>👥</i><b>¿Academia por niveles?</b><span>Niveles con cuota mensual, asistencia desde el celular, comprobantes de transferencia y rendiciones.</span></a>
        <a href="#faq" class="guide-card"><i>❓</i><b>¿Algo no funciona?</b><span>Preguntas frecuentes: cuotas duplicadas, la cámara no abre, un menú que no aparece…</span></a>
    </div>
    <x-manual.tip>
        En cualquier pantalla tenés <strong>Manual de uso</strong> en el menú. Hacé clic en cualquier captura para verla en grande. Con el botón <strong>Imprimir / PDF</strong> sale una versión con portada e índice, lista para guardar en PDF.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="primeros-pasos-profe" class="chapter">
    <h2><span class="num">▶</span> Primeros pasos: profe que alquila canchas</h2>
    <p class="lead">Para un profesor (o escuela) que da clases en las canchas de un club o complejo que <strong>alquila por su cuenta</strong>: tiene que alquilarlas, pagarlas, registrar que las pagó y organizar la agenda de clases de sus alumnos, que le pagan a él.</p>
    <div class="summary">
        <p>La idea</p>
        <ul>
            <li>Tu <strong>entidad</strong> es tu escuela (no el club donde alquilás).</li>
            <li>Cada cancha que alquilás se carga como una <strong>instalación</strong> (sede) de tu escuela.</li>
            <li>Lo que le pagás al club va a <strong>Gastos › Alquiler</strong>, cancha por cancha y mes por mes.</li>
            <li>Tus clases se programan en la <strong>Agenda</strong> en esas canchas; tus alumnos te pagan cuotas, packs o clases sueltas.</li>
            <li>El <strong>Tablero</strong> te muestra cuánto cobraste, cuánto pagaste de alquiler y cuánto te quedó.</li>
        </ul>
    </div>

    <h3>1. Tu escuela y la configuración</h3>
    <ol class="steps">
        <li>Como super administrador, <a href="#entidad">creá la entidad</a> con el nombre de tu escuela (ej.: <em>Escuela de Tenis Martín</em>), tipo <strong>Club</strong>.</li>
        <li>En <strong>Configuración</strong>: moneda, día de vencimiento de las cuotas y, en <em>Agenda de clases</em>, cómo se llaman tus grupos (<em>Grupo</em>, <em>Nivel</em>…) y el <strong>precio de la clase suelta</strong>.</li>
        <li>En <em>Cobros</em>: datos para transferir (CBU / alias), <em>Los profesores pueden cobrar en efectivo</em> activado y <em>El efectivo se rinde</em> <strong>desactivado</strong> (el dinero es tuyo). Si igual querés llevar el control de tu efectivo, activá <em>Quien rinde puede confirmar su propia rendición</em>.</li>
        <li>En <strong>Socios › Categorías</strong> dejá una categoría <em>Alumno</em> con cuota 0 (lo que cobrás va por grupo, pack o clase).</li>
    </ol>

    <h3>2. Cargar las canchas que alquilás</h3>
    <p>El club tiene, por ejemplo, 10 canchas. Cargá <strong>solo las que usás</strong> (si un día usás otra, la agregás en un minuto).</p>
    <ol class="steps">
        <li>En <strong>Instalaciones › Nueva instalación</strong>: nombre claro, por ejemplo <em>Club Norte – Cancha 3</em>.</li>
        <li><strong>Tarifa por hora: 0</strong> y <strong>desmarcá «Admite reservas»</strong> y «Visible en la web»: la tarifa de instalaciones es lo que pagan los socios al reservar, no lo que vos le pagás al club.</li>
        <li>Horario de apertura y cierre del club (sirve de referencia en la agenda).</li>
        <li>En <strong>Administración › Usuarios</strong>, abrí tu usuario (y el de cada profe que trabaje con vos) y marcá esas canchas en <strong>Sedes donde da clases</strong>. Sin esto no aparecen al programar clases.</li>
    </ol>
    <x-manual.tip type="example">
        Martín alquila en el <strong>Club Norte</strong> las canchas 3 y 4 (lunes a viernes de 17 a 21 h) y en el <strong>Complejo Sur</strong> la cancha 1 los sábados. Carga tres instalaciones: <em>Club Norte – Cancha 3</em>, <em>Club Norte – Cancha 4</em> y <em>Complejo Sur – Cancha 1</em>, todas con tarifa 0 y sin reservas.
    </x-manual.tip>

    <h3>3. Alumnos y cómo te pagan</h3>
    <ol class="steps">
        <li><a href="#socios">Cargá a tus alumnos</a> (con correo, para que tengan el portal y paguen desde el celular).</li>
        <li>Elegí cómo cobrás:
            <ul>
                <li><strong>Cuota mensual por grupo:</strong> creá cada grupo en <a href="#actividades">{{ activity_label(true) }}</a> con su cuota, horarios y la cancha como sede; inscribí a los alumnos. Las clases se generan solas en la agenda.</li>
                <li><strong>Packs</strong> (ej. 8 clases por mes) o <strong>clase suelta</strong>: en <a href="#agenda">Agenda › Packs</a> creás los planes y se los asignás a cada alumno.</li>
            </ul>
        </li>
    </ol>

    <h3>4. Armar la agenda</h3>
    <ol class="steps">
        <li>En <strong>Agenda de clases › Agenda</strong>, vista <em>Semana</em>, tocá un horario libre o <strong>Nueva clase</strong>.</li>
        <li>Elegí la cancha (sede), fecha y horario; sumá a los alumnos. Para una clase fija, <strong>Repetir semanalmente</strong> con los días y hasta qué fecha.</li>
        <li>Si en esa cancha ya tenés otra clase a esa hora, el sistema te avisa. Tampoco te deja estar en dos clases a la vez.</li>
        <li>Cada día, en <strong>Clases de hoy</strong> (o <a href="#acceso">Control de acceso › Asistencia</a>) tomás lista: todos aparecen presentes y destildás a los que faltan.</li>
    </ol>
    <p>En la vista <em>Mes</em> de la agenda ves las <strong>horas ocupadas</strong> del período, con un color por cancha: te sirve para controlar cuántas horas te va a cobrar el club.</p>

    <h3>5. Pagar el alquiler y registrarlo</h3>
    <ol class="steps">
        <li>Cuando le pagás al club, andá a <strong>Tesorería › Gastos › Registrar gasto</strong>.</li>
        <li>Categoría <strong>Alquiler</strong>, la <strong>cancha</strong> en <em>Instalación</em> y el <strong>mes</strong> al que corresponde.</li>
        <li>Fecha, importe, medio (efectivo, transferencia…), a quién se pagó (ej. <em>Club Norte</em>) y el N° de transferencia o recibo.</li>
        <li>Con <strong>Sacar foto</strong> fotografiás el recibo que te dio el club con el celular o la compu, lo encuadrás con zoom y queda guardado junto al gasto.</li>
    </ol>
    <p>Arriba de la lista de gastos queda una tarjeta por cancha: <strong>cuánto y cuándo pagaste el último alquiler</strong> y si <strong>este mes ya está pago</strong>. El mes siguiente, el botón <strong>Repetir</strong> (↻) de esa fila abre el formulario ya completo: solo ajustás el importe si cambió.</p>
    <x-manual.tip type="example">
        El 5/11 Martín transfiere $ 180.000 al Club Norte por noviembre de la cancha 3 y $ 180.000 por la cancha 4, y paga en efectivo $ 60.000 al Complejo Sur. Registra tres gastos de <em>Alquiler</em>, cada uno con su cancha y el mes de noviembre, y les saca foto a los recibos. En el tablero ve: cobrado $ 950.000, gastado $ 420.000, <strong>resultado del mes $ 530.000</strong>.
    </x-manual.tip>

    <h3>6. El día a día</h3>
    <ul>
        <li><strong>Cobrar:</strong> los alumnos transfieren y suben el comprobante desde <em>Mi cuenta</em>, o te pagan en efectivo y lo registrás en <strong>Cobrar en efectivo</strong> desde el celular.</li>
        <li><strong>Quién debe:</strong> en el <em>Tablero</em> (lista de deudores) y en <a href="#mensajes-alumnos">Mensajes a alumnos › Los que deben</a> les mandás un recordatorio al celular.</li>
        <li><strong>Lluvia o feriado:</strong> en <em>Clases de hoy</em>, <strong>Suspender</strong> avisa a los alumnos con una notificación.</li>
        <li><strong>Fin de mes:</strong> mirá en el <em>Tablero</em> el resultado del mes y en <em>Gastos</em> que todas las canchas tengan el alquiler pago.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section id="conceptos">
    <h2><span class="num">i</span> Conceptos básicos</h2>
    <dl class="glossary">
        <dt>Entidad</dt>
        <dd>Cada club, gimnasio o escuela que administrás. Tiene sus propios socios, cuotas, pagos, gastos, configuración, usuarios y sitio web. Puede ser <strong>Club</strong> (cuota social y {{ $acts }}), <strong>Gimnasio</strong> (planes de acceso) o <strong>Club con gimnasio</strong> (ambos). Si administrás más de una, se cambia con el selector de la barra superior.</dd>
        <dt>Socio / alumno</dt>
        <dd>Persona asociada a la entidad. Tiene número, categoría, estado (pendiente, activo, suspendido, baja), foto y una cuenta con sus cargos, pagos y saldo a favor.</dd>
        <dt>Categoría</dt>
        <dd>Define la cuota social mensual y el derecho de ingreso (ej.: Infantil, Activo, Familiar, o <em>Alumno</em> con cuota 0).</dd>
        <dt>Cargo / cuota</dt>
        <dd>Lo que el socio debe pagar: cuota social, cuota de {{ $act }}, inscripción anual, plan de gimnasio, reserva, torneo, vestimenta o un cargo manual. Puede estar pendiente, con pago parcial, vencido, pagado o anulado.</dd>
        <dt>Saldo a favor</dt>
        <dd>Dinero del socio que quedó a cuenta (por ejemplo, de un torneo cancelado) y que se usa para pagar otro cargo cuando se elige.</dd>
        <dt>Gasto</dt>
        <dd>Lo que la entidad paga: alquiler de canchas o salones, luz, sueldos, insumos… Con los pagos y los gastos el tablero calcula el resultado del mes.</dd>
        <dt>Usuario y rol</dt>
        <dd>Cuenta para ingresar al sistema. El personal tiene uno o más <strong>roles</strong> por entidad (Administrador, Tesorería, Profesor/a…). Los socios con acceso al portal también tienen usuario.</dd>
        <dt>Super administrador</dt>
        <dd>Acceso total a todas las entidades. Es el único que puede crear entidades.</dd>
    </dl>
</section>

{{-- ============================================================ --}}
<section id="ingresar">
    <h2><span class="num">1</span> Ingresar al sistema</h2>
    <ol class="steps">
        <li>Abrí la dirección del sistema y entrá a <strong>/ingresar</strong> (o el botón <em>Ingresar</em> del sitio web).</li>
        <li>Escribí tu correo y contraseña. Si la olvidaste, usá <em>¿Olvidaste tu contraseña?</em> y te llega un enlace por correo.</li>
        <li>El personal entra al <strong>panel de administración</strong>; los socios, al <strong>portal del socio</strong>. Un profesor entra directo a <em>Clases de hoy</em>.</li>
    </ol>
    <x-manual.shot src="01-login.png" caption="Pantalla de ingreso." />
</section>

{{-- ============================================================ --}}
<section id="entidad">
    <h2><span class="num">2</span> Crear la entidad (club, gimnasio o escuela)</h2>
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
    <x-manual.tip type="example">
        Nombre: <strong>Club Atlético Ejemplo</strong> · Tipo: <strong>Club</strong> · Identificador: <code>club-atletico-ejemplo</code>. Se crea con la configuración, el sitio web y los datos base para empezar; todo se puede editar o borrar.
    </x-manual.tip>
    <p>Cuando administrás varias entidades, la pantalla de Entidades muestra un resumen de cada una (socios activos, cobrado en el mes, deuda vencida) y el botón <strong>Administrar</strong> para cambiar a ella.</p>
    <x-manual.shot src="04-entidades.png" caption="Varias entidades administradas desde la misma cuenta." />
</section>

{{-- ============================================================ --}}
<section id="configuracion">
    <h2><span class="num">3</span> Configuración</h2>
    <p>En <strong>Administración › Configuración del club</strong> se definen las reglas del día a día:</p>
    <div class="overflow-x-auto">
        <table class="manual-table">
            <thead><tr><th>Grupo</th><th>Qué se configura</th></tr></thead>
            <tbody>
                <tr><td><strong>Club</strong></td><td>Moneda; día de <strong>generación</strong> y de <strong>vencimiento</strong> de las cuotas; <strong>recargo por mora</strong> (0 lo desactiva); cuotas vencidas que <strong>bloquean el ingreso</strong>; si se exige no tener deuda para inscribirse o reservar; cobrar el mes en curso al inscribirse; recordatorios por correo.</td></tr>
                <tr><td><strong>Agenda de clases</strong></td><td>Cómo se llaman las actividades (<em>Nivel</em>, <em>Grupo</em>…); precio de la clase suelta; duración de la clase; semanas que se generan por adelantado; si los alumnos pueden avisar que faltan; <strong>si se puede tomar o corregir asistencia de días anteriores</strong>.</td></tr>
                <tr><td><strong>Cobros</strong></td><td>Comprobantes de transferencia (y si se acreditan solos); datos para transferir; si los profesores cobran en efectivo; si el efectivo se rinde; <strong>si quien rinde puede confirmar su propia rendición</strong>.</td></tr>
                <tr><td><strong>Sitio, contacto, redes, app</strong></td><td>Nombre, logo, colores (se usan también en el carnet), datos de contacto y el ícono de la app.</td></tr>
            </tbody>
        </table>
    </div>
    <x-manual.shot src="05-configuracion.png" caption="Configuración: cuotas, recargos y reglas." />
    <x-manual.tip type="example">
        Generación el día <strong>1</strong>, vencimiento el día <strong>10</strong> y recargo del <strong>10 %</strong>: una cuota de $ 18.000 generada el 1/10 que no se paga hasta el 10/10 pasa a <em>Vencida</em> el 11/10 y queda en $ 19.800. Los torneos y la vestimenta vencen sin recargo.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="categorias">
    <h2><span class="num">4</span> Categorías de socios</h2>
    <p>En <strong>Socios › Categorías</strong> definís cuánto paga cada tipo de socio por mes y el derecho de ingreso (se cobra una sola vez al dar de alta). Los rangos de edad son orientativos para elegir la categoría. También podés crear una categoría nueva desde el alta del socio, sin salir del formulario.</p>
    <x-manual.shot src="06-categorias.png" caption="Categorías con su cuota mensual y cantidad de socios activos." />
    <x-manual.tip>
        Si cambiás la cuota de una categoría, el nuevo importe se usa a partir de la próxima generación de cuotas. Las cuotas ya generadas no cambian. Para una escuela o academia, una categoría <em>Alumno</em> con cuota 0 evita cobrar cuota social.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="socios">
    <h2><span class="num">5</span> Socios, foto y carnet</h2>
    <h3>Alta</h3>
    <ol class="steps">
        <li>Andá a <strong>Socios</strong> y hacé clic en <strong>Nuevo socio</strong> (también está en el tablero).</li>
        <li>Completá los datos personales, la <strong>categoría</strong> y la <strong>fecha de ingreso</strong>.</li>
        <li><strong>Foto:</strong> tocá <strong>Sacar foto</strong> para usar la webcam, la cámara de la notebook o la del celular (o <em>Elegir archivo</em>). Después <strong>acercala con el zoom y movela</strong> para centrar la cara en el círculo, y <em>Usar esta foto</em>. Se guarda cuadrada, lista para el carnet.</li>
        <li>Cargá el <strong>correo electrónico</strong>: es necesario para el portal y para recibir recibos.</li>
        <li>Si es parte de una familia, indicá el <strong>socio titular</strong> y el parentesco. Guardá: el número de socio se asigna solo.</li>
    </ol>
    <x-manual.shot src="07-socio-nuevo.png" caption="Formulario de alta de socio." />
    <x-manual.tip type="warning" title="La cámara no abre">
        El navegador solo deja usar la cámara si el sistema se abre con <strong>https://</strong> (o desde <code>localhost</code> en la misma compu). Sin eso, en el celular <em>Sacar foto</em> abre igual la cámara del teléfono; en la compu queda <em>Elegir archivo</em>. Si el navegador pregunta, tocá <strong>Permitir</strong>.
    </x-manual.tip>

    <h3>Solicitudes desde la web</h3>
    <p>Si está habilitado el formulario <em>Asociate</em> del sitio, las personas pueden pedir el alta. Llegan como socios <strong>Pendientes de aprobación</strong> (el menú Socios muestra un contador). Abrí la ficha y elegí <strong>Aprobar</strong> o <strong>Rechazar</strong>.</p>

    <h3>Ficha del socio</h3>
    <p>El listado permite buscar por nombre, documento o número y filtrar por estado y categoría. La ficha concentra todo:</p>
    <ul>
        <li><strong>Cuenta:</strong> cargos, <strong>pagos con el concepto que cubrió cada uno</strong> (y el importe aplicado si fueron varios), <strong>saldo a favor</strong> con sus movimientos y <strong>devoluciones</strong>.</li>
        <li><strong>Registrar pago</strong> o agregar un <strong>cargo manual</strong>; <strong>inscribirlo</strong> en {{ $acts }}.</li>
        <li><strong>Habilitar acceso al portal</strong>: le llega un correo para crear su contraseña.</li>
        <li><strong>Suspender</strong>, <strong>dar de baja</strong> o <strong>reactivar</strong>. Un socio suspendido o dado de baja no genera cuotas ni puede ingresar.</li>
        <li>Menú ▾: <strong>Imprimir carnet</strong> y <strong>Verificar carnet</strong>.</li>
    </ul>
    <x-manual.shot src="09-socio-ficha.png" caption="Ficha del socio: saldo, cuenta corriente y acciones." />

    <h3>Carnet para imprimir</h3>
    <p>Desde la ficha (menú ▾ › <strong>Imprimir carnet</strong>) o el propio socio desde su portal (<em>Carnet › Imprimir mi carnet</em>). Sale en <strong>tamaño tarjeta</strong> (85,6 × 54 mm) con frente y dorso juntos:</p>
    <ul>
        <li><strong>Frente:</strong> logo y colores del club, foto, nombre, documento, categoría, N° de socio y «socio desde».</li>
        <li><strong>Dorso:</strong> el <strong>código QR</strong> para el ingreso, datos de contacto del club y espacio para la firma.</li>
    </ul>
    <p>Recortá por la línea punteada de afuera y doblá por la del medio (ideal para plastificar). Al imprimir activá <strong>Gráficos de fondo</strong> para que salgan los colores.</p>

    <h3>Ficha médica</h3>
    <p>Primero armá el modelo en <strong>Socios › Ficha médica</strong>: las preguntas (grupo sanguíneo, alergias, medicación, apto físico…) con su tipo de respuesta, si es obligatoria y en qué sección va; a la derecha ves una vista previa.</p>
    <ul>
        <li>En el <strong>alta del socio</strong> aparece la sección <em>Ficha médica</em>; se puede completar después desde la pestaña <strong>Ficha médica</strong> de su ficha, que indica si está completa y quién la actualizó.</li>
        <li>Un campo que ya tiene respuestas no se borra: <strong>desactivalo</strong> y deja de pedirse, pero lo cargado se sigue viendo.</li>
        <li>Permisos: <em>ver</em> (por ejemplo, los profesores), <em>completar y modificar</em> y <em>armar el modelo</em>.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section id="cuotas" class="chapter">
    <h2><span class="num">6</span> Cuotas y cargos</h2>
    <p>En <strong>Tesorería › Cuotas y cargos</strong> ves todo lo que deben los socios, con filtros por estado, período, tipo y socio.</p>
    <x-manual.shot src="10-cuotas.png" caption="Cuotas y cargos con su estado." />
    <h3>Generar las cuotas del mes</h3>
    <ol class="steps">
        <li>Hacé clic en <strong>Generar cuotas</strong>.</li>
        <li>Elegí el <strong>período</strong> (mes y año) y confirmá.</li>
        <li>Se crea la cuota social de cada socio activo según su categoría, más la cuota de cada {{ $act }} en la que esté inscripto (con su beca o cuota individual, si tiene).</li>
    </ol>
    <x-manual.tip>
        Es seguro repetirlo: no duplica cargos ya generados. Además, el sistema lo hace solo el día configurado de cada mes (ver <a href="#automaticos">procesos automáticos</a>).
    </x-manual.tip>
    <p><strong>Procesar vencimientos</strong> marca como vencidos los cargos impagos y aplica el recargo en el momento (normalmente lo hace el sistema solo cada noche). Un cargo sin pagos se puede <strong>anular</strong> indicando el motivo (queda en la auditoría). Con el botón de billetes vas directo a cobrarlo.</p>
</section>

{{-- ============================================================ --}}
<section id="pagos">
    <h2><span class="num">7</span> Cobrar y emitir recibos</h2>
    <ol class="steps">
        <li>Andá a <strong>Tesorería › Pagos › Registrar pago</strong> (o desde el tablero o la ficha del socio).</li>
        <li>Buscá al socio por nombre, documento o número.</li>
        <li>Marcá los <strong>cargos</strong> que paga: la cuota, el torneo, la vestimenta… juntos o por separado. El importe se completa solo; podés bajarlo para un <strong>pago parcial</strong>.</li>
        <li>Elegí el <strong>medio de pago</strong> (efectivo, transferencia, débito, crédito u otro; o <strong>saldo a favor</strong> si tiene), la fecha y, si corresponde, el número de operación.</li>
        <li>Guardá y usá <strong>Imprimir recibo</strong>. Si el socio tiene correo, también recibe el comprobante.</li>
    </ol>
    <x-manual.shot src="12-registrar-pago.png" caption="Registro de un pago imputado a cargos pendientes." />
    <x-manual.tip type="example">
        Juan debe la cuota social de octubre ($ 18.000) y Fútbol ($ 8.000). Paga $ 26.000 por transferencia: se marcan los dos cargos, medio <em>Transferencia</em>, referencia <code>OP-458812</code>. Si paga solo $ 20.000, se cancela primero el cargo más antiguo y el otro queda con <em>Pago parcial</em>.
    </x-manual.tip>
    <p>El listado de <strong>Pagos</strong> permite filtrar por fecha y medio, reimprimir recibos, exportar a planilla y <strong>anular</strong> un pago mal cargado (los cargos vuelven a quedar pendientes). Los profesores cobran desde el celular en <strong>Cobrar en efectivo</strong> (ver <a href="#niveles">sección 12</a>).</p>
</section>

{{-- ============================================================ --}}
<section id="saldo">
    <h2><span class="num">7a</span> Saldo a favor y devoluciones <span class="badge-new">Nuevo</span></h2>
    <p>Cuando un socio pagó algo que ya no corresponde (por ejemplo, un <strong>torneo cancelado</strong>), ese dinero se puede <strong>devolver</strong> o dejar <strong>a cuenta</strong> como saldo a favor.</p>
    <h3>Devolver o dejar a cuenta</h3>
    <ol class="steps">
        <li>En el torneo cancelado, en la fila del alumno que pagó, tocá <strong>Devolver o dejar a cuenta</strong>.</li>
        <li>Elegí <strong>Se la devuelvo</strong> (importe, fecha y cómo: efectivo, transferencia…) o <strong>Queda a cuenta</strong>. El importe propuesto es todo lo que pagó; se puede cambiar para devolver o dejar solo una parte.</li>
        <li>Si no se puede devolver ni dejar a cuenta, no registres nada: el cargo ya quedó anulado y no debe.</li>
    </ol>
    <h3>Usar el saldo a favor</h3>
    <p>El saldo no se usa solo: <strong>se elige al cobrar</strong>. En <em>Registrar pago</em> aparece «Tiene $ X de saldo a favor» con el botón <strong>Usarlo para este pago</strong> (o el medio <em>Saldo a favor</em>); en <em>Cobrar en efectivo</em>, el botón <strong>Pagar con saldo a favor</strong>. Si no alcanza, el resto del cargo queda pendiente.</p>
    <ul>
        <li>El saldo a favor paga cargos del club (cuotas, torneos, vestimenta), no los cobros a nombre de un profesor (packs, clases sueltas).</li>
        <li>No es efectivo: no entra en lo que el profesor tiene que rendir.</li>
        <li>Si se anula un pago hecho con saldo a favor, el saldo vuelve. Un pago cuyo dinero ya se devolvió o se dejó a cuenta no se puede anular.</li>
        <li>Todo queda en la ficha del socio (secciones <em>Saldo a favor</em> y <em>Devoluciones</em>) y el alumno lo ve en su portal.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section id="gastos">
    <h2><span class="num">7b</span> Gastos y resultado del mes</h2>
    <p>Lo que se cobra son los <strong>pagos</strong>; lo que se paga (alquiler de canchas o del salón, luz, sueldos, insumos…) se registra en <strong>Tesorería › Gastos</strong>. Con las dos cosas el tablero muestra cuánto entró, cuánto salió y cuánto quedó.</p>
    <ol class="steps">
        <li>Tocá <strong>Registrar gasto</strong> y elegí la categoría (o escribí una nueva).</li>
        <li>Para el <strong>alquiler</strong>, elegí la categoría <em>Alquiler</em>, la cancha o salón en <em>Instalación</em> y el <em>mes al que corresponde</em>. El sistema te recuerda cuánto y cuándo se pagó el anterior.</li>
        <li>Cargá la fecha en que lo pagaste, el importe, el medio y, si querés, el número de transferencia.</li>
        <li><strong>Comprobante:</strong> <strong>Sacar foto</strong> abre la cámara (en el celular, la de atrás). Después encuadrás el ticket o factura <strong>con zoom y arrastrando</strong>, y se guarda lo que ves en el recuadro. También podés <strong>Subir archivo</strong> (foto o PDF).</li>
    </ol>
    <x-manual.tip type="example">
        El 5/10 se pagan $ 150.000 por transferencia por el alquiler de octubre del salón. Arriba de la lista queda la tarjeta <em>Alquiler · Salón</em> con el último pago y si octubre está pago. En noviembre, el botón <strong>Repetir</strong> (↻) de esa fila abre el formulario ya completo para noviembre: solo hay que ajustar el importe si aumentó.
    </x-manual.tip>
    <p>En el <strong>Tablero</strong> aparecen <em>Recaudado este mes</em>, <em>Gastado este mes</em>, el <em>Resultado del mes</em> (recaudado menos gastos y devoluciones), el gráfico de los últimos 12 meses, los gastos del mes por categoría y la lista de <strong>Deudores</strong>.</p>
    <p>Permisos: <em>Ver los gastos</em> y <em>Registrar, corregir y eliminar gastos</em> (en Tesorería, dentro de Roles y permisos).</p>
</section>

{{-- ============================================================ --}}
<section id="actividades" class="chapter">
    <h2><span class="num">8</span> {{ activity_label(true) }} e inscripciones</h2>
    <p>En <strong>{{ activity_label(true) }}</strong> cargás las disciplinas, grupos o niveles con su cuota mensual, <strong>inscripción anual</strong> (opcional), cupo, rango de edad, profesores, horarios y sede. Con el botón <em>Repetir en otros días</em> (↻) de cada horario lo copiás a otros días. Si la marcás como visible, aparece en el sitio web.</p>
    <x-manual.shot src="15-actividad-form.png" caption="Formulario de actividad con horarios." />
    <h3>Inscribir a un socio</h3>
    <ol class="steps">
        <li>En <strong>Inscripciones</strong>, hacé clic en <strong>Nueva inscripción</strong> (o desde la ficha del socio).</li>
        <li>Elegí el socio y {{ mb_strtolower(activity_label()) === 'actividad' ? 'la actividad' : 'el '.$act }}. Opcional: <strong>beca (%)</strong> o <strong>cuota individual fija</strong> (ver <a href="#becas">12b</a>).</li>
        <li>El sistema valida edad, cupo y deuda. Si está configurado, se cobra el mes en curso y la inscripción anual; los meses siguientes se generan con las cuotas.</li>
    </ol>
    <x-manual.tip type="example">
        <strong>Fútbol infantil</strong>, cuota $ 8.000, cupo 25, edades 6 a 12, martes y jueves 18:00 a 19:30 en Cancha 1. Si un socio de 15 años intenta inscribirse, el sistema lo rechaza por edad.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="instalaciones">
    <h2><span class="num">9</span> Instalaciones y reservas</h2>
    <p>En <strong>Instalaciones</strong> cargás canchas, quinchos, salones, etc., con su horario, duración del turno, turnos máximos por reserva y tarifa por hora (0 = sin cargo). Son también las <strong>sedes</strong> donde se dan clases.</p>
    <p>En <strong>Reservas</strong> ves la grilla del día por instalación. Hacé clic en un horario <em>Libre</em> o en <strong>Nueva reserva</strong>, elegí socio y turnos y confirmá. Si tiene tarifa, se genera el cargo en la cuenta del socio. Los socios también pueden reservar desde el portal.</p>
    <x-manual.tip>
        ¿Alquilás las canchas de otro club para dar clases? No las cargues como reservables: seguí la guía <a href="#primeros-pasos-profe">Primeros pasos: profe que alquila canchas</a>.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="gimnasio">
    <h2><span class="num">10</span> Gimnasio: planes y membresías</h2>
    <p>Disponible en entidades de tipo <strong>Gimnasio</strong> o <strong>Club con gimnasio</strong> (menú <em>Gimnasio</em>).</p>
    <p>Cada plan define precio, duración (días o meses), <strong>tipo de acceso</strong> (pase libre, solo clases o ambos), límite de visitas, clases incluidas y franjas horarias permitidas. En <strong>Gimnasio › Planes de socios › Asignar plan</strong> se elige socio, plan y fecha de comienzo (y si se renueva solo); se genera el cargo y el plan se activa al registrarse el pago.</p>
    <x-manual.tip type="example">
        <strong>Pase libre mañana</strong>: $ 25.000 por 1 mes, pase libre, solo de lunes a viernes de 7 a 12 h. <strong>8 clases</strong>: $ 20.000 por 1 mes, solo clases, límite 8 visitas.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="agenda">
    <h2><span class="num">11</span> Agenda de clases (profesores)</h2>
    <p>Para profesores que dan clases individuales o grupales (por ejemplo, tenis) en una o varias entidades. Cada profesor entra con su usuario y maneja <strong>su</strong> agenda, sus packs y sus cobros.</p>
    <h3>Preparar al profesor</h3>
    <ol class="steps">
        <li>En <strong>Administración › Usuarios</strong>, creá el usuario y dale el rol <strong>profesor</strong> (todo usuario necesita al menos un rol para aparecer y poder entrar).</li>
        <li>En el mismo formulario, marcá las <strong>sedes donde da clases</strong>.</li>
        <li>Si también da clases en otra entidad, cambiá de entidad y repetí: rol <em>profesor</em> y sus sedes allí.</li>
    </ol>
    <h3>Ver y armar la agenda</h3>
    <p>En <strong>Agenda de clases › Agenda</strong> elegís la vista <strong>Día</strong>, <strong>Semana</strong> o <strong>Mes</strong>, y <em>Todos mis clubes</em> (un color por club) o un club puntual (un color por sede). Arriba se ven los totales del período: clases, <strong>horas ocupadas</strong>, clases dadas y alumnos.</p>
    <ol class="steps">
        <li>Hacé clic en un horario libre o en <strong>Nueva clase</strong>: club y sede, fecha y horario.</li>
        <li>Buscá a los alumnos por nombre o documento (en toda la base: si es socio de otra entidad se lo suma a esta sin cobrarle ingreso). Con varios alumnos la clase es grupal.</li>
        <li>Para una clase fija, <strong>Repetir semanalmente</strong> con los días y hasta qué fecha.</li>
    </ol>
    <x-manual.tip>Un profesor no puede tener dos clases a la misma hora, aunque sean en clubes distintos, y el sistema avisa si la sede ya tiene una reserva u otra clase.</x-manual.tip>
    <h3>Asistencia, packs y clases sueltas</h3>
    <p>Al marcar la clase como dada, a cada presente: si tiene un <strong>pack vigente</strong> se le descuenta una clase; si no, se le cobra la <strong>clase suelta</strong> (precio de la clase, del profesor o de la entidad, en ese orden). Los ausentes no consumen ni pagan. <strong>Reabrir</strong> deshace y anula las clases sueltas no cobradas. Si en Configuración se desactivó la asistencia de días anteriores, solo se puede tomar o corregir el mismo día.</p>
    <p>En <strong>Agenda › Packs</strong> el profesor crea sus planes (<em>4 u 8 clases por mes</em>, <em>Clase de prueba</em>…) y con <strong>Asignar a alumno</strong> los vende. Packs y clases sueltas quedan <strong>a nombre del profesor</strong> y se cobran en <strong>Mis cobros</strong>; no bloquean el ingreso al club.</p>
    <x-manual.tip type="example">
        Martín da tenis en el <strong>Club Norte</strong> (lunes y miércoles) y en el <strong>Club Sur</strong> (martes). Lucía compró el pack <em>8 clases por mes</em> ($ 40.000); Pedro paga cada clase ($ 6.000). Al marcar la clase del lunes como dada, a Lucía le quedan 7 clases y a Pedro se le genera un cargo de $ 6.000 que cobra desde <em>Mis cobros</em>.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="niveles">
    <h2><span class="num">12</span> Niveles, asistencia y el celular</h2>
    <p>Pensado para academias y escuelas que agrupan a sus alumnos por <strong>nivel</strong> (Juvenil A, Juvenil B…), con uno o varios profesores por nivel y una cuota mensual. Sirve tanto para una coordinadora con varios profesores como para un profesor que trabaja solo.</p>
    <h3>Configuración</h3>
    <ul>
        <li><strong>Agenda de clases › Cómo se llaman las actividades</strong>: por ejemplo <em>Nivel / Niveles</em>.</li>
        <li><strong>Cobros</strong>: comprobantes de transferencia (y si se acreditan solos), datos para transferir, si los profesores cobran en efectivo, si el efectivo se rinde y si quien rinde puede confirmar su propia rendición.</li>
    </ul>
    <h3>Armar los niveles</h3>
    <ol class="steps">
        <li>En <strong>{{ activity_label(true) }}</strong>, creá cada nivel con su cuota mensual, horarios y sede.</li>
        <li>Elegí el <strong>profesor responsable</strong> y, si hay más, los <strong>otros profesores a cargo</strong>.</li>
        <li>Inscribí a las alumnas en <strong>Inscripciones</strong> (con beca si corresponde). Las clases de las próximas semanas se generan solas y se ven en la <strong>Agenda</strong> y en <strong>Clases de hoy</strong>.</li>
    </ol>
    <h3>El profesor en el celular</h3>
    <p>Al ingresar, el profesor entra directo a <strong>Clases de hoy</strong>, con una barra inferior: <em>Hoy, Agenda, Cobrar, Rendir</em>.</p>
    <ul>
        <li><strong>Asistencia:</strong> tocá la clase: aparecen <strong>todos los alumnos del nivel tildados como presentes</strong>; destildá a los que faltan (quien avisó desde la app ya aparece destildado, con el motivo) y <strong>Guardar asistencia</strong>. Hay botones <em>Todos / Ninguno</em> y el contador «Presentes N de M».</li>
        <li><strong>Suspender:</strong> avisa a las alumnas con una notificación y por correo. Coordinación puede usar <em>No hay clase hoy para ningún grupo</em>.</li>
        <li><strong>Cobrar:</strong> buscá a la alumna, marcá lo que paga (cuota, torneo, vestimenta, o todo junto) y registrá el efectivo; se emite el recibo. Si tiene saldo a favor, aparece <em>Pagar con saldo a favor</em>.</li>
        <li><strong>Efectivo a rendir:</strong> lo cobrado y no rendido. <strong>Rendir</strong> lo envía a coordinación, que lo confirma en <em>Tesorería › Rendiciones</em>. Si está activada la opción, el profe confirma su propia rendición con <strong>Confirmar</strong>.</li>
    </ul>
    <h3>La alumna en el celular</h3>
    <ul>
        <li>Se instala la app desde el navegador (<em>Agregar a pantalla de inicio</em>) y se activan las <strong>notificaciones</strong>. En iPhone, primero hay que instalar la app.</li>
        <li><strong>Mis clases › No voy a esta clase</strong>: avisa al profesor, con un motivo opcional.</li>
        <li><strong>Mi cuenta › Informar un pago</strong>: elegí las cuotas, sacá una foto del comprobante (o subí el PDF) y enviá.</li>
    </ul>
    <x-manual.tip type="example">
        Laura coordina tres niveles con dos profesores. El martes llueve: desde <em>Clases de hoy</em> toca <em>No hay clase hoy para ningún grupo</em> y a todas las alumnas les llega la notificación. Sofía transfiere la cuota y sube el comprobante; Laura lo acredita en <em>Comprobantes</em>. Otra alumna le paga en efectivo al profe Diego, que lo registra en <em>Cobrar</em> y lo rinde a fin de semana.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="becas">
    <h2><span class="num">12b</span> Becas, aumentos e inscripción anual</h2>
    <h3>Becas por porcentaje</h3>
    <p>Al inscribir a un alumno (o después, en <strong>Inscripciones</strong>, tocando su cuota) se carga la <strong>beca en %</strong>. Se descuenta solo de la cuota mensual, nunca de torneos ni de la inscripción anual, y rige desde la próxima cuota que se genere. También se puede cargar una <strong>cuota individual fija</strong>; si tiene las dos, la beca se aplica sobre la cuota fija.</p>
    <x-manual.tip type="example">Sofía paga Juvenil B ($ 20.000). En octubre está becada al 25 % y paga $ 15.000; en noviembre la beca pasa a 50 % y paga $ 10.000. Con 100 % no se le genera cuota.</x-manual.tip>
    <h3>Aumento masivo de cuotas</h3>
    <p>En el listado de {{ $acts }}, <strong>Aumentar cuotas</strong>: <strong>monto fijo</strong> (ej. $ 10.000 más) o <strong>porcentaje</strong> (ej. 20 %), a qué {{ $acts }} se aplica, vista previa y confirmar. También se ajustan las cuotas individuales fijas; lo ya generado no cambia.</p>
    <h3>Inscripción anual</h3>
    <p>Cada {{ $act }} puede tener un costo de <strong>inscripción anual</strong> (0 = sin costo). Se cobra al inscribir, una vez por año. Para el año nuevo, <strong>Cobrar inscripción {{ now()->year }}</strong> en el listado se la cobra a los inscriptos que todavía no la tienen.</p>
</section>

{{-- ============================================================ --}}
<section id="torneos">
    <h2><span class="num">12c</span> Torneos</h2>
    <p>En <strong>Torneos › Nuevo torneo</strong> se carga nombre, <strong>costo</strong>, <strong>fecha máxima de pago</strong> y los <strong>días</strong>, cada uno con uno o varios {{ $acts }}. Puede haber varios torneos en el mes, cada uno con su costo.</p>
    <ul>
        <li><strong>Libre:</strong> los alumnos de esos {{ $acts }} se anotan desde el portal (<em>Torneos › Participar</em>) hasta el día anterior. El profe también puede anotarlos, uno por uno o con <em>Anotar a todos</em>.</li>
        <li><strong>Solo los que yo elija:</strong> solo el profe anota a los participantes.</li>
        <li><strong>Excepciones:</strong> el profe puede sumar a cualquier alumno, aunque sea de otro {{ $act }} o a último momento.</li>
    </ul>
    <p>El cargo del torneo se paga como la cuota, junto con ella o por separado. En el detalle se ve quién pagó y quién debe; con <em>Avisar a los que deben</em> se les manda un recordatorio. Un torneo impago queda <em>Vencido</em> sin recargo ni bloqueo de ingreso.</p>
    <p><strong>Si se cancela el torneo</strong>, se anulan todos los cargos y nadie queda debiendo. A quienes ya pagaron se les devuelve el dinero o queda a cuenta (ver <a href="#saldo">7a</a>).</p>
    <x-manual.tip type="example">Torneo <strong>Primavera</strong>, $ 15.000, pagar hasta el 20/10. Sábado 25: Juvenil A y B; domingo 26: Mayores. Lucía se anota desde el portal; el profe suma a Pedro (de Infantil) como excepción. Lucía paga cuota + torneo juntos por transferencia; Pedro paga solo el torneo en efectivo.</x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="mensajes-alumnos">
    <h2><span class="num">12d</span> Mensajes a alumnos</h2>
    <p>En <strong>Comunicación › Mensajes a alumnos</strong> (o desde un torneo o un adicional con <em>Avisar a los que deben</em>) se envía un mensaje a: los que deben (cuotas vencidas o que vencen en la semana), los que deben un torneo, los que deben vestimenta o un artículo, todos los alumnos de un {{ $act }}, o todos.</p>
    <p>El texto viene sugerido y se puede editar. Llega como <strong>notificación al celular</strong> a quienes la activaron y queda en el portal, en <em>Mensajes</em> y en el inicio. El profesor solo puede escribirles a sus alumnos.</p>
</section>

{{-- ============================================================ --}}
<section id="vestimenta">
    <h2><span class="num">12e</span> Vestimenta y artículos</h2>
    <p>Para lo que el profe compra y los alumnos le pagan: la malla de un torneo, la remera del grupo, un buzo…</p>
    <ol class="steps">
        <li>Desde el torneo (<em>Vestimenta y adicionales › Agregar</em>) o en <strong>Vestimenta y artículos › Nuevo adicional</strong>, cargá nombre, precio y fecha de pago. Si es de un torneo, la fecha de pago no puede ser posterior al torneo.</li>
        <li>Elegí para qué es: un torneo, un {{ $act }} o alumnos sueltos. En torneo y {{ $act }} aparecen todos tildados: destildá a quienes no corresponde.</li>
        <li>A cada alumno tildado se le suma el cargo, que se paga como la cuota (sola o junto con la cuota y el torneo).</li>
    </ol>
    <p>Si un alumno avisa que <strong>ya lo consiguió por otro lado</strong>, abrí el adicional y tocá <strong>Anular</strong> en su fila: se le anula solo a él y queda el motivo. Si ya había pagado, primero anulá el pago. Con <strong>Cobrar también a…</strong> se lo cargás a alguien más. Si vence sin pagarse no suma recargo ni bloquea el ingreso.</p>
</section>

{{-- ============================================================ --}}
<section id="acceso" class="chapter">
    <h2><span class="num">13</span> Control de acceso y asistencia</h2>
    <p><strong>Socios › Control de acceso</strong> tiene dos modos (pestañas arriba):</p>
    <h3>Ingreso</h3>
    <p>Recepción escanea el QR del carnet (con un lector o la cámara) o escribe el número de socio o documento y presiona <strong>Verificar</strong>. El sistema responde <strong>Permitido</strong> o <strong>Denegado</strong> con el motivo (suspendido, deuda vencida por encima del límite, plan vencido, fuera de horario, sin visitas…). Cada intento queda registrado.</p>
    <x-manual.shot src="21-control-acceso.png" caption="Verificación de ingreso con su resultado." />
    <h3>Asistencia por {{ $act }}</h3>
    <p>Como en un colegio: elegís uno de tus {{ $acts }} y la fecha, aparecen <strong>todos los alumnos tildados como presentes</strong> y destildás a los que faltan. <strong>Guardar asistencia</strong> la registra en la clase de ese día (si no había clase programada, se crea). Si volvés a entrar, ves lo guardado y lo podés corregir.</p>
    <ul>
        <li>El profesor entra directo a este modo y solo ve sus {{ $acts }}.</li>
        <li>Quien avisó desde la app que no iba aparece destildado, con el motivo.</li>
        <li>No se toma asistencia de fechas futuras ni de días con la clase suspendida. Con la opción de Configuración desactivada, tampoco de días anteriores.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section id="comunicacion">
    <h2><span class="num">14</span> Mensajes y avisos</h2>
    <ul>
        <li><strong>Mensajes:</strong> lo que llega desde el formulario de contacto del sitio. El menú muestra cuántos hay sin leer.</li>
        <li><strong>Avisos a socios:</strong> comunicados que se muestran en el portal (ej.: “La pileta abre el 1/12”), con fecha de publicación y vencimiento.</li>
        <li><strong>Mensajes a alumnos:</strong> notificación al celular a un grupo puntual (ver <a href="#mensajes-alumnos">12d</a>).</li>
    </ul>
    <x-manual.shot src="22-avisos.png" caption="Avisos publicados en el portal." />
</section>

{{-- ============================================================ --}}
<section id="sitio">
    <h2><span class="num">15</span> Sitio web</h2>
    <p>Cada entidad tiene su sitio público. Desde el menú <strong>Sitio web</strong> se edita sin conocimientos técnicos: <strong>identidad y contacto</strong> (nombre, logo, colores, teléfono, dirección, redes y mapa), <strong>portada</strong> (carrusel), <strong>secciones</strong> de la página de inicio, <strong>noticias</strong> y <strong>páginas</strong>.</p>
    <x-manual.shot src="23-identidad.png" caption="Identidad del sitio: logo, colores y datos de contacto." />
    <x-manual.tip>Usá el botón <strong>Ver sitio</strong> de la barra superior para ver los cambios. El logo y los colores también se usan en el carnet impreso.</x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="usuarios">
    <h2><span class="num">16</span> Usuarios y roles</h2>
    <h3>Crear un usuario del personal</h3>
    <ol class="steps">
        <li>En <strong>Administración › Usuarios</strong>, hacé clic en <strong>Agregar usuario</strong>.</li>
        <li>Completá nombre y correo. Podés definir la contraseña o enviarle un correo para que la cree.</li>
        <li>Elegí sus <strong>roles en esta entidad</strong> (es obligatorio al menos uno: un usuario sin rol no aparece en la lista ni puede entrar). Para un profesor, marcá también sus <strong>sedes</strong>.</li>
    </ol>
    <h3>Roles incluidos</h3>
    <div class="overflow-x-auto">
        <table class="manual-table">
            <thead><tr><th>Rol</th><th>Pensado para</th></tr></thead>
            <tbody>
                <tr><td>Administrador</td><td>Gestión completa de la entidad.</td></tr>
                <tr><td>Tesorería</td><td>Cuotas, pagos, anulaciones, comprobantes, rendiciones y reportes.</td></tr>
                <tr><td>Secretaría</td><td>Socios, inscripciones, cobros, reservas, avisos, torneos, vestimenta y mensajes.</td></tr>
                <tr><td>Recepción</td><td>Control de acceso, reservas, planes y cobros en mostrador.</td></tr>
                <tr><td>Coordinación</td><td>Niveles, inscripciones, agenda de todos los profesores, comprobantes, rendiciones, torneos y mensajes.</td></tr>
                <tr><td>Profesor/a</td><td>Su agenda, asistencia, packs y cobros; cobrar en efectivo a sus alumnos; torneos, vestimenta y mensajes a sus alumnos.</td></tr>
                <tr><td>Comunicación</td><td>Sitio web, mensajes y avisos.</td></tr>
            </tbody>
        </table>
    </div>
    <p>En <strong>Roles y permisos</strong> podés crear roles propios y elegir qué puede hacer cada uno: con <strong>Seleccionar todo / Quitar todo</strong> marcás todo de una vez, y con <strong>Marcar todos</strong> en cada grupo (Socios, Tesorería…) solo ese grupo. Los cambios se aplican al tocar <em>Guardar permisos</em>.</p>
    <x-manual.shot src="26-roles.png" caption="Edición de permisos por rol." />
</section>

{{-- ============================================================ --}}
<section id="auditoria">
    <h2><span class="num">17</span> Auditoría y reportes</h2>
    <p><strong>Auditoría</strong> registra quién creó, modificó o anuló cada dato y cuándo, con el detalle de los cambios: anulaciones de pagos y cargos, becas, aumentos, devoluciones…</p>
    <p>Los listados de <strong>Socios</strong>, <strong>Pagos</strong> y <strong>Cuotas</strong> se exportan a planilla (respetando los filtros). El <strong>Tablero</strong> resume lo recaudado, lo gastado y el resultado del mes, la deuda vencida y los deudores, los últimos 12 meses y las reservas del día.</p>
    <x-manual.shot src="28-tablero.png" caption="Tablero principal con los indicadores de la entidad." />
</section>

{{-- ============================================================ --}}
<section id="portal">
    <h2><span class="num">18</span> Portal del socio</h2>
    <p>Cuando habilitás el acceso al portal desde la ficha del socio, recibe un correo para crear su contraseña. Desde el celular o la compu puede:</p>
    <ul>
        <li><strong>Mi cuenta:</strong> lo que debe, pagos, recibos, <strong>saldo a favor</strong> e <strong>informar un pago</strong> con la foto del comprobante.</li>
        <li><strong>Carnet:</strong> el carnet digital con QR para el ingreso y <strong>Imprimir mi carnet</strong> en tamaño tarjeta.</li>
        <li><strong>Mis clases:</strong> próximas clases, historial de asistencia, clases que le quedan del pack y <em>No voy a esta clase</em>.</li>
        <li><strong>Torneos:</strong> anotarse en los torneos libres de su {{ $act }} y ver los suyos (con la vestimenta y, si se cancelaron, lo devuelto o a favor).</li>
        <li><strong>Mensajes:</strong> lo que le mandó el profe o el club.</li>
        <li>{{ activity_label(true) }}, reservas, planes del gimnasio, avisos y sus datos.</li>
    </ul>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-manual.shot src="29-portal-inicio.png" caption="Inicio del portal." />
        <x-manual.shot src="30-portal-carnet.png" caption="Carnet digital con QR." />
    </div>
    <x-manual.tip>
        Si una persona es socia de varias entidades, usa una sola cuenta y elige la entidad desde el portal. El portal se instala en el celular como una app (<em>Agregar a pantalla de inicio</em>).
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="automaticos">
    <h2><span class="num">19</span> Procesos automáticos</h2>
    <p>Con el programador de tareas del servidor activo, el sistema hace solo:</p>
    <div class="overflow-x-auto">
        <table class="manual-table">
            <thead><tr><th>Cuándo</th><th>Qué hace</th></tr></thead>
            <tbody>
                <tr><td>Todos los días, 00:30</td><td>Marca como vencidos los cargos impagos y aplica el recargo por mora (no a torneos ni vestimenta).</td></tr>
                <tr><td>Todos los días, 00:45</td><td>Vence planes de gimnasio, genera renovaciones automáticas y cancela altas impagas.</td></tr>
                <tr><td>Todos los días, 01:00</td><td>Genera las cuotas del mes en el día configurado de cada entidad.</td></tr>
                <tr><td>Todos los días, 02:00</td><td>Genera las clases de las próximas semanas de cada {{ $act }} con horario.</td></tr>
                <tr><td>Todos los días, 09:00</td><td>Envía recordatorios de vencimiento por correo (si están habilitados).</td></tr>
            </tbody>
        </table>
    </div>
    <x-manual.tip type="warning">
        Requiere en el servidor <code>php artisan schedule:run</code> cada minuto (cron) y <code>php artisan queue:work</code> para correos y notificaciones. Mientras tanto, usá <em>Generar cuotas</em> y <em>Procesar vencimientos</em> a mano; las clases del día se generan solas al abrir <em>Clases de hoy</em>.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="ejemplo" class="chapter">
    <h2><span class="num">★</span> Ejemplo completo: un mes en el Club Atlético Ejemplo</h2>
    <p>Recorrido para simular el sistema desde cero (unos 15 minutos):</p>
    <ol class="steps">
        <li><strong>Entidad.</strong> Como super administrador, creá “Club Atlético Ejemplo”, tipo <em>Club</em>.</li>
        <li><strong>Configuración.</strong> Moneda “$”, generación día 1, vencimiento día 10, recargo 10 %.</li>
        <li><strong>Categorías.</strong> <em>Activo</em> en $ 18.000 y <em>Cadete</em> en $ 11.000.</li>
        <li><strong>Actividad.</strong> “Fútbol” con cuota $ 8.000, cupo 30, martes y jueves de 19 a 20:30.</li>
        <li><strong>Socios.</strong> Juan Pérez (Activo, foto con la cámara) y su hijo Tomás (Cadete, titular: Juan).</li>
        <li><strong>Inscripción.</strong> Tomás en Fútbol con una beca del 50 %.</li>
        <li><strong>Cuotas.</strong> Generá el período actual: Juan $ 18.000; Tomás $ 11.000 de cuota social y $ 4.000 de Fútbol.</li>
        <li><strong>Cobro.</strong> Registrá un pago de Juan en efectivo e imprimí el recibo. Registrá también un gasto (la luz, con foto del ticket): el tablero muestra recaudado, gastado y resultado.</li>
        <li><strong>Deuda.</strong> Dejá impagas las cuotas de Tomás: al vencer quedan <em>Vencidas</em> con recargo y aparecen en <em>Deudores</em>.</li>
        <li><strong>Acceso.</strong> En Control de acceso, verificá a Juan (permitido) y a Tomás con deuda (según el límite).</li>
        <li><strong>Carnet.</strong> Desde la ficha de Juan, <em>Imprimir carnet</em>.</li>
        <li><strong>Portal.</strong> Habilitá el portal de Juan, creá su contraseña desde el correo y mirá su carnet y su cuenta.</li>
    </ol>
    <x-manual.tip>
        En un entorno de prueba los correos no se envían: quedan en el registro del sistema (<code>storage/logs</code>) según la configuración de correo del servidor.
    </x-manual.tip>
</section>

{{-- ============================================================ --}}
<section id="faq">
    <h2><span class="num">?</span> Preguntas frecuentes</h2>
    <dl class="glossary faq">
        <dt>Generé las cuotas dos veces, ¿se duplicaron?</dt>
        <dd>No. La generación solo crea lo que falta.</dd>
        <dt>Cobré a la persona equivocada.</dt>
        <dd>Anulá el pago desde el listado de Pagos (queda en auditoría) y registralo de nuevo al socio correcto.</dd>
        <dt>Creé un usuario y no lo veo en la lista.</dt>
        <dd>La lista muestra al personal con algún rol en la entidad actual. Hoy el formulario exige elegir al menos un rol.</dd>
        <dt>No veo un menú que otro usuario sí ve.</dt>
        <dd>El menú depende de los permisos de tu rol en la entidad actual. Pedile a un administrador que los revise en Roles y permisos.</dd>
        <dt>No veo el menú «Niveles».</dt>
        <dd>El menú se llama <em>Actividades</em> hasta que en Configuración › Agenda de clases cambiás «Cómo se llaman las actividades».</dd>
        <dt>La cámara no abre para la foto.</dt>
        <dd>Se necesita entrar con <code>https://</code> (o <code>localhost</code>) y darle permiso al navegador (candado junto a la dirección). En el celular, sin https, <em>Sacar foto</em> abre igual la cámara del teléfono.</dd>
        <dt>Un alumno ya consiguió la malla por otro lado.</dt>
        <dd>En el adicional, <strong>Anular</strong> en su fila, con el motivo. Si ya había pagado, primero anulá el pago.</dd>
        <dt>Se canceló un torneo y algunos ya habían pagado.</dt>
        <dd>En el torneo, <em>Devolver o dejar a cuenta</em> en cada fila (ver <a href="#saldo">7a</a>).</dd>
        <dt>Un socio no puede inscribirse o reservar.</dt>
        <dd>Revisá si tiene deuda vencida y si la configuración exige no tener deuda. También se validan edad y cupo. Las deudas de torneos, vestimenta o con un profesor no bloquean.</dd>
        <dt>El PDF del manual o del carnet sale sin colores.</dt>
        <dd>En la ventana de impresión activá <strong>Gráficos de fondo</strong> (en «Más opciones»).</dd>
        <dt>¿Puedo borrar un socio?</dt>
        <dd>Se recomienda darlo de baja para conservar su historial. Solo se puede eliminar si no tiene movimientos.</dd>
    </dl>
</section>

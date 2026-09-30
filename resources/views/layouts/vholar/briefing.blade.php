@extends('app')
@section('title', 'Briefing del Piloto')

@section('content')

{{-- Header --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #1a1035 0%, #2d1b69 100%);">
            <div class="card-body py-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div><i class="bi bi-journal-richtext text-warning" style="font-size:2.5rem;"></i></div>
                    <div>
                        <h2 class="text-white mb-1 fw-bold">Briefing del Piloto</h2>
                        <p class="text-white-50 mb-0">Vholar Virtual Airlines · vmsOpenAcars v0.7.8 · Edición 2026</p>
                    </div>
                    <div class="ms-auto text-end d-none d-md-block">
                        <img src="{{ public_asset('images/vholar_logoweb.png') }}" height="50" alt="Vholar">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Índice --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-list-ul me-2"></i>Contenido</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <ul class="list-unstyled mb-0 small">
                            <li><a href="#sec-bienvenida" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>1. Bienvenida</a></li>
                            <li><a href="#sec-requisitos" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>2. Requisitos y Configuración</a></li>
                            <li><a href="#sec-reglamentos" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>3. Reglamentos Generales</a></li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul class="list-unstyled mb-0 small">
                            <li><a href="#sec-procedimientos" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>4. Procedimientos de Vuelo</a></li>
                            <li><a href="#sec-puntuacion" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>5. Sistema de Puntuación</a></li>
                            <li><a href="#sec-redes" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>6. Redes de Vuelo en Línea</a></li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul class="list-unstyled mb-0 small">
                            <li><a href="#sec-rangos" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>7. Rangos y Progresión</a></li>
                            <li><a href="#sec-operaciones" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>8. Tipos de Operación</a></li>
                            <li><a href="#sec-conducta" class="text-decoration-none"><i class="bi bi-chevron-right text-primary me-1"></i>9. Conducta y Solución de Problemas</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 1. Bienvenida --}}
<div class="row mb-4" id="sec-bienvenida">
    <div class="col-12">
        <div class="card border-start border-primary border-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-hand-wave me-2"></i>1. Bienvenida a Vholar Virtual Airlines
            </div>
            <div class="card-body">
                <p class="lead">Bienvenido a la tripulación de <strong>Vholar Virtual Airlines</strong>. Ser parte de esta aerolínea virtual es comprometerse con la excelencia operacional, el realismo y el compañerismo que nos caracterizan.</p>
                <p class="mb-0">Este documento cubre los procedimientos, estándares y herramientas que rigen tu actividad como piloto. El cliente ACARS oficial es <strong>vmsOpenAcars v0.7.8</strong>, que califica cada vuelo con 14 criterios en tiempo real. Léelo antes de tu primer vuelo.</p>
                <div class="alert alert-info d-flex align-items-start gap-2 mt-3 mb-2">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <div>Este briefing complementa al <a href="{{ url('/page/oma') }}" class="alert-link">Manual de Operaciones Parte A (OM-A)</a>. En caso de contradicción, el OM-A prevalece.</div>
                </div>
                <div class="alert alert-warning d-flex align-items-start gap-2 mb-0">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                    <div><strong>Nota de actualización:</strong> El OM-A hace referencia a la plataforma anterior "<strong>crewsystem</strong>" y al cliente ACARS "<strong>FDA-ACARS</strong>". Ambos han sido reemplazados por la plataforma actual (<strong>Vholar / phpVMS</strong>) y por <strong>vmsOpenAcars v0.7.8</strong> respectivamente. Este Briefing del Piloto refleja el estado actual del sistema.</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 2. Requisitos y Configuración --}}
<div class="row mb-4" id="sec-requisitos">
    <div class="col-12">
        <div class="card border-start border-info border-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-gear me-2"></i>2. Requisitos y Configuración de vmsOpenAcars
            </div>
            <div class="card-body">

                <h6 class="fw-bold mb-2">Requisitos de software</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-dark"><tr><th>Requisito</th><th>Detalle</th></tr></thead>
                        <tbody>
                            <tr><td>Sistema operativo</td><td>Windows (cualquier versión moderna)</td></tr>
                            <tr><td>FSUIPC / XUIPC</td><td>Instalado y activo — la versión <strong>gratuita</strong> es suficiente</td></tr>
                            <tr><td>NavData API Key</td><td>Proporcionada por Vholar — necesaria para scoring completo</td></tr>
                            <tr><td>Cuenta SimBrief</td><td>Gratuita en simbrief.com (usar tu pilot ID o alias, no el correo)</td></tr>
                            <tr><td>Conexión a internet</td><td>Necesaria durante todo el vuelo</td></tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-bold mb-2">Simuladores compatibles</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-dark"><tr><th>Simulador</th><th>FSUIPC requerido</th></tr></thead>
                        <tbody>
                            <tr><td>MSFS 2020 / 2024</td><td>FSUIPC 7</td></tr>
                            <tr><td>Prepar3D v5 / v6</td><td>FSUIPC 6</td></tr>
                            <tr><td>Prepar3D v4</td><td>FSUIPC 5 o 6</td></tr>
                            <tr><td>FSX / FSX Steam</td><td>FSUIPC 4</td></tr>
                            <tr><td>X-Plane 11 / 12</td><td>Plugin <strong>XUIPC</strong> (en lugar de FSUIPC)</td></tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-bold mb-2">Configuración inicial (Settings)</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card bg-body-secondary border-0 h-100">
                            <div class="card-body small">
                                <p class="fw-bold mb-1"><i class="bi bi-server text-primary me-1"></i>phpVMS</p>
                                <p class="mb-1"><strong>API URL:</strong> URL de Vholar con <code>/</code> al final</p>
                                <p class="mb-0"><strong>API Key:</strong> Tu clave personal de piloto (generada en tu <a href="{{ route('frontend.profile.index') }}">perfil</a>)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-body-secondary border-0 h-100">
                            <div class="card-body small">
                                <p class="fw-bold mb-1"><i class="bi bi-map text-success me-1"></i>SimBrief</p>
                                <p class="mb-0"><strong>SimBrief User:</strong> Tu nombre de usuario o Pilot ID de SimBrief (no el correo electrónico)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-body-secondary border-0 h-100">
                            <div class="card-body small">
                                <p class="fw-bold mb-1"><i class="bi bi-database text-warning me-1"></i>NavData API</p>
                                <p class="mb-1">Solicita la <strong>URL y API Key</strong> al staff de Vholar. Pulsa <code>TEST</code> para verificar. <span class="text-success">Verde</span> = OK, <span class="text-warning">naranja</span> = key inválida, <span class="text-danger">rojo</span> = sin conexión.</p>
                                <p class="mb-0 text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Sin NavData API, los criterios Touchdown Zone, Centreline, Localizer y Minimums no se evalúan.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-secondary d-flex gap-2 mb-0">
                    <i class="bi bi-book flex-shrink-0 mt-1"></i>
                    <div>El LOGBOOK local guarda el historial de aproximaciones con gráficos. Configúralo en Settings → Landing Log seleccionando o creando un archivo <code>.sqlite</code>. Puedes comparar hasta 5 aproximaciones simultáneamente.</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 3. Reglamentos --}}
<div class="row mb-4" id="sec-reglamentos">
    <div class="col-12">
        <div class="card border-start border-danger border-4">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle me-2"></i>3. Reglamentos Generales
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-success"><i class="bi bi-check-circle me-2"></i>Obligatorio</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-success"></i>Completar al menos <strong>1 vuelo por mes</strong> para mantener la membresía activa.</li>
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-success"></i>Usar <strong>vmsOpenAcars</strong> para todos los vuelos ACARS.</li>
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-success"></i>Generar el <strong>OFP en SimBrief</strong> antes del despacho.</li>
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-success"></i>Operar solamente <strong>aeronaves habilitadas</strong> para tu rango o type rating.</li>
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-success"></i>El callsign en red debe ser <code>VHR + número de vuelo</code> (ej: <code>VHR4865</code>).</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-danger"><i class="bi bi-x-circle me-2"></i>Prohibido</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-danger"></i>Acelerar el tiempo de simulación (<strong>time warp</strong>).</li>
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-danger"></i>Usar <strong>slew mode</strong> o repositionar la aeronave manualmente durante el vuelo.</li>
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-danger"></i>Conectarse a la red con <strong>callsign incorrecto</strong>.</li>
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-danger"></i>Compartir <strong>credenciales</strong> de acceso a phpVMS o vmsOpenAcars.</li>
                            <li class="list-group-item px-0 small"><i class="bi bi-dot text-danger"></i>Conducta irrespetuosa hacia otros pilotos, staff o controladores ATC.</li>
                        </ul>
                    </div>
                </div>

                <hr class="mt-3 mb-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card bg-body-secondary border-0 h-100">
                            <div class="card-body small">
                                <p class="fw-bold mb-1"><i class="bi bi-layout-text-sidebar-reverse text-primary me-1"></i>Portal Vholar (antes "Crew Center")</p>
                                <p class="mb-1">El <strong>OM-A</strong> hace referencia al "Crew Center" y a "SCHEDULES". Estos son ahora secciones del portal Vholar:</p>
                                <ul class="mb-0">
                                    <li>Rutas programadas → <a href="{{ url('/flights') }}">Despacho</a></li>
                                    <li>Asignaciones mensuales → <a href="{{ url('/dassignments') }}">Asignaciones</a></li>
                                    <li>NOTAMs de la compañía → <a href="{{ url('/dnotams') }}">NOTAMs</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-body-secondary border-0 h-100">
                            <div class="card-body small">
                                <p class="fw-bold mb-1"><i class="bi bi-laptop text-success me-1"></i>Software autorizado</p>
                                <p class="mb-1">El <strong>OM-A §10.4</strong> menciona "FDA-ACARS" como el software provisto por la aerolínea. Este ha sido reemplazado por <strong>vmsOpenAcars v0.7.8</strong>, que es el único cliente ACARS autorizado.</p>
                                <p class="mb-0 text-muted">Cualquier referencia a "FDA-ACARS" en documentos anteriores debe entenderse como <strong>vmsOpenAcars</strong>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 4. Procedimientos --}}
<div class="row mb-4" id="sec-procedimientos">
    <div class="col-12">
        <div class="card border-start border-primary border-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-list-check me-2"></i>4. Procedimientos de Vuelo (Flujo completo con vmsOpenAcars)
            </div>
            <div class="card-body p-0">
                <div class="accordion accordion-flush" id="procAccordion">

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#proc1">
                                <span class="badge bg-primary me-3">1</span> Login y selección de vuelo
                            </button>
                        </h2>
                        <div id="proc1" class="accordion-collapse collapse show" data-bs-parent="#procAccordion">
                            <div class="accordion-body small">
                                <ol class="mb-0">
                                    <li class="mb-1">Inicia el simulador y carga tu aeronave en el aeropuerto de salida.</li>
                                    <li class="mb-1">Abre vmsOpenAcars y haz clic en <strong>LOGIN</strong>. Tu nombre y aeropuerto aparecerán en el panel STATUS.</li>
                                    <li class="mb-1">Revisa los <a href="{{ url('/dnotams') }}"><strong>NOTAMs de la compañía</strong></a> antes de seleccionar tu vuelo — pueden incluir restricciones de ruta, aeronaves o aeropuertos.</li>
                                    <li class="mb-1">Haz clic en <strong>SIMBRIEF</strong> → pestaña <em>My Bids</em> (vuelos reservados) o <em>Available Flights</em> (todos los disponibles desde tu base actual).</li>
                                    <li class="mb-1">Selecciona el vuelo y la aeronave disponible.</li>
                                    <li class="mb-1">Haz clic en <strong>PLAN IN SIMBRIEF</strong> → ajusta y genera el OFP en SimBrief → regresa y pulsa <strong>FETCH OFP</strong> → <strong>ACCEPT</strong>.</li>
                                    <li class="mb-0"><strong>START</strong> se habilita cuando: simulador conectado, plan cargado y avión a menos de ~5 km del aeropuerto de salida.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#proc2">
                                <span class="badge bg-primary me-3">2</span> Boarding y Pushback
                            </button>
                        </h2>
                        <div id="proc2" class="accordion-collapse collapse" data-bs-parent="#procAccordion">
                            <div class="accordion-body small">
                                <ul class="mb-0">
                                    <li class="mb-1">Pulsar <strong>START</strong> inicia la fase <em>Boarding</em>. El FMA muestra una cuenta regresiva hasta el STD.</li>
                                    <li class="mb-1"><strong>Luces NAV</strong> encendidas desde que se energiza la aeronave.</li>
                                    <li class="mb-1"><strong>Luces BEACON</strong> encendidas antes del pushback o inicio de motores.</li>
                                    <li class="mb-1">Se permite inicio en <strong>motor único (Hotel Mode)</strong> en turbohélices — el ACARS detecta el modo automáticamente y no penaliza el BEACON mientras la hélice no gira.</li>
                                    <li class="mb-0">El ACARS registra el <strong>Block Off</strong> al iniciarse el primer movimiento de rodaje.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#proc3">
                                <span class="badge bg-primary me-3">3</span> Taxi Out
                            </button>
                        </h2>
                        <div id="proc3" class="accordion-collapse collapse" data-bs-parent="#procAccordion">
                            <div class="accordion-body small">
                                <ul class="mb-0">
                                    <li class="mb-1"><strong>Luces TAXI</strong> encendidas al iniciar el rodaje.</li>
                                    <li class="mb-1">El sistema monitoriza las luces. Una violación = <strong>−5 pts</strong> (máximo −10 pts por luces en todo el vuelo).</li>
                                    <li class="mb-1">Velocidad máxima: <strong>25 kt en calles, 10 kt en intersecciones y plataforma</strong>.</li>
                                    <li class="mb-1">Si usas <strong>motor único</strong> ≥ 50 % del tiempo de rodaje (TaxiOut o TaxiIn), el sistema otorga <span class="text-success fw-bold">+5 pts de bonificación</span>. Solo aplica en aeronaves multi-motor.</li>
                                    <li class="mb-1">La penalización de <strong>IVAO Offline (−5 pts)</strong> se evalúa al inicio de TaxiOut: conéctate a IVAO antes de mover el avión.</li>
                                    <li class="mb-0">El ACARS reporta al log: taxiways recorridos, holding points y pista en uso.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#proc4">
                                <span class="badge bg-primary me-3">4</span> Carrera de Despegue y Despegue
                            </button>
                        </h2>
                        <div id="proc4" class="accordion-collapse collapse" data-bs-parent="#procAccordion">
                            <div class="accordion-body small">
                                <ul class="mb-0">
                                    <li class="mb-1"><strong>Luces STROBE y LANDING</strong> encendidas al entrar en pista o recibir autorización de despegue. Si están apagadas al inicio de TakeoffRoll → <strong>−5 pts</strong>.</li>
                                    <li class="mb-1">Verificar el <strong>QNH ajustado al origen</strong> antes de la carrera. Si el Δ supera 2 hPa → <strong>−5 pts</strong>.</li>
                                    <li class="mb-1">El ACARS mide la <strong>desviación de centreline</strong> y la distancia al umbral — alinearse correctamente.</li>
                                    <li class="mb-1">Trigger de TakeoffRoll: velocidad de suelo <strong>> 30 kt</strong> con freno de parqueo liberado.</li>
                                    <li class="mb-0">Retraer el tren de aterrizaje tan pronto exista gradiente de ascenso positivo.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#proc5">
                                <span class="badge bg-primary me-3">5</span> Ascenso y Crucero (En Ruta)
                            </button>
                        </h2>
                        <div id="proc5" class="accordion-collapse collapse" data-bs-parent="#procAccordion">
                            <div class="accordion-body small">
                                <ul class="mb-0">
                                    <li class="mb-1">Ajustar a <strong>STD 1013 hPa</strong> al cruzar la altitud de transición (<strong>18 000 ft</strong> en Colombia).</li>
                                    <li class="mb-1">Apagar luces de <strong>LANDING</strong> por encima de <strong>9 500 ft AGL</strong>. Si están encendidas más allá de esa altitud → −5 pts.</li>
                                    <li class="mb-1"><strong>Luces BEACON</strong> encendidas durante todo el vuelo. Excepción: aeronaves con switch BEACON/STROBE combinado.</li>
                                    <li class="mb-1">El ACARS emite alertas OSD de <strong>espacios aéreos</strong>: predicción (3 min), sobrevuelo y entrada. Respétalas.</li>
                                    <li class="mb-0">La frecuencia de envío de posición en crucero es cada <strong>15 s</strong>; en approach cada <strong>5 s</strong>; en despegue/aterrizaje cada <strong>2 s</strong>.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#proc6">
                                <span class="badge bg-danger me-3">6</span> Aproximación — Gate 1 000 ft AGL
                            </button>
                        </h2>
                        <div id="proc6" class="accordion-collapse collapse" data-bs-parent="#procAccordion">
                            <div class="accordion-body">
                                <div class="alert alert-danger d-flex gap-2 mb-3 py-2">
                                    <i class="bi bi-exclamation-octagon-fill flex-shrink-0 mt-1"></i>
                                    <div class="small">El gate de <strong>1 000 ft AGL</strong> es el criterio más importante del vuelo. Al cruzarlo en descenso, el ACARS evalúa 7 parámetros simultáneamente. Una aproximación inestabilizada requiere go-around.</div>
                                </div>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered align-middle small mb-0">
                                        <thead class="table-dark"><tr><th>Criterio en 1 000 ft AGL</th><th>Penalización</th></tr></thead>
                                        <tbody>
                                            <tr><td>Velocidad fuera del rango Vapp ± tolerancia</td><td class="text-danger fw-bold">−5 pts <span class="text-muted fw-normal">(exento si ATC activo en COM1)</span></td></tr>
                                            <tr><td>VS excesivo (< −1 000 fpm)</td><td class="text-danger fw-bold">−5 pts</td></tr>
                                            <tr><td>No descendiendo (VS > −100 fpm)</td><td class="text-danger fw-bold">−5 pts</td></tr>
                                            <tr><td>Bank > 7°</td><td class="text-danger fw-bold">−3 pts</td></tr>
                                            <tr><td>Pitch fuera de [−2.5°, +10°]</td><td class="text-danger fw-bold">−3 pts</td></tr>
                                            <tr><td>Tren de aterrizaje no extendido</td><td class="text-danger fw-bold">−5 pts</td></tr>
                                            <tr><td>Flaps < 50 % de extensión</td><td class="text-danger fw-bold">−4 pts</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <ul class="small mb-0">
                                    <li class="mb-1">Ajustar <strong>QNH del destino</strong> antes de llegar a 1 000 ft AGL. Si Δ > 2 hPa → −5 pts adicionales.</li>
                                    <li class="mb-1">Sintonizar el <strong>ILS</strong> de la pista en COM/NAV. Si no está sintonizado → −3 pts (Localizer Alignment).</li>
                                    <li class="mb-0">No descender por debajo de la <strong>DA (Decision Altitude)</strong> sin aterrizar → −5 pts (Minimums Compliance). Si el procedimiento no es ILS, no se penaliza.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#proc7">
                                <span class="badge bg-primary me-3">7</span> Aterrizaje
                            </button>
                        </h2>
                        <div id="proc7" class="accordion-collapse collapse" data-bs-parent="#procAccordion">
                            <div class="accordion-body small">
                                <ul class="mb-0">
                                    <li class="mb-1">Aterrizar dentro de los primeros <strong>1 500 ft del umbral</strong> (Touchdown Zone) — más allá penaliza hasta −7 pts.</li>
                                    <li class="mb-1">Mantener la alineación de centreline: objetivo <strong>< 10 ft de desviación lateral</strong>.</li>
                                    <li class="mb-1">Tasa de descenso objetivo: <strong>≤ 150 fpm</strong> para calificación «Butter» (0 puntos deducidos).</li>
                                    <li class="mb-1">Factor de carga (G-Force): mantener ≤ 1.5 g — por encima de 1.7 g se penalizan −15 pts.</li>
                                    <li class="mb-1">Ángulo de bank al toque: ≤ 2° ideal; > 5° penaliza −10 pts.</li>
                                    <li class="mb-0">Activar <strong>reversas</strong> al toque y frenar suavemente. El ACARS registra el uso de reversas en el log.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#proc8">
                                <span class="badge bg-primary me-3">8</span> Taxi In y Bloqueo
                            </button>
                        </h2>
                        <div id="proc8" class="accordion-collapse collapse" data-bs-parent="#procAccordion">
                            <div class="accordion-body small">
                                <ul class="mb-0">
                                    <li class="mb-1">Desocupar pista por la primera calle de salida disponible.</li>
                                    <li class="mb-1">Apagar <strong>STROBE</strong> al salir de pista.</li>
                                    <li class="mb-1">Rodaje en <strong>motor único</strong> en TaxiIn también cuenta para la bonificación de +5 pts.</li>
                                    <li class="mb-1">Apagar motores en puerta y poner freno de parqueo — el ACARS registra el <strong>Block On</strong>.</li>
                                    <li class="mb-1">La fase pasa a <em>Completed</em>. Haz clic en <strong>SEND PIREP</strong> para enviar el resultado a phpVMS.</li>
                                    <li class="mb-0">Verifica en tu <a href="{{ url('/dpireps') }}">Logbook</a> que el PIREP aparezca con score correcto.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- 5. Puntuación --}}
<div class="row mb-4" id="sec-puntuacion">
    <div class="col-12">
        <div class="card border-start border-success border-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-trophy me-2"></i>5. Sistema de Puntuación
            </div>
            <div class="card-body">
                <p>Cada vuelo parte de <strong>100 puntos</strong>. Se aplican deducciones según <strong>14 criterios</strong> y una bonificación por taxi en motor único. El valor final (0–100) se envía con el PIREP.</p>

                {{-- Tabla de criterios --}}
                <h6 class="fw-bold mt-3 mb-2">Tabla de criterios (14 + 1 bonificación)</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle small">
                        <thead class="table-dark">
                            <tr><th>Criterio</th><th class="text-center">Máx. deducción</th><th>Regla de penalización</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Landing Rate</strong></td>
                                <td class="text-center text-danger fw-bold">−40 pts</td>
                                <td>≤ 150 fpm → 0 · ≤ 250 → −5 · ≤ 350 → −15 · ≤ 450 → −25 · ≤ 650 → −35 · > 650 → −40</td>
                            </tr>
                            <tr>
                                <td><strong>G-Force</strong></td>
                                <td class="text-center text-danger fw-bold">−15 pts</td>
                                <td>≤ 1.5 g → 0 · ≤ 1.7 g → −7 · > 1.7 g → −15</td>
                            </tr>
                            <tr>
                                <td><strong>Bank Angle</strong></td>
                                <td class="text-center text-danger fw-bold">−10 pts</td>
                                <td>≤ 2° → 0 · ≤ 5° → −5 · > 5° → −10</td>
                            </tr>
                            <tr>
                                <td><strong>Pitch Angle</strong></td>
                                <td class="text-center text-danger fw-bold">−10 pts</td>
                                <td>1°–7° nose-up → 0 (ideal) · < −2° → −10 · −2° a 1° → −5 · > 8° → −5</td>
                            </tr>
                            <tr>
                                <td><strong>Overspeed</strong></td>
                                <td class="text-center text-danger fw-bold">−15 pts</td>
                                <td>0 eventos → 0 · 1 evento → −7 · ≥ 2 eventos → −15 · <em>Exento si COM1 en ATC activo IVAO</em></td>
                            </tr>
                            <tr>
                                <td><strong>Lights Compliance</strong></td>
                                <td class="text-center text-danger fw-bold">−10 pts</td>
                                <td>−5 pts por violación, máximo −10. Ver detalle abajo.</td>
                            </tr>
                            <tr>
                                <td><strong>Stabilized Approach</strong></td>
                                <td class="text-center text-danger fw-bold">−15 pts</td>
                                <td>Evaluado al cruzar 1 000 ft AGL. Ver detalle en Procedimientos §6.</td>
                            </tr>
                            <tr>
                                <td><strong>QNH Compliance</strong></td>
                                <td class="text-center text-danger fw-bold">−10 pts</td>
                                <td>−5 pts si Δ > 2 hPa. Se verifica 2 veces: al inicio de TakeoffRoll (origen) y al gate 1 000 ft AGL (destino).</td>
                            </tr>
                            <tr>
                                <td><strong>IVAO Offline</strong></td>
                                <td class="text-center text-danger fw-bold">−5 pts</td>
                                <td>−5 si no estás conectado a IVAO al inicio del TaxiOut.</td>
                            </tr>
                            <tr>
                                <td><strong>On-Time Departure</strong></td>
                                <td class="text-center text-danger fw-bold">−5 pts</td>
                                <td>−5 si el Block Off real difiere más de ±10 min del STD del OFP.</td>
                            </tr>
                            <tr class="table-secondary">
                                <td><strong>Touchdown Zone</strong> <span class="badge bg-info text-dark ms-1">NavData</span></td>
                                <td class="text-center text-danger fw-bold">−7 pts</td>
                                <td>≤ 1 500 ft del umbral → 0 · ≤ 2 500 ft → −3 · > 2 500 ft → −7</td>
                            </tr>
                            <tr class="table-secondary">
                                <td><strong>Centreline Deviation</strong> <span class="badge bg-info text-dark ms-1">NavData</span></td>
                                <td class="text-center text-danger fw-bold">−7 pts</td>
                                <td>≤ 10 ft → 0 · ≤ 30 ft → −3 · > 30 ft → −7</td>
                            </tr>
                            <tr class="table-secondary">
                                <td><strong>Localizer Alignment</strong> <span class="badge bg-info text-dark ms-1">NavData</span></td>
                                <td class="text-center text-danger fw-bold">−5 pts</td>
                                <td>ILS no sintonizado → −3 · desviación de rumbo > 5° → −2 (máx ×2)</td>
                            </tr>
                            <tr class="table-secondary">
                                <td><strong>Minimums Compliance</strong> <span class="badge bg-info text-dark ms-1">NavData</span></td>
                                <td class="text-center text-danger fw-bold">−5 pts</td>
                                <td>−5 si el avión descendió por debajo de la DA sin aterrizar</td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>Single Engine Taxi</strong></td>
                                <td class="text-center text-success fw-bold">+5 pts</td>
                                <td>Bonus: rueda ≥ 50 % del tiempo en motor único (TaxiOut o TaxiIn). Score máximo: 100.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mb-4"><span class="badge bg-info text-dark">NavData</span> Requiere NavData API configurada en Settings. Sin ella, esos 4 criterios no se evalúan.</p>

                {{-- Luces --}}
                <h6 class="fw-bold mb-2">Detalle: Luces Compliance</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-dark"><tr><th>Momento</th><th>Luz requerida</th></tr></thead>
                        <tbody>
                            <tr><td>Pushback</td><td>NAV <span class="text-success fw-bold">ON</span></td></tr>
                            <tr><td>Inicio de rodaje (TaxiOut)</td><td>NAV <span class="text-success fw-bold">ON</span> + TAXI <span class="text-success fw-bold">ON</span></td></tr>
                            <tr><td>TakeoffRoll</td><td>STROBE <span class="text-success fw-bold">ON</span> + LANDING <span class="text-success fw-bold">ON</span></td></tr>
                            <tr><td>En vuelo (todas las fases)</td><td>BEACON <span class="text-success fw-bold">ON</span> continuo</td></tr>
                            <tr><td>Por debajo de 9 500 ft AGL</td><td>LANDING <span class="text-success fw-bold">ON</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-secondary small mb-4 py-2">
                    <strong>Excepción BEACON:</strong> aeronaves con switch BEACON/STROBE combinado (ej. Dash 8/Q400) están exentas. Turbohélices en Hotel Mode tampoco penalizan hasta que la hélice comienza a girar.
                </div>

                {{-- Calificaciones aterrizaje --}}
                <h6 class="fw-bold mb-2">Calificaciones de aterrizaje</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-dark"><tr><th>Calificación</th><th>Tasa de descenso</th><th>Deducción Landing Rate</th></tr></thead>
                        <tbody>
                            <tr class="table-success"><td>🧈 <strong>Butter</strong></td><td>≤ 150 fpm</td><td class="text-success fw-bold">0 pts</td></tr>
                            <tr class="table-success"><td>✅ <strong>Smooth</strong></td><td>151 – 250 fpm</td><td class="text-danger fw-bold">−5 pts</td></tr>
                            <tr><td>🟢 <strong>Normal</strong></td><td>251 – 350 fpm</td><td class="text-danger fw-bold">−15 pts</td></tr>
                            <tr class="table-warning"><td>🟡 <strong>Hard</strong></td><td>351 – 450 fpm</td><td class="text-danger fw-bold">−25 pts</td></tr>
                            <tr class="table-warning"><td>🟠 <strong>Very Hard</strong></td><td>451 – 650 fpm</td><td class="text-danger fw-bold">−35 pts</td></tr>
                            <tr class="table-danger"><td>🔴 <strong>Slam</strong></td><td>> 650 fpm</td><td class="text-danger fw-bold">−40 pts</td></tr>
                        </tbody>
                    </table>
                </div>

                {{-- Tips --}}
                <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb-fill text-warning me-1"></i>Consejos para score perfecto</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-dark"><tr><th>Aspecto</th><th>Qué hacer</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Luces</strong></td><td>NAV ON antes del pushback · TAXI ON al rodar · STROBE y LANDING ON en TakeoffRoll · BEACON ON siempre</td></tr>
                            <tr><td><strong>QNH salida</strong></td><td>Sintoniza el QNH del origen <em>antes</em> de comenzar la carrera de despegue</td></tr>
                            <tr><td><strong>IVAO</strong></td><td>Conéctate a IVAO <em>antes</em> de iniciar el TaxiOut</td></tr>
                            <tr><td><strong>Puntualidad</strong></td><td>Respeta el STD del OFP. La tolerancia es ±10 min</td></tr>
                            <tr><td><strong>Gate 1 000 ft</strong></td><td>Velocidad en Vapp · VS entre −100 y −1 000 fpm · bank < 7° · tren extendido · flaps ≥ 50 %</td></tr>
                            <tr><td><strong>QNH llegada</strong></td><td>Sintoniza el QNH del destino antes de llegar a 1 000 ft AGL</td></tr>
                            <tr><td><strong>Touchdown</strong></td><td>Primeros 1 500 ft de pista · alineado (< 10 ft de centreline) · apunta a ≤ 150 fpm</td></tr>
                            <tr><td><strong>Single engine</strong></td><td>Apaga un motor en rodaje para el bonus de +5 pts</td></tr>
                        </tbody>
                    </table>
                </div>

                {{-- Calificaciones finales --}}
                <h6 class="fw-bold mt-4 mb-2">Calificación final</h6>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-success fs-6 px-3 py-2">90–100 · Excelente</span>
                    <span class="badge bg-primary fs-6 px-3 py-2">80–89 · Muy Bueno</span>
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">70–79 · Bueno</span>
                    <span class="badge bg-danger fs-6 px-3 py-2">60–69 · Regular</span>
                    <span class="badge bg-secondary fs-6 px-3 py-2">&lt; 60 · Por mejorar</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 6. Redes --}}
<div class="row mb-4" id="sec-redes">
    <div class="col-12">
        <div class="card border-start border-info border-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-broadcast-pin me-2"></i>6. Redes de Vuelo en Línea
            </div>
            <div class="card-body">
                <p>Vholar opera exclusivamente en <strong>IVAO</strong>. Volar en línea evita la penalización de <strong>−5 pts por IVAO Offline</strong> y activa la exención de velocidad cuando hay ATC activo. Se requiere un mínimo de presencia en red para mantener el estatus activo.</p>
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="card border-0 bg-body-secondary h-100">
                            <div class="card-body small">
                                <h6 class="fw-bold"><i class="bi bi-globe me-2 text-info"></i>IVAO — Red oficial de Vholar</h6>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-1"><i class="bi bi-dot"></i>Callsign: <code>VHR + número de vuelo</code> (ej: <code>VHR4865</code>)</li>
                                    <li class="mb-1"><i class="bi bi-dot"></i>Conéctate <em>antes</em> de iniciar el TaxiOut para evitar la penalización de −5 pts.</li>
                                    <li class="mb-1"><i class="bi bi-dot"></i>Ingresa tu <strong>VID de IVAO</strong> en tu perfil de Vholar para el seguimiento automático de presencia.</li>
                                    <li class="mb-1"><i class="bi bi-dot"></i>El ACARS verifica tu presencia en IVAO al momento del boarding.</li>
                                    <li><i class="bi bi-dot"></i>Con ATC activo en COM1, las penalizaciones de Overspeed y Vapp a 1 000 ft quedan suprimidas.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-warning border-2 h-100">
                            <div class="card-body small">
                                <h6 class="fw-bold text-warning"><i class="bi bi-bar-chart-fill me-1"></i>Presencia mínima requerida</h6>
                                <p class="display-6 fw-bold text-center my-2">80%</p>
                                <p class="text-muted mb-0 text-center">de tus vuelos deben realizarse conectado a IVAO para mantener el estatus activo en la aerolínea.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 7. Rangos --}}
<div class="row mb-4" id="sec-rangos">
    <div class="col-12">
        <div class="card border-start border-warning border-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-bar-chart-steps me-2"></i>7. Rangos y Progresión
            </div>
            <div class="card-body">
                <p>La progresión es automática y basada en <strong>horas de vuelo acumuladas</strong>. Cada rango habilita nuevos tipos de aeronave. Los vuelos ACARS de rangos inferiores se aprueban automáticamente; los manuales requieren revisión del staff hasta rango Captain.</p>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover align-middle small">
                        <thead class="table-dark">
                            <tr><th>Rango</th><th class="text-center">Auto-aprobación ACARS</th><th class="text-center">Auto-aprobación Manual</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><i class="bi bi-airplane me-1 text-secondary"></i>Cadet</td><td class="text-center"><span class="badge bg-success">Sí</span></td><td class="text-center"><span class="badge bg-danger">No</span></td></tr>
                            <tr><td><i class="bi bi-airplane me-1 text-info"></i>Junior First Officer</td><td class="text-center"><span class="badge bg-success">Sí</span></td><td class="text-center"><span class="badge bg-danger">No</span></td></tr>
                            <tr><td><i class="bi bi-airplane me-1 text-primary"></i>Senior First Officer</td><td class="text-center"><span class="badge bg-success">Sí</span></td><td class="text-center"><span class="badge bg-danger">No</span></td></tr>
                            <tr><td><i class="bi bi-airplane-fill me-1 text-warning"></i>Captain</td><td class="text-center"><span class="badge bg-success">Sí</span></td><td class="text-center"><span class="badge bg-danger">No</span></td></tr>
                            <tr><td><i class="bi bi-airplane-fill me-1 text-success"></i>Senior Captain</td><td class="text-center"><span class="badge bg-success">Sí</span></td><td class="text-center"><span class="badge bg-success">Sí</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0">Los valores exactos de horas por rango están en <a href="{{ url('/dranks') }}">Rangos</a>. Los <strong>Type Ratings</strong> permiten acceso a tipos adicionales — contáctate con el staff para solicitarlos.</p>
            </div>
        </div>
    </div>
</div>

{{-- 8. Tipos de Operación --}}
<div class="row mb-4" id="sec-operaciones">
    <div class="col-12">
        <div class="card border-start border-primary border-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-grid me-2"></i>8. Tipos de Operación
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 bg-body-secondary border-0">
                            <div class="card-body small">
                                <h6 class="fw-bold"><i class="bi bi-airplane text-primary me-2"></i>Vuelos Regulares</h6>
                                <p class="text-muted mb-0">Rutas programadas. Selecciona desde el <a href="{{ url('/flights') }}">Centro de Despacho</a>. Disponibles también como Bids en vmsOpenAcars.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 bg-body-secondary border-0">
                            <div class="card-body small">
                                <h6 class="fw-bold"><i class="bi bi-rocket text-warning me-2"></i>Charter</h6>
                                <p class="text-muted mb-0">Vuelos bajo demanda entre cualquier par de aeropuertos. Precio dinámico por distancia. Desde <a href="{{ url('/vmsopenops/charter/create') }}">Crear Charter</a>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 bg-body-secondary border-0">
                            <div class="card-body small">
                                <h6 class="fw-bold"><i class="bi bi-truck text-info me-2"></i>Ferry</h6>
                                <p class="text-muted mb-0">Reposicionamiento de aeronaves entre bases. Sin pasajeros ni carga. Disponible en <a href="{{ url('/vmsopenops/ferry') }}">Ferry</a>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 bg-body-secondary border-0">
                            <div class="card-body small">
                                <h6 class="fw-bold"><i class="bi bi-arrow-left-right text-secondary me-2"></i>Jumpseat</h6>
                                <p class="text-muted mb-0">Traslado del piloto entre aeropuertos como pasajero para reposicionarte. Desde <a href="{{ url('/vmsopenops/jumpseat') }}">Jumpseat</a>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 bg-body-secondary border-0">
                            <div class="card-body small">
                                <h6 class="fw-bold"><i class="bi bi-calendar-check text-success me-2"></i>Asignaciones</h6>
                                <p class="text-muted mb-0">Misiones especiales con rutas, aeronaves y ventanas de tiempo definidas por el staff. Ver en <a href="{{ url('/dassignments') }}">Asignaciones</a>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 bg-body-secondary border-0">
                            <div class="card-body small">
                                <h6 class="fw-bold"><i class="bi bi-map text-danger me-2"></i>Tours</h6>
                                <p class="text-muted mb-0">Colecciones de vuelos temáticos. Completa todos los tramos para obtener el premio del tour. En <a href="{{ url('/dtours') }}">Tours</a>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 9. Conducta y Problemas --}}
<div class="row mb-4" id="sec-conducta">
    <div class="col-12">
        <div class="card border-start border-secondary border-4">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-person-check me-2"></i>9. Conducta y Solución de Problemas
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Conducta</h6>
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item px-0"><i class="bi bi-check2 text-success me-2"></i>Respetar a todos los miembros y controladores ATC en red.</li>
                            <li class="list-group-item px-0"><i class="bi bi-check2 text-success me-2"></i>Reportar errores del sistema al staff de forma respetuosa.</li>
                            <li class="list-group-item px-0"><i class="bi bi-x text-danger me-2"></i>No se tolera acoso, discriminación ni lenguaje ofensivo.</li>
                            <li class="list-group-item px-0"><i class="bi bi-x text-danger me-2"></i>El abuso de herramientas resulta en suspensión.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Problemas comunes con vmsOpenAcars</h6>
                        <div class="accordion accordion-flush" id="troubleAccordion">
                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#t1">
                                        El botón START no se habilita
                                    </button>
                                </h2>
                                <div id="t1" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                                    <div class="accordion-body small py-2">Verifica que: el simulador esté activo y FSUIPC conectado · tengas un plan cargado y aceptado · el avión esté a menos de ~5 km del aeropuerto de salida.</div>
                                </div>
                            </div>
                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#t2">
                                        FETCH OFP no encuentra el plan
                                    </button>
                                </h2>
                                <div id="t2" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                                    <div class="accordion-body small py-2">El plan no puede tener más de 2 horas de antigüedad · verifica el usuario de SimBrief en Settings · asegúrate de haber generado el OFP antes de hacer FETCH.</div>
                                </div>
                            </div>
                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#t3">
                                        Touchdown Zone y Centreline no se evalúan
                                    </button>
                                </h2>
                                <div id="t3" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                                    <div class="accordion-body small py-2">La API key de NavData no está configurada o es inválida. Ve a Settings → NavData API, introduce la key del staff y pulsa TEST.</div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#t4">
                                        El score es más bajo de lo esperado
                                    </button>
                                </h2>
                                <div id="t4" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                                    <div class="accordion-body small py-2">Revisa el log del vuelo en tu PIREP — cada penalización se registra en el momento exacto. Presta especial atención a QNH (salida y llegada), luces y el gate de 1 000 ft AGL.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Footer --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-body-secondary border-0">
            <div class="card-body text-center text-muted small">
                <p class="mb-1"><strong>Vholar Virtual Airlines</strong> · Briefing del Piloto · vmsOpenAcars v0.7.8 · Edición 2026</p>
                <p class="mb-0">Documento de uso interno para tripulantes. Para consultas, contacta al staff. <a href="{{ url('/page/oma') }}">Ver OM-A completo →</a></p>
            </div>
        </div>
    </div>
</div>

@endsection

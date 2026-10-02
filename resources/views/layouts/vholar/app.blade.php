<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no'
        name='viewport' />

    <title>
        @hasSection('title')
            @yield('title') | VHolar Virtual Airlines
        @else
            VHolar Virtual Airlines | Comunidad de Simulación Aérea
        @endif
    </title>
    <meta name="description" content="VHOLAR: ¡LA AEROLÍNEA VIRTUAL QUE LO TIENE TODO!. Tendrás acompañamiento de Pilotos reales de nuestros equipos">
    <meta property="og:title" content="VHolar Virtual Airlines">
    <meta property="og:description" content="Comunidad profesional de simulación aérea. Únete y vuela con nosotros.">
    <meta property="og:image" content="{{ url('/images/vholar_logoweb.png') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">

    <script>
        // Check for saved user preference, if any, on initial load
        (function() {
            if (localStorage.getItem('theme') === 'dark' || ((!localStorage.getItem('theme') || localStorage.getItem(
                    'theme') === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-bs-theme', "dark")
            }
        })();
    </script>

    {{-- Start of required lines block. DON'T REMOVE THESE LINES! They're required or might break things --}}
    <meta name="base-url" content="{!! url('') !!}">
    <meta name="api-key" content="{!! Auth::check() ? Auth::user()->api_key : '' !!}">
    <meta name="csrf-token" content="{!! csrf_token() !!}">
    {{-- End the required lines block --}}

    <link rel="shortcut icon" type="image/png" href="{{ public_asset('/images/favicon.png') }}" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cookieconsent/3.1.1/cookieconsent.min.css"
        integrity="sha512-LQ97camar/lOliT/MqjcQs5kWgy6Qz/cCRzzRzUCfv0fotsCTC9ZHXaPQmJV8Xu/PVALfJZ7BDezl5lW3/qBxg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css" />
    <link href="{{ public_asset('/assets/vendor/tomselect/tom-select.bootstrap5.css') }}" rel="stylesheet">

    {{-- Leaflet CSS --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    {{-- Leaflet JS --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    {{-- Configuración de Leaflet para imágenes --}}
    <script>
        // Forzar URLs correctas de imágenes
        L.Icon.Default.imagePath = 'https://unpkg.com/leaflet@1.9.4/dist/images/';
    </script>

    {{-- TOKENS VHOLAR · fuente unica de verdad. Debe ir ANTES que theme.css --}}
    <link href="{{ public_asset('/assets/themes/vholar/css/tokens.css') }}?v={{ time() }}" rel="stylesheet">
    {{-- TEMA VHOLAR CSS --}}
    <link href="{{ public_asset('/assets/themes/vholar/css/theme.css') }}?v={{ time() }}" rel="stylesheet">
    
<script>
    // Verificar los estilos aplicados
    setTimeout(function() {
        var cards = document.querySelectorAll('.flight-card');
        if(cards.length > 0) {
            var bg = window.getComputedStyle(cards[0]).backgroundColor;
            console.log('Color de fondo actual:', bg);
        }
    }, 1000);
</script>
    {{-- Start of the required files in the head block --}}
    {{-- <link href="{{ public_mix('/assets/global/css/vendor.css') }}" rel="stylesheet" /> --}}
    @yield('css')
    @yield('scripts_head')
    {{-- End of the required stuff in the head block --}}
</head>

<body class="@yield('body_class')">
    <!-- Navbar -->
    <div class="wrapper d-flex flex-column min-vh-100">
        @include('nav')
        <div class="body container flex-grow-1 pt-4">
            {{-- These should go where you want your content to show up --}}
            @include('flash.message')
            @yield('content')
            {{-- End the above block --}}

        </div>

        {{-- SECCIÓN DE LOGOS IVAO --}}
        <div style="background-color: var(--vh-bg); border-top: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05);">
            <div class="container py-4">
                <div class="row align-items-center justify-content-center g-4">
                    <div class="col-md-3 col-6 text-center">
                        <a href="https://www.ivao.aero" target="_blank" rel="noopener noreferrer">
                            <img src="{{ public_asset('/images/logos/ivao.png') }}" alt="IVAO" class="img-fluid" style="max-height: 65px; width: auto; filter: brightness(0.9);">
                        </a>
                    </div>
                    <div class="col-md-3 col-6 text-center">
                        <a href="https://co.ivao.aero" target="_blank" rel="noopener noreferrer">
                            <img src="{{ public_asset('/images/logos/ivao_co.png') }}" alt="IVAO Colombia" class="img-fluid" style="max-height: 100px; width: auto; filter: brightness(0.9);">
                        </a>
                    </div>
                    <div class="col-md-3 col-6 text-center">
                        <a href="https://www.ivao.aero" target="_blank" rel="noopener noreferrer">
                            <img src="{{ public_asset('/images/logos/ivao_va.png') }}" alt="IVAO Virtual Airlines" class="img-fluid" style="max-height: 65px; width: auto; filter: brightness(0.9);">
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <footer class="py-3 border-top" style="background-color: var(--vh-bg); border-color: rgba(255,255,255,0.05);">
            <div class="container d-flex flex-wrap justify-content-between align-items-center">
                <div class="col-md-4 d-flex align-items-center">
                    <span class="mb-3 mb-md-0 text-body-secondary">Copyright {{ date('Y') }}
                        {{ config('app.name') }}</span>
                </div>
                <div class="col-md-4 d-flex align-items-center justify-content-end">
                    <span class="mb-3 mb-md-0 text-body-secondary text-end">Powered by <a href="https://www.phpvms.net"
                            target="_blank">phpVMS</a> {{ vholar_versions() }}</span>
                </div>
            </div>
        </footer>
    </div>

    {{-- External Redirects Modal --}}
    @include('external_redirect_modal')

    {{-- BOOTSTRAP 5 BUNDLE --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    {{-- INICIALIZACIÓN DE COMPONENTES BOOTSTRAP --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar todos los dropdowns
            var dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
            dropdownElements.forEach(function(element) {
                new bootstrap.Dropdown(element);
            });
            
            // Inicializar el colapso del navbar (menú hamburguesa)
            var navbarToggler = document.querySelector('.navbar-toggler');
            var navbarCollapse = document.querySelector('#navbarSupportedContent');
            if (navbarToggler && navbarCollapse) {
                // Crear instancia de collapse
                var bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                    toggle: false
                });
                
                // Manejar clic en el botón toggler
                navbarToggler.addEventListener('click', function() {
                    bsCollapse.toggle();
                });
            }
            
            // Inicializar todos los popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            console.log('✅ Bootstrap inicializado:', {
                dropdowns: dropdownElements.length,
                popovers: popoverList.length,
                navbarCollapse: !!navbarCollapse
            });
        });
    </script>
    
    {{-- Tom Select --}}
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/js/tom-select.complete.min.js"></script>

    {{-- Start of the required tags block --}}
    <script src="{{ public_mix('/assets/global/js/vendor.js') }}"></script>
    {{-- NO se carga /assets/frontend/js/vendor.js: ese bundle lleva Bootstrap 4.3.1
         (junto a moment y Popper 1.x) y Bootstrap 5 ya viene del CDN de arriba.
         Tener las dos librerias en la misma pagina duplicaba el JS y hacia que
         cualquier llamada jQuery tipo $(...).modal() ejecutase la implementacion
         de Bootstrap 4 contra markup de Bootstrap 5.
         El bundle SIGUE existiendo porque el tema del nucleo `beta` (Bootstrap 4)
         si lo necesita; simplemente este tema ya no lo usa. --}}
    <script src="{{ public_mix('/assets/frontend/js/app.js') }}"></script>
    @yield('scripts')
    @stack('scripts')

    {{-- Theme switcher --}}
    @include('scripts.bs_theme')

    {{-- Cookie Consent --}}
    <script>
        window.addEventListener("load", function() {
            if (typeof window.cookieconsent !== 'undefined') {
                window.cookieconsent.initialise({
                    palette: {
                        /* Oscurecido: el banner claro daba 3.25:1 (#838391 sobre #edeff5), por debajo
                           de AA, y era la unica superficie clara que quedaba en el tema. */
                        popup: { background: "#28212F", text: "#A79FB2" },
                        button: { background: "#412C4D" }
                    },
                    position: "top",
                });
            }
        });
    </script>

    {{-- Google Analytics --}}
    @php $gtag = setting('general.google_analytics_id'); @endphp
    @if ($gtag)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gtag }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '{{ $gtag }}');
        </script>
    @endif

    {{-- UTC Date/Time Script --}}
    <script>
        function updateUTCDateTime() {
            const now = new Date();
            const day = String(now.getUTCDate()).padStart(2, '0');
            const hour = String(now.getUTCHours()).padStart(2, '0');
            const minute = String(now.getUTCMinutes()).padStart(2, '0');
            const metarTime = `${day}${hour}${minute}Z`;
            const dateElement = document.getElementById('utc-datetime-text');
            if (dateElement) dateElement.textContent = metarTime;
        }
        updateUTCDateTime();
        setInterval(updateUTCDateTime, 1000);
    </script>

{{-- Toast Notification System --}}
<script>
    // Sistema de notificaciones Toast
    const Toast = {
        container: null,
        
        init() {
            this.container = document.getElementById('toast-container');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'toast-container';
                this.container.id = 'toast-container';
                document.body.appendChild(this.container);
            }
        },
        
        show(message, type = 'info', title = null) {
            this.init();
            
            const titles = {
                success: 'Éxito',
                danger: 'Error',
                warning: 'Advertencia',
                info: 'Información'
            };
            
            const icons = {
                success: 'bi bi-check-circle-fill',
                danger: 'bi bi-exclamation-triangle-fill',
                warning: 'bi bi-exclamation-circle-fill',
                info: 'bi bi-info-circle-fill'
            };
            
            const toastTitle = title || titles[type] || 'Información';
            const icon = icons[type] || icons.info;
            
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="${icon}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${toastTitle}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.closest('.toast-notification').remove()">
                    <i class="bi bi-x"></i>
                </button>
            `;
            
            this.container.appendChild(toast);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.add('fade-out');
                    setTimeout(() => {
                        if (toast.parentNode) toast.remove();
                    }, 300);
                }
            }, 5000);
            
            return toast;
        },
        
        success(message, title = null) {
            return this.show(message, 'success', title);
        },
        
        error(message, title = null) {
            return this.show(message, 'danger', title);
        },
        
        warning(message, title = null) {
            return this.show(message, 'warning', title);
        },
        
        info(message, title = null) {
            return this.show(message, 'info', title);
        }
    };
    
    // Inicializar al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        Toast.init();
    });
</script>

    {{-- Modal de Confirmación para Eliminar Bid --}}
    <div class="modal fade vholar-confirm-modal" id="confirmRemoveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Confirmar cancelación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="confirm-icon">
                        <i class="bi bi-question-circle"></i>
                    </div>
                    <div class="confirm-title">¿Cancelar esta reserva?</div>
                    <div class="confirm-message">
                        Esta acción eliminará la reserva del vuelo seleccionado.
                        ¿Estás seguro de que deseas continuar?
                    </div>
                    <div class="flight-details-confirm" id="confirmFlightDetails">
                        <div class="flight-ident">--</div>
                        <div class="flight-route">-- → --</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </button>
                    <button type="button" class="btn-confirm" id="confirmRemoveBtn">
                        <i class="bi bi-trash"></i> Sí, cancelar reserva
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast Notifications Container --}}
    <div class="toast-container" id="toast-container"></div>

{{-- Silenciar errores de addEventListener en elementos nulos --}}
<script>
    // Capturar y silenciar errores específicos de addEventListener
    window.addEventListener('error', function(e) {
        if (e.message && e.message.includes('addEventListener') && e.message.includes('null')) {
            e.preventDefault();
            e.stopPropagation();
            console.warn('Silenciado: intento de addEventListener en elemento nulo');
            return false;
        }
    });
    
    // También capturar promesas rechazadas
    window.addEventListener('unhandledrejection', function(e) {
        if (e.reason && e.reason.message && e.reason.message.includes('payload')) {
            e.preventDefault();
            console.warn('Silenciado: error de payload en core.js');
            return false;
        }
    });
</script>

{{-- Modal de información de vuelo - Estilo FlightRadar24 --}}
<div class="modal fade flight-modal" id="flightInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-airplane-fill" style="color: var(--vh-info);"></i> FLIGHT INFORMATION
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="flightInfoModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading flight data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Close
                </button>
                <a href="#" id="flightInfoModalLink" class="btn btn-primary" target="_blank">
                    <i class="bi bi-file-text"></i> Full Report
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Global Vholar confirm modal (Bootstrap 5) --}}
<div class="modal fade" id="vhConfirmModal" tabindex="-1" aria-labelledby="vhConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="background:var(--vh-surface);border:1px solid var(--vh-border);color:var(--vh-text);">
            <div class="modal-header" style="background:var(--vh-surface-2);border-bottom:1px solid var(--vh-border);">
                <h5 class="modal-title" id="vhConfirmTitle" style="color:var(--vh-text);">Confirmar acción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="vhConfirmMessage" style="color:var(--vh-text);margin:0;"></p>
            </div>
            <div class="modal-footer" style="background:var(--vh-surface-2);border-top:1px solid var(--vh-border);">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="vhConfirmBtn">
                    <i class="bi bi-check-lg"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var _form = null, _name = null, _value = null;
    var _modal = null;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-vh-confirm]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        _form  = btn.closest('form');
        _name  = btn.getAttribute('name')  || null;
        _value = btn.getAttribute('value') || null;
        document.getElementById('vhConfirmMessage').textContent = btn.dataset.vhConfirm || '¿Estás seguro?';
        document.getElementById('vhConfirmTitle').textContent   = btn.dataset.vhConfirmTitle || 'Confirmar acción';
        _modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('vhConfirmModal'));
        _modal.show();
    });

    document.addEventListener('click', function (e) {
        if (e.target.id !== 'vhConfirmBtn') return;
        if (_modal) _modal.hide();
        if (_form) {
            if (_name) {
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = _name; h.value = _value || '';
                _form.appendChild(h);
            }
            _form.submit();
        }
        _form = null; _name = null; _value = null;
    });
})();
</script>

</body>

</html>
<!DOCTYPE html>
<html lang="en">

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cookieconsent/3.1.1/cookieconsent.min.css"
        integrity="sha512-LQ97camar/lOliT/MqjcQs5kWgy6Qz/cCRzzRzUCfv0fotsCTC9ZHXaPQmJV8Xu/PVALfJZ7BDezl5lW3/qBxg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css" />
    <link href="{{ public_asset('/assets/vendor/tomselect/tom-select.bootstrap5.css') }}" rel="stylesheet">

    {{-- Start of the required files in the head block --}}
    {{-- <link href="{{ public_mix('/assets/global/css/vendor.css') }}" rel="stylesheet" /> --}}
    @yield('css')
    @yield('scripts_head')
    {{-- End of the required stuff in the head block --}}

     <style>
        :root {
            --bs-primary: #067ec1 !important;
            --primary-color: #412c4d;
            --primary-hover: #5e3e73;
            --bg-dark: #1e1623;
            --vh-primary: #412c4d;
            --vh-dark: #1e1b24;
            --vh-light: #f4f2f6;
        }
        /* Fondo general */
        body {
            background-color: var(--vh-dark);
            color: #e5e5e5;
        }
        /* Navbar */
        .navbar {
            background-color: #412c4d !important;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding-top: 6px !important;
            padding-bottom: 6px !important;
            min-height: 55px;
        }
        /* Logo */
        .navbar-brand img {
            height: 38px;
        }        
        /* Links navbar */
        .navbar .nav-link {
            color: rgba(255,255,255,0.75) !important;
            padding: 6px 12px !important;
            font-size: 14px;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
        }
        /* Hover elegante */
        .navbar .nav-link:hover {
            opacity: 1;
            transform: translateY(-1px);
        }
        /* Línea inferior sutil */
        .navbar {
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        /* Efecto blur ligero (opcional, moderno) */
        .navbar {
            backdrop-filter: blur(6px);
        }        
        .navbar .nav-link:hover,
        .navbar .nav-link.active {
            color: #ffffff !important;
        }
        /* Botón VHolar */
        .btn-primary {
            background-color: #412c4d !important;
            border-color: #412c4d !important;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 10px 18px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #563a63 !important;
            border-color: #563a63 !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(65,44,77,0.4);
        }
        /* Inputs VHolar */
        .form-control {
            background-color: #1f1c27 !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            color: #fff !important;
            border-radius: 8px;
            padding: 10px 12px;
        }

        .form-control:focus {
            border-color: #412c4d !important;
            box-shadow: 0 0 0 0.2rem rgba(65,44,77,0.3) !important;
        }        
        /* Labels */
        .form-label,
        label {
            color: #bbb !important;
            font-weight: 500;
        }

        /* Links */
        a {
            color: #c9a6db;
            text-decoration: none;
        }

        a:hover {
            color: #ffffff;
        }
        /* Card login */
        .auth-card,
        .card {
            background-color: #2a2633 !important;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        } 
        body.login-page {
            background: linear-gradient(rgba(30,27,36,0.9), rgba(30,27,36,0.95)),
                        url('/images/banner.jpg');
            background-size: cover;
            background-position: center;
        }               

        /* Footer */
        footer {
            background-color: #16131c;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .bg-primary {
            background-color: var(--primary-color) !important;
        }

                /* Dropdown oscuro VHolar */
        .dropdown-menu {
            background-color: #2a2633 !important;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 8px 0;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            backdrop-filter: blur(6px);
        }

        .dropdown-item {
            color: #ddd !important;
            font-weight: 500;
        }

        .dropdown-item:hover,
        .dropdown-item:focus {
            background-color: #412c4d !important;
            color: #ffffff !important;
        }

        /* Separador */
        .dropdown-divider {
            border-color: rgba(255,255,255,0.05);
        }
        /* Fix dropdown sobre cards */
        .navbar {
            position: relative;
            z-index: 1050;
        }

        .dropdown-menu {
            z-index: 2000 !important;
            position: absolute;
        }


    </style>
</head>

<body>
    <!-- Navbar -->
    <div class="wrapper d-flex flex-column min-vh-100">
        @include('nav')
        <div class="body container flex-grow-1 pt-4">
            {{-- These should go where you want your content to show up --}}
            @include('flash.message')
            @yield('content')
            {{-- End the above block --}}

        </div>
        <footer class="py-3 mt-4 border-top">
            <div class="container d-flex flex-wrap justify-content-between align-items-center">

                <div class="col-md-4 d-flex align-items-center">
                    <span class="mb-3 mb-md-0 text-body-secondary">Copyright {{ date('Y') }}
                        {{ config('app.name') }}</span>
                </div>
                <div class="col-md-4 d-flex align-items-center justify-content-end">
                    <span class="mb-3 mb-md-0 text-body-secondary text-end">Powered by <a href="https://www.phpvms.net"
                            target="_blank">phpVMS</a></span>
                </div>
            </div>
        </footer>
    </div>

    {{-- External Redirects Modal --}}
    @include('external_redirect_modal')

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/js/tom-select.complete.min.js"></script>

    <script>
        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]')
        const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl))
    </script>

    {{-- Start of the required tags block. Don't remove these or things will break!! --}}
    <script src="{{ public_mix('/assets/global/js/vendor.js') }}"></script>
    <script src="{{ public_mix('/assets/frontend/js/vendor.js') }}"></script>
    <script src="{{ public_mix('/assets/frontend/js/app.js') }}"></script>
    @yield('scripts')

    {{-- This is the color theme switcher --}}
    @include('scripts.bs_theme')

    {{--
It's probably safe to keep this to ensure you're in compliance
with the EU Cookie Law https://privacypolicies.com/blog/eu-cookie-law
--}}
    <script>
        window.addEventListener("load", function() {
            window.cookieconsent.initialise({
                palette: {
                    popup: {
                        background: "#edeff5",
                        text: "#838391"
                    },
                    button: {
                        "background": "#067ec1"
                    }
                },
                position: "top",
            })
        });
    </script>
    {{-- End the required tags block --}}

    {{--
Google Analytics tracking code. Only active if an ID has been entered
You can modify to any tracking code and re-use that settings field, or
just remove it completely. Only added as a convenience factor
--}}
    @php
        $gtag = setting('general.google_analytics_id');
    @endphp
    @if ($gtag)
        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gtag }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', new Date());

            gtag('config', '{{ $gtag }}');
        </script>
    @endif
    {{-- End of the Google Analytics code --}}

</body>

</html>

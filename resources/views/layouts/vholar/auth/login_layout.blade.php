<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport' />

  <title>@yield('title') - Vholar</title>
  <script>
    // Check for saved user preference, if any, on initial load
    (function() {
      if (localStorage.getItem('theme') === 'dark' || ((!localStorage.getItem('theme') || localStorage.getItem('theme') === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.setAttribute('data-bs-theme', "dark")
      }
    })();
  </script>

  {{-- Start of required lines block. DON'T REMOVE THESE LINES! They're required or might break things --}}
  <meta name="base-url" content="{!! url('') !!}">
  <meta name="api-key" content="{!! Auth::check() ? Auth::user()->api_key : '' !!}">
  <meta name="csrf-token" content="{!! csrf_token() !!}">
  {{-- End the required lines block --}}

  <!-- Favicon VHolar -->
  <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon.png">
  <meta name="theme-color" content="#412c4d">  
  <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css" />
  <link href="{{ public_asset('/assets/vendor/tomselect/tom-select.bootstrap5.css') }}" rel="stylesheet">
  <style>
    :root {
        --vh-primary: #412c4d;
        --vh-dark: #1e1b24;
    }

    /* Fondo */
    body {
        background: linear-gradient(rgba(30,27,36,0.9), rgba(30,27,36,0.95)),
                    url('/images/banner.jpg');
        background-size: cover;
        background-position: center;
        color: #e5e5e5;
    }

    /* Card */
    .card {
        background-color: #2a2633;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.05);
        box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    }

    /* Botón */
    .btn-primary {
        background-color: #412c4d !important;
        border-color: #412c4d !important;
        font-weight: 600;
        border-radius: 8px;
    }

    .btn-primary:hover {
        background-color: #563a63 !important;
        border-color: #563a63 !important;
    }

    /* Inputs */
    .form-control {
        background-color: #1f1c27 !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
        color: #fff !important;
        border-radius: 8px;
    }

    .form-control:focus {
        border-color: #412c4d !important;
        box-shadow: 0 0 0 0.2rem rgba(65,44,77,0.3) !important;
    }

    /* Labels */
    label {
        color: #bbb;
    }
    /* Links login VHolar */
    .link {
        color: #c9a6db !important;
        text-decoration: none;
        font-weight: 500;
    }

    .link:hover {
        color: #ffffff !important;
        text-decoration: underline;
    }

    .bg-brand {
      background-color: #067EC1;
    }
    [data-bs-theme="dark"] .bg-brand {
      background-color: var(--bs-body-bg);
    }
    .logo-light {
      display: block;
    }
    .logo-dark {
      display: none;
    }
    [data-bs-theme="dark"] .logo-light {
      display: none;
    }
    [data-bs-theme="dark"] .logo-dark {
      display: block;
    }



  </style>
  @yield('css')
</head>

<body class="bg-brand">
  <div class="wrapper d-flex flex-column min-vh-100">
    <div class="body container flex-grow-1 pt-4">
        {{-- These should go where you want your content to show up --}}
        @include('flash.message')
        @yield('content')
        {{-- End the above block --}}

    </div>
    <footer class="py-3 mt-4 text-white">
        <div class="container d-flex flex-wrap justify-content-between align-items-center">

            <div class="col-md-4 d-flex align-items-center">
                <span class="mb-3 mb-md-0">Copyright {{ date('Y') }}
                    {{ config('app.name') }}</span>
            </div>
            <div class="col-md-4 d-flex align-items-center justify-content-end">
                <span class="mb-3 mb-md-0 text-end">Powered by <a style="color: orange" href="https://www.phpvms.net"
                        target="_blank">phpVMS</a></span>
            </div>
        </div>
    </footer>
</div>
</body>

<script src="{{ public_asset('/assets/global/js/jquery.js') }}" type="text/javascript"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+0i5q5Y5n5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+5z5+

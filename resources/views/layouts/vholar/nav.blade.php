<nav class="navbar navbar-expand-lg bg-primary shadow-sm" data-bs-theme="dark">
    <div class="container-fluid">

        {{-- Logo --}}
        <a class="navbar-brand" href="{{ url('/') }}">
            <img src="{{ public_asset('images/vholar_logoweb.png') }}" height="38" alt="Vholar">
        </a>

        {{-- Mobile Toggle --}}
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav ms-auto align-items-lg-center">

                {{-- ================= PUBLIC LINKS ================= --}}
                {{-- Dropdown: Operations Centre --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="operationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear"></i> @lang('common.operations_centre')
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="operationsDropdown">
                        @auth
                            <li><a class="dropdown-item" href="{{ url('/dtours') }}"><i class="bi bi-map"></i> @lang('common.tours')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/flights') }}"><i class="bi bi-airplane"></i> @lang('common.dispatch')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/vmsopenops/charter/create') }}"><i class="bi bi-rocket"></i> @lang('common.charter')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/flights/bids') }}"><i class="bi bi-airplane"></i> @lang('flights.mybid')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/dassignments') }}"><i class="bi bi-calendar"></i> @lang('common.assignments')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/dhubs') }}"><i class="bi bi-building"></i> @lang('common.hub')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/vmsopenops/jumpseat') }}"><i class="bi bi-arrow-left-right"></i> Jumpseat</a></li>
                            <li><a class="dropdown-item" href="{{ url('/vmsopenops/ferry') }}"><i class="bi bi-truck"></i> Ferry</a></li>
                            <li><a class="dropdown-item" href="{{ url('/vmsopenops/stats') }}"><i class="bi bi-table"></i> @lang('common.stats')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/dpireps') }}"><i class="bi bi-journal-check"></i> @lang('common.logbook')</a></li>
                        @endauth
                        <li><a class="dropdown-item" href="{{ url('/fleetgrid') }}"><i class="bi bi-airplane-engines"></i> @lang('common.fleet')</a></li>
                        <li><a class="dropdown-item" href="{{ url('/fleetmap') }}"><i class="bi bi-map"></i> @lang('common.fleet_map')</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('frontend.livemap.index') }}"><i class="bi bi-globe"></i> @lang('common.livemap')</a></li>
                    </ul>
                </li>

                {{-- Dropdown: About Us --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="aboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-info-circle"></i> @lang('common.about_us')
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="aboutDropdown">
                        <li><a class="dropdown-item" href="{{ url('/page/airline-history') }}"><i class="bi bi-journal-bookmark-fill"></i> @lang('common.airline_page')</a></li>
                        <li><a class="dropdown-item" href="{{ url('/page/staff') }}"><i class="bi bi-people"></i> @lang('common.staff')</a></li>
                        <li><a class="dropdown-item" href="{{ route('frontend.users.index') }}"><i class="bi bi-person-lines-fill"></i> {{ trans_choice('common.pilot', 2) }}</a></li>
                        @auth
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ url('/dranks') }}"><i class="bi bi-trophy"></i> @lang('common.ranks')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/dstats') }}"><i class="bi bi-diagram-3"></i> @lang('common.stats')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/dawards') }}"><i class="bi bi-award"></i> @lang('common.airline_awards')</a></li>
                        @endauth
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ url('/register') }}"><i class="bi bi-person-plus"></i> @lang('common.register')</a></li>
                    </ul>
                </li>

                {{-- Crew Centre (direct link) --}}
                @guest
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('frontend.dashboard.index') }}">
                            <i class="bi bi-person-badge"></i> @lang('common.crew_centre')
                        </a>
                    </li>
                @endguest

                {{-- ================= GUEST ================= --}}
                @guest
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/register') }}">
                            <i class="bi bi-person-plus"></i> @lang('common.register')
                        </a>
                    </li>
                @endguest

                {{-- ================= AUTH USER ================= --}}
                @auth
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('frontend.dashboard.index') }}">
                            <i class="bi bi-speedometer2"></i> @lang('common.dashboard')
                        </a>
                    </li>
                    {{-- Dropdown: Nombre del Piloto (reemplaza los enlaces individuales) --}}
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="pilotDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            @if (Auth::user()->avatar)
                                <img src="{{ Auth::user()->avatar->url }}" class="rounded-circle" style="height:28px;width:28px;">
                            @else
                                <img src="{{ public_asset('images/vholar_logoweb.png') }}" class="rounded-circle bg-white p-1" style="height:28px;width:28px;">
                            @endif
                            <span>{{ Auth::user()->ident }} ({{ Auth::user()->name_private }})</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="pilotDropdown">
                            <li><a class="dropdown-item" href="{{ url('/page/oma') }}"><i class="bi bi-file-text"></i> OM-A</a></li>
                            <li><a class="dropdown-item" href="{{ route('vholar.briefing') }}"><i class="bi bi-journal-richtext"></i> Briefing del Piloto</a></li>
                            <li><a class="dropdown-item" href="{{ url('/page/wallet') }}"><i class="bi bi-wallet2"></i> @lang('common.wallet')</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('frontend.profile.index') }}"><i class="bi bi-person"></i> @lang('common.profile')</a></li>
                            <li><a class="dropdown-item" href="{{ url('/dawards/user') }}"><i class="bi bi-award"></i> @lang('common.my_awards')</a></li>
                            <li><a class="dropdown-item" href="{{ route('vmsopenfilemanager.liveries.index') }}"><i class="bi bi-download"></i> @lang('common.liveries')</a></li>
                            <li><a class="dropdown-item" href="{{ route('vmsopenfilemanager.downloads.index') }}"><i class="bi bi-download"></i> {{ trans_choice('common.download', 2) }}</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ url('/logout') }}"><i class="bi bi-box-arrow-right"></i> @lang('common.logout')</a></li>
                        </ul>
                    </li>

                    {{-- Admin link (solo para administradores) --}}
                    @can('access_admin')
                        <li class="nav-item ms-lg-1">
                            <a class="nav-link" href="{{ url('/admin') }}">
                                <i class="bi bi-gear-fill"></i> @lang('common.administration')
                            </a>
                        </li>
                    @endcan

                @endauth

                {{-- ================= FECHA Y HORA UTC (FORMATO METAR) ================= --}}
                <li class="nav-item ms-lg-2" id="utc-datetime" style="min-width: 100px;">
                    <span class="nav-link text-white">
                        <i class="bi bi-clock"></i> 
                        <span id="utc-datetime-text">--:--Z</span>
                    </span>
                </li>

                {{-- ===== Language Switcher ===== --}}
                <li class="nav-item dropdown ms-lg-3">
                    <a class="nav-link dropdown-toggle"
                       href="#"
                       data-bs-toggle="dropdown">
                        <span class="fi fi-{{ $languages[$locale]['flag-icon'] }}"></span>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        @foreach ($languages as $lang => $language)
                            @if ($lang != $locale)
                                <li>
                                    <a class="dropdown-item"
                                       href="{{ route('frontend.lang.switch', $lang) }}">
                                        <span class="fi fi-{{ $language['flag-icon'] }}"></span>
                                        {{ $language['display'] }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </li>

            </ul>
        </div>
    </div>
</nav>
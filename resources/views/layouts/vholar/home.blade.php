@extends('app')
@section('title', __('common.dashboard'))
@section('body_class', 'home-page')

@section('content')
    </div> {{-- cierra .body.container --}}

    {{-- CARRUSEL --}}
    <div id="myCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#myCarousel" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#myCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#myCarousel" data-bs-slide-to="2"></button>
            <button type="button" data-bs-target="#myCarousel" data-bs-slide-to="3"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="{{ public_asset('/images/slider/banner1.jpg') }}" class="d-block w-100" style="max-height: 500px; object-fit: cover;">
                <div class="carousel-caption d-none d-md-block">
                    <h3 class="fw-bold text-white">BIENVENIDO A VHOLAR</h3>
                    <p><a href="#features" class="scrollto text-warning">Comienza tu carrera <i class="bi bi-arrow-down"></i></a></p>
                </div>
            </div>
            <div class="carousel-item">
                <img src="{{ public_asset('/images/slider/banner2.jpg') }}" class="d-block w-100" style="max-height: 500px; object-fit: cover;">
                <div class="carousel-caption d-none d-md-block">
                    <h3 class="fw-bold text-white">CONTAMOS CON UNA FLOTA MODERNA</h3>
                    <p>Análisis de Datos de Vuelo (FDA) avanzado</p>
                </div>
            </div>
            <div class="carousel-item">
                <img src="{{ public_asset('/images/slider/banner3.jpg') }}" class="d-block w-100" style="max-height: 500px; object-fit: cover;">
                <div class="carousel-caption d-none d-md-block">
                    <h3 class="fw-bold text-white">CONECTAMOS A COLOMBIA CON EL MUNDO</h3>
                    <p>Integración con SimBrief</p>
                </div>
            </div>
            <div class="carousel-item">
                <img src="{{ public_asset('/images/slider/banner4.jpg') }}" class="d-block w-100" style="max-height: 500px; object-fit: cover;">
                <div class="carousel-caption d-none d-md-block">
                    <h3 class="fw-bold text-white">ASESORÍA CON PILOTOS REALES</h3>
                    <p>Airbus, ATR, Dash, Beechcraft y más</p>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#myCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#myCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>

    {{-- TARJETAS --}}
    {{-- TARJETAS DE ESTADÍSTICAS con fondo claro --}}
<div class="py-4" style="background-color: #F8F5FA;">
    <div class="container">
        <div class="row g-3">
            {{-- Total Flights --}}
            <div class="col-6 col-md-3">
                <div class="card text-center py-2 shadow-sm" style="background-color: #F3EFF5; border: 1px solid rgba(65,44,77,0.15); border-radius: 12px;">
                    <div class="card-body py-2">
                        <h5 class="mb-2" style="color: #5a4a66;">@lang('home.total_flights')</h5>
                        <h3 class="fw-bold mb-1" style="color: #2c1e35;">
                            <span class="counter" data-target="{{ $totalFlightsAllTime ?? 0 }}">0</span>
                        </h3>
                        <small style="color: #7b6a87;">
                            <span class="counter-small" data-target="{{ $totalFlightsThisMonth ?? 0 }}">0</span> @lang('home.flights_this_month')
                        </small>
                    </div>
                </div>
            </div>

            {{-- Total Distance --}}
            <div class="col-6 col-md-3">
                <div class="card text-center py-2 shadow-sm" style="background-color: #F3EFF5; border: 1px solid rgba(65,44,77,0.15); border-radius: 12px;">
                    <div class="card-body py-2">
                        <h5 class="mb-2" style="color: #5a4a66;">@lang('home.total_distance')</h5>
                        <h3 class="fw-bold mb-1" style="color: #2c1e35;">
                            <span class="counter" data-target="{{ $totalDistanceAllTime ?? 0 }}">0</span>
                            <small style="font-size: 0.9rem;">mi</small>
                        </h3>
                        <small style="color: #7b6a87;">
                            <span class="counter-small" data-target="{{ $totalDistanceThisMonth ?? 0 }}">0</span> @lang('home.miles_this_month')
                        </small>
                    </div>
                </div>
            </div>

            {{-- Total Hours --}}
            <div class="col-6 col-md-3">
                <div class="card text-center py-2 shadow-sm" style="background-color: #F3EFF5; border: 1px solid rgba(65,44,77,0.15); border-radius: 12px;">
                    <div class="card-body py-2">
                        <h5 class="mb-2" style="color: #5a4a66;">@lang('home.total_hours')</h5>
                        <h3 class="fw-bold mb-1" style="color: #2c1e35;">
                            <span class="counter-hours" data-minutes="{{ $totalHoursAllTime ?? 0 }}">0</span>
                        </h3>
                        <small style="color: #7b6a87;">
                            <span class="counter-hours-small" data-minutes="{{ $totalHoursThisMonth ?? 0 }}">0</span> @lang('home.hours_this_month')
                        </small>
                    </div>
                </div>
            </div>

            {{-- Total Pilots --}}
            <div class="col-6 col-md-3">
                <div class="card text-center py-2 shadow-sm" style="background-color: #F3EFF5; border: 1px solid rgba(65,44,77,0.15); border-radius: 12px;">
                    <div class="card-body py-2">
                        <h5 class="mb-2" style="color: #5a4a66;">@lang('home.total_pilots')</h5>
                        <h3 class="fw-bold mb-1" style="color: #2c1e35;">
                            <span class="counter" data-target="{{ $totalPilotsAllTime ?? 0 }}">0</span>
                        </h3>
                        <small style="color: #7b6a87;">
                            <span class="counter-small" data-target="{{ $totalPilotsActive ?? 0 }}">0</span> @lang('home.active_this_month')
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    {{-- HERO --}}
    <div class="bg-dark py-5" style="background: linear-gradient(rgba(30,27,36,0.95), rgba(30,27,36,0.98));">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h2 class="fw-bold text-white">VHOLAR: ¡LA AEROLÍNEA VIRTUAL QUE LO TIENE TODO!</h2>
                    <img src="{{ public_asset('/images/todo.png') }}" alt="VHOLAR" class="img-fluid my-3" style="max-width: 100%;">
                    <h2 class="fw-bold text-white">Tendrás acompañamiento de Pilotos reales de nuestros equipos</h2>
                </div>
            </div>
        </div>
    </div>

    {{-- TABLAS --}}
    <div class="container py-4">
        {{-- Dispatched Flights --}}
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3">@lang('home.dispatched_flights')</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>@lang('common.pilot')</th>
                                <th>@lang('flights.flightnumber')</th>
                                <th>@lang('common.type')</th>
                                <th>@lang('flights.dep')</th>
                                <th>@lang('flights.arr')</th>
                                <th>@lang('home.pax')</th>
                                <th>@lang('common.cargo')</th>
                                <th>@lang('common.aircraft')</th>
                                <th>@lang('common.status')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeBids ?? [] as $bid)
                                <tr>
                                    <td class="small">{{ $bid->user->ident ?? '' }} ({{ $bid->user->name_private ?? '' }})</td>
                                    <td class="small">{{ $bid->flight->ident ?? '' }}</td>
                                    <td class="small">{{ $bid->flight ? \App\Models\Enums\FlightType::label($bid->flight->flight_type) : '' }}</td>
                                    <td class="small">{{ $bid->flight->dpt_airport_id ?? '' }}</td>
                                    <td class="small">{{ $bid->flight->arr_airport_id ?? '' }}</td>
                                    <td class="small">-</td>
                                    <td class="small">-</td>
                                    <td class="small">{{ optional($bid->aircraft)->registration ?? __('common.any') }}</td>
                                    <td><span class="badge bg-success small">@lang('common.active')</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center small">@lang('home.no_active_bookings')</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top 5 Landings + Latest Members --}}
        <div class="row g-4">
            <div class="col-md-6">
                <h4 class="mb-3">@lang('home.top_scoring')</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>@lang('common.pilot')</th>
                                <th>@lang('common.flight')</th>
                                <th>@lang('common.score')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topScoring ?? [] as $pirep)
                                <tr>
                                    <td class="small">
                                        <a href="{{ route('frontend.profile.show', [$pirep->user_id]) }}">
                                            {{ $pirep->user->ident ?? '' }} ({{ $pirep->user->name_private ?? '' }})
                                        </a>
                                    </td>
                                    <td class="small">
                                        <a href="{{ route('frontend.pireps.show', [$pirep->id]) }}">
                                            {{ $pirep->ident ?? '' }}
                                        </a>
                                    </td>
                                    <td class="small">{{ number_format($pirep->score ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center small">@lang('home.no_scored_flights')</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h4 class="mb-3 mt-4">@lang('home.bottom_scoring')</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>@lang('common.pilot')</th>
                                <th>@lang('common.flight')</th>
                                <th>@lang('common.score')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bottomScoring ?? [] as $pirep)
                                <tr>
                                    <td class="small">
                                        <a href="{{ route('frontend.profile.show', [$pirep->user_id]) }}">
                                            {{ $pirep->user->ident ?? '' }} ({{ $pirep->user->name_private ?? '' }})
                                        </a>
                                    </td>
                                    <td class="small">
                                        <a href="{{ route('frontend.pireps.show', [$pirep->id]) }}">
                                            {{ $pirep->ident ?? '' }}
                                        </a>
                                    </td>
                                    <td class="small">{{ number_format($pirep->score ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center small">@lang('home.no_scored_flights')</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-6">
                <h4 class="mb-3">@lang('home.latest_members')</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>@lang('common.pilot')</th>
                                <th>@lang('common.country')</th>
                                <th>@lang('common.hub')</th>
                                <th>@lang('home.hired_date')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($latestPilots ?? [] as $pilot)
                                <tr>
                                    <td class="small">{{ $pilot->ident }} ({{ $pilot->name_private }})</td>
                                    <td class="small">
                                        <span class="fi fi-{{ $pilot->country }}"></span>
                                        {{ $pilot->country_name ?? $pilot->country }}
                                    </td>
                                    <td class="small">{{ optional($pilot->home_airport)->name ?? $pilot->home_airport_id }}</td>
                                    <td class="small">{{ $pilot->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center small">@lang('home.no_pilots')</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {{-- SECCIÓN DE LLAMADA A LA ACCIÓN (CTA) --}}
    <section id="join" class="join section py-3" style="background-color: #DDD3E4;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-auto d-flex align-items-center gap-4">
                    <h4 class="title fw-bold mb-0" style="color: #412c4d;">
                        <b>¿Estás listo para VHOLAR?</b>
                    </h4>
                    <a href="{{ url('/register') }}" class="btn btn-primary px-4 py-1" style="background-color: #412c4d !important; border-color: #412c4d !important; font-size: 0.9rem;">
                        Escríbenos
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    @parent
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const animateNumber = (element, target, duration = 2000) => {
                const stepTime = 20;
                const steps = duration / stepTime;
                const increment = target / steps;
                let current = 0;
                const update = () => {
                    current += increment;
                    if (current < target) {
                        element.innerText = Math.floor(current).toLocaleString();
                        setTimeout(update, stepTime);
                    } else {
                        element.innerText = target.toLocaleString();
                    }
                };
                update();
            };
            
            const animateHours = (element, minutes, duration = 2000) => {
                const targetHours = Math.floor(minutes / 60);
                const stepTime = 20;
                const steps = duration / stepTime;
                const increment = targetHours / steps;
                let current = 0;
                const update = () => {
                    current += increment;
                    if (current < targetHours) {
                        element.innerText = Math.floor(current).toLocaleString();
                        setTimeout(update, stepTime);
                    } else {
                        element.innerText = targetHours.toLocaleString();
                    }
                };
                update();
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = parseInt(entry.target.getAttribute('data-target'));
                        if (!isNaN(target)) animateNumber(entry.target, target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            
            document.querySelectorAll('.counter').forEach(c => observer.observe(c));
            document.querySelectorAll('.counter-small').forEach(c => observer.observe(c));
            
            const hourObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const minutes = parseInt(entry.target.getAttribute('data-minutes'));
                        if (!isNaN(minutes)) animateHours(entry.target, minutes);
                        hourObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            document.querySelectorAll('.counter-hours, .counter-hours-small').forEach(c => hourObserver.observe(c));
        });
    </script>
@endsection
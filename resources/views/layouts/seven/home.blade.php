@extends('layouts.seven.app')

@section('title', 'Home')

@section('content')
<div class="vh-hero text-center py-5 mb-4" style="background: linear-gradient(rgba(30,27,36,0.8), rgba(30,27,36,0.9)), url('/images/banner.jpg'); background-size: cover; background-position: center;">
    <div class="container">
        <h1 class="display-4 fw-bold text-white">VHOLAR: ¡LA AEROLÍNEA VIRTUAL QUE LO TIENE TODO!</h1>
        <p class="lead text-light">Tendrás acompañamiento de Pilotos reales de nuestros equipos</p>
        <a href="{{ route('register') }}" class="btn btn-primary btn-lg mt-3">Join the Crew</a>
    </div>
</div>


<div style="padding:60px 20px; text-align:center;">
    <h2>Our Statistics</h2>
    <div style="display:flex; justify-content:center; gap:50px; margin-top:40px;">
        <div>
            <h3 style="font-size:40px;">{{ \App\Models\User::count() }}</h3>
            <p>Pilots</p>
        </div>
        <div>
            <h3 style="font-size:40px;">{{ \App\Models\Pirep::count() }}</h3>
            <p>Flights Completed</p>
        </div>
        <div>
            <h3 style="font-size:40px;">{{ \App\Models\Airport::count() }}</h3>
            <p>Airports Served</p>
        </div>
    </div>
</div>

@endsection
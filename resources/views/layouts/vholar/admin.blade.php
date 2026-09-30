@extends('layouts.vholar.app')

@section('title', isset($title) ? $title . ' - Admin' : 'Admin Panel')

@section('content')
<div class="container-fluid">
    @include('flash::message')
    @yield('content')
</div>
@endsection
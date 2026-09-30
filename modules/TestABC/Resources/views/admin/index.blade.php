@extends('testabc::layouts.admin')

@section('title', 'TestABC')
@section('actions')
    <li>
        <a href="{{ url('/testabc/admin/create') }}">
            <i class="ti-plus"></i>
            Add New</a>
    </li>
@endsection
@section('content')
    <div class="card border-blue-bottom">
        <div class="header"><h4 class="title">Admin Scaffold!</h4></div>
        <div class="content">
            <p>This view is loaded from module: {{ config('testabc.name') }}</p>
        </div>
    </div>
@endsection

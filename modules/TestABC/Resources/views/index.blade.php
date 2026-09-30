@extends('testabc::layouts.frontend')

@section('title', 'TestABC')

@section('content')
    <h1>Hello World</h1>

    <p>
        This view is loaded from module: {{ config('testabc.name') }}
    </p>
@endsection

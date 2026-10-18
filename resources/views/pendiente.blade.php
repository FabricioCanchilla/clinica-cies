@extends('layouts.app')

@section('titulo', 'Panel')

@section('contenido')
    <h1>Panel de {{ config('roles.' . auth()->user()->COD_ROL . '.nombre') }}</h1>
    <p class="sub">Este panel está en construcción.</p>
@endsection
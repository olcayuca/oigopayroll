@extends('errors.layout')

@section('code', '419')
@section('color', '#2D6FD1')
@section('title', 'Oturumunuzun süresi doldu')
@section('message', 'Güvenliğiniz için uzun süre işlem yapılmayan oturumlar kapatılır. Sayfayı yenileyip yeniden deneyin.')

@section('actions')
    <a href="{{ url()->previous() }}" class="btn primary">Sayfayı yenile</a>
    <a href="{{ url('/login') }}" class="btn secondary">Yeniden giriş yap</a>
@endsection

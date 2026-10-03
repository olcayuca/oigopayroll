@extends('errors.layout')

@section('code', '500')
@section('color', '#D24B47')
@section('title', 'Beklenmeyen bir hata oluştu')
@section('message', 'İsteğiniz işlenirken bir sorun oluştu. Ekibimiz hatadan haberdar edildi; birkaç dakika sonra yeniden deneyin.')

@section('actions')
    <a href="javascript:location.reload()" class="btn primary">Yeniden dene</a>
    <a href="{{ url('/') }}" class="btn secondary">Ana sayfaya dön</a>
@endsection

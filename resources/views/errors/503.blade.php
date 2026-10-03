@extends('errors.layout')

@section('code', '503')
@section('color', '#2D6FD1')
@section('title', 'Planlı bakım çalışması')
@section('message', 'Sistem şu anda bakımda. Bordro dönemleriniz ve verileriniz etkilenmez; kısa süre sonra yeniden hizmetteyiz.')

@section('actions')
    <a href="javascript:location.reload()" class="btn primary">Sayfayı yenile</a>
@endsection

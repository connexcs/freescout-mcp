@extends('layouts.app')

@section('title_full', __('OAuth request error'))

@section('content')
    <div class="section-heading">{{ __('OAuth request error') }}</div>
    <div class="alert alert-danger"><strong>{{ $error->error }}</strong>: {{ $error->getMessage() }}</div>
@endsection

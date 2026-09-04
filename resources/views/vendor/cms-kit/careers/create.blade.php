@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.careers.vacancies.index') }}">Vacancies</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add Vacancy</li>
@endsection

@section('content')
    @include('cms-kit::careers._form', [
        'formAction' => route('cms.careers.store'),
        'submitLabel' => 'Save Vacancy',
    ])
@endsection

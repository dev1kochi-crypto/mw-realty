@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.careers.departments.index') }}">Departments</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Department</li>
@endsection

@section('content')
    @include('cms-kit::careers.departments._form', [
        'formAction' => route('cms.careers.departments.update', $department->id),
        'submitLabel' => 'Update Department',
    ])
@endsection

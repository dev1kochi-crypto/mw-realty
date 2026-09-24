@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.payments.index') }}">Payments</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $number }}</li>
@endsection

@section('content')
@include('invoices._web', [
    'backUrl' => route('cms.payments.index'),
    'backLabel' => 'Back to Payments',
    'pdfUrl' => route('cms.payments.pdf', $payment->id),
])
@endsection

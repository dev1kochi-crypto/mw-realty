@extends('portal.layouts.app')

@section('title', 'Invoice ' . $number)

@section('content')
@include('invoices._web', [
    'backUrl' => route('portal.plans.payments'),
    'backLabel' => 'Back to Payment History',
    'pdfUrl' => route('portal.plans.payments.pdf', $payment->id),
    'backClass' => 'btn-portal-light',
    'secondaryClass' => 'btn-portal-light',
    'primaryClass' => 'btn-portal-primary',
])
@endsection

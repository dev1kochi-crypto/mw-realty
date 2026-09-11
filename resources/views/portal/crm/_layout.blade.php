@extends('portal.layouts.app')

{{-- Shared shell for CRM pages — navigation lives in the sidebar (Leads / Master / Reports), so this
     just passes through to the page's own content; kept as a named extension point for any CRM-wide
     chrome that comes later, instead of duplicating nav here. --}}

@section('content')
@yield('crm-content')
@endsection

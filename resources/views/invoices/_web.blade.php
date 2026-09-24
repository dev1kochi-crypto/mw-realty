{{-- Web invoice page body (admin + portal): toolbar + the shared invoice document. --}}
@include('invoices._styles')
<style>
    .inv-page { max-width: 820px; margin: 0 auto; }
    .inv-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
    .inv-sheet { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 14px 40px rgba(28, 35, 64, 0.1); border: 1px solid rgba(28, 35, 64, 0.06); }
    .inv-sheet .inv { font-size: 14px; }
    @media print {
        body * { visibility: hidden; }
        .inv-sheet, .inv-sheet * { visibility: visible; }
        .inv-sheet { position: absolute; inset: 0; box-shadow: none; border: 0; border-radius: 0; }
        .inv-head { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
<div class="inv-page">
    <div class="inv-toolbar">
        <a href="{{ $backUrl }}" class="btn btn-sm {{ $backClass ?? 'btn-outline-secondary' }}"><i class="fas fa-arrow-left me-1"></i>{{ $backLabel }}</a>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm {{ $secondaryClass ?? 'btn-outline-secondary' }}" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
            <a href="{{ $pdfUrl }}" class="btn btn-sm {{ $primaryClass ?? 'btn-primary' }}"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
        </div>
    </div>
    <div class="inv-sheet">
        @include('invoices._document')
    </div>
</div>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $number }}</title>
@include('invoices._styles')
<style>
    @page { margin: 0; }
    body { margin: 0; }
    /* dompdf has no calc(): inset blocks with margins instead. */
    .inv-facts, .inv-lines, .inv-totals { width: 90%; margin-left: 5%; margin-right: 5%; }
</style>
</head>
<body>
@include('invoices._document')
</body>
</html>

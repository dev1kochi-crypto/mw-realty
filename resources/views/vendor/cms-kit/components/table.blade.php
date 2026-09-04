@props([
    'headers' => [],
    'items' => [],
    'actions' => true,
])

<div class="table-responsive">
    <table {{ $attributes->merge(['class' => 'table premium-table mb-0']) }}>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th @if($loop->first) class="ps-4" @endif>{{ $header }}</th>
                @endforeach
                @if($actions) <th class="text-end pe-4">Actions</th> @endif
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>

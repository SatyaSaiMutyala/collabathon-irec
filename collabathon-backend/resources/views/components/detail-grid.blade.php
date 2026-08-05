@props(['fields' => []])

{{--
    A label/value list that pairs short fields two-up instead of giving each its own
    full-width row, which left most of the line empty on a wide panel.

    Each entry: ['label' => string, 'value' => mixed, 'wide' => bool].
    A value that is an HtmlString renders as markup, so a call site can pass badges or a
    link without this component growing a slot for every case.

    Rows are chunked in PHP rather than left to the grid: a `wide` field has to occupy a
    row of its own, and letting CSS handle that with nth-child parity breaks the moment a
    wide field shifts the odd/even sequence. Chunking first makes the hairlines exact.
--}}
@php
    $rows = [];
    $current = [];

    foreach ($fields as $field) {
        if ($field['wide'] ?? false) {
            if ($current) {
                $rows[] = $current;
                $current = [];
            }
            $rows[] = [$field];
            continue;
        }

        $current[] = $field;

        if (count($current) === 2) {
            $rows[] = $current;
            $current = [];
        }
    }

    if ($current) {
        $rows[] = $current;
    }

    /**
     * The value is resolved here rather than inline in the markup. `whitespace-pre-line`
     * has to stay so a multi-line address keeps its breaks — which also means any newline
     * or indentation between the <dd> tags becomes a visible blank line above the value.
     * Emitting one pre-escaped string with no surrounding whitespace is what avoids that.
     */
    $render = function ($value) {
        if ($value instanceof \Illuminate\Support\HtmlString) {
            return $value;
        }

        return new \Illuminate\Support\HtmlString(filled($value) ? e($value) : '—');
    };
@endphp

<dl {{ $attributes }}>
    @foreach($rows as $row)
        <div @class([
            'grid grid-cols-1 sm:grid-cols-2',
            'border-b border-line-soft' => ! $loop->last,
        ])>
            @foreach($row as $index => $field)
                <div @class([
                    'px-5 py-2.5 min-w-0',
                    // Second cell of a pair: a divider above it when stacked on mobile,
                    // replaced by the vertical rule once the pair sits side by side.
                    'border-t border-line-soft sm:border-t-0' => $index > 0,
                    'sm:border-r sm:border-line-soft' => $index === 0 && count($row) > 1,
                    'sm:col-span-2' => ($field['wide'] ?? false),
                ])>
                    <dt class="text-[11.5px] text-ink-3 leading-tight">{{ $field['label'] }}</dt>
                    <dd class="text-[13px] text-ink mt-0.5 min-w-0 break-words whitespace-pre-line leading-snug">{!! $render($field['value']) !!}</dd>
                </div>
            @endforeach
        </div>
    @endforeach
</dl>

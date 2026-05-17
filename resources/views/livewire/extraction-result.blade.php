@php
    $extraction = $doc->extraction;
    $quality = $extraction?->quality ?? 'low';
    $qualityColor = match ($quality) {
        'high' => 'bg-green-100 text-green-800 border-green-200',
        'medium' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        default => 'bg-red-100 text-red-800 border-red-200',
    };
    $confidencePerField = $extraction?->confidence_per_field ?? [];
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $doc->original_name }}</h1>
            <p class="text-sm text-gray-500">
                Document #{{ $doc->id }} · {{ $doc->mime }} · {{ number_format($doc->size_bytes / 1024, 1) }} KB
                · {{ $doc->created_at?->format('Y-m-d H:i') }}
            </p>
        </div>
        <a href="/ocr" wire:navigate
           class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">
            ← Upload khác
        </a>
    </div>

    {{-- Status alert --}}
    @if ($doc->status === 'failed')
        <div class="rounded-md bg-red-50 border border-red-200 p-4">
            <p class="font-semibold text-red-800">Pipeline failed</p>
            <p class="text-sm text-red-700 mt-1">{{ $doc->error_message }}</p>
        </div>
    @elseif ($doc->status === 'processing' || $doc->status === 'pending')
        <div class="rounded-md bg-blue-50 border border-blue-200 p-4">
            <p class="text-sm text-blue-700">Đang xử lý… Reload page sau 30s.</p>
        </div>
    @endif

    @if ($extraction)
        {{-- Document metadata --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-md border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500 uppercase">Doc type</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $extraction->doc_type }}</p>
            </div>
            <div class="rounded-md border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500 uppercase">Ngôn ngữ</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $extraction->language_detected }}</p>
            </div>
            <div class="rounded-md border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500 uppercase">Confidence</p>
                <p class="font-semibold text-gray-900 mt-1">
                    {{ number_format($extraction->confidence_overall * 100, 1) }}%
                </p>
            </div>
            <div class="rounded-md border p-3 {{ $qualityColor }}">
                <p class="text-xs uppercase opacity-75">Quality</p>
                <p class="font-semibold mt-1 capitalize">
                    {{ $quality }}
                    @if ($extraction->requires_review)
                        · cần review
                    @endif
                </p>
            </div>
        </div>

        {{-- Warnings --}}
        @if (! empty($extraction->warnings))
            <div class="rounded-md bg-amber-50 border border-amber-200 p-3 space-y-1">
                <p class="text-sm font-medium text-amber-900">Warnings</p>
                @foreach ($extraction->warnings as $w)
                    <p class="text-xs text-amber-800">
                        <span class="font-mono">{{ $w['code'] ?? '' }}</span>
                        · {{ $w['field'] ?? '' }} — {{ $w['msg'] ?? '' }}
                    </p>
                @endforeach
            </div>
        @endif

        {{-- Key-values với action tracking --}}
        <div class="bg-white rounded-md border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-200">
                <h2 class="font-semibold text-gray-900">Trường dữ liệu OCR</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Click <b>Copy</b> nếu giá trị đúng · <b>Edit</b> rồi <b>Copy</b> nếu cần sửa
                    · <b>Skip</b> nếu không cần · <b>Wrong</b> nếu AI bịa.
                    Mọi action được tracked làm implicit feedback cho agent học.
                </p>
            </div>

            <table class="w-full text-sm" x-data="{ startedAt: Date.now() }">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2 w-1/4">Field</th>
                        <th class="text-left px-4 py-2">Giá trị (chỉnh sửa được)</th>
                        <th class="text-right px-4 py-2 w-32">Confidence</th>
                        <th class="text-right px-4 py-2 w-72">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($fieldState as $key => $state)
                        @php
                            $conf = $confidencePerField[$key] ?? 0;
                            $confColor = match (true) {
                                $conf >= 0.85 => 'text-green-700 bg-green-50',
                                $conf >= 0.5 => 'text-yellow-700 bg-yellow-50',
                                default => 'text-red-700 bg-red-50',
                            };
                            $rowDimmed = $state['action'] !== null;
                        @endphp
                        <tr class="{{ $rowDimmed ? 'opacity-60' : '' }}">
                            <td class="px-4 py-2 font-mono text-xs text-gray-700">{{ $key }}</td>
                            <td class="px-4 py-2">
                                <input
                                    type="text"
                                    value="{{ $state['edited_value'] }}"
                                    x-data="{ value: @js($state['edited_value']) }"
                                    x-model="value"
                                    x-ref="input_{{ $key }}"
                                    @input="$el.dataset.edited = (value !== @js($state['value']))"
                                    class="w-full rounded border-gray-200 text-sm px-2 py-1 focus:border-gray-400 focus:ring-0"
                                >
                            </td>
                            <td class="px-4 py-2 text-right">
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium {{ $confColor }}">
                                    {{ number_format($conf * 100, 0) }}%
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right space-x-1">
                                @if ($state['action'])
                                    <span class="text-xs text-gray-500">✓ {{ $state['action'] }}</span>
                                @else
                                    <button
                                        type="button"
                                        x-on:click="
                                            const inp = $refs.input_{{ $key }};
                                            const edited = inp.value !== @js($state['value']);
                                            navigator.clipboard.writeText(inp.value);
                                            const dur = Date.now() - startedAt;
                                            $wire.recordAction(
                                                @js($key),
                                                edited ? 'edit_then_copy' : 'copy_raw',
                                                inp.value,
                                                dur
                                            );
                                        "
                                        class="rounded bg-gray-900 px-2 py-1 text-xs font-medium text-white hover:bg-gray-800">
                                        Copy
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="recordAction(@js($key), 'skip', null, {{ 0 }})"
                                        class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">
                                        Skip
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="recordAction(@js($key), 'mark_wrong', null, {{ 0 }})"
                                        class="rounded border border-red-300 text-red-600 px-2 py-1 text-xs hover:bg-red-50">
                                        Wrong
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (empty($fieldState))
                <div class="px-4 py-6 text-center text-sm text-gray-500">
                    Không trích xuất được field nào.
                </div>
            @endif
        </div>

        {{-- Translation --}}
        @if ($extraction->translation_vi)
            <details class="rounded-md border border-gray-200 bg-white">
                <summary class="cursor-pointer px-4 py-2 font-medium text-gray-900">
                    Bản dịch tiếng Việt
                </summary>
                <pre class="px-4 py-3 border-t border-gray-200 text-sm whitespace-pre-wrap font-sans">{{ $extraction->translation_vi }}</pre>
            </details>
        @endif

        {{-- Raw text toggle --}}
        <details class="rounded-md border border-gray-200 bg-white">
            <summary class="cursor-pointer px-4 py-2 font-medium text-gray-900">
                Raw text ({{ strlen($extraction->text_full) }} ký tự)
            </summary>
            <pre class="px-4 py-3 border-t border-gray-200 text-xs whitespace-pre-wrap font-mono max-h-96 overflow-y-auto">{{ $extraction->text_full }}</pre>
        </details>
    @endif
</div>

@push('scripts')
    <script src="//unpkg.com/alpinejs" defer></script>
@endpush

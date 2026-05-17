@php
    use App\Support\OcrFieldLabels;
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
        <div class="flex gap-2">
            <a href="/ocr/history" wire:navigate
               class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">
                Lịch sử
            </a>
            <a href="/ocr" wire:navigate
               class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">
                ← Upload khác
            </a>
        </div>
    </div>

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

        {{-- Translation tiếng Việt — show prominent cho non-VN docs --}}
        @if ($extraction->translation_vi && $extraction->language_detected !== 'vi')
            <div class="bg-blue-50 border border-blue-200 rounded-md">
                <div class="px-4 py-2 border-b border-blue-200 flex items-center justify-between">
                    <h2 class="font-semibold text-blue-900">
                        Bản dịch tiếng Việt
                        <span class="ml-2 text-xs font-normal text-blue-700">
                            (gốc: {{ strtoupper($extraction->language_detected) }})
                        </span>
                    </h2>
                    <button type="button"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($extraction->translation_vi)).then(() => { copied = true; setTimeout(() => copied = false, 1500); })"
                            class="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700">
                        <span x-show="!copied">Copy bản dịch</span>
                        <span x-show="copied" x-cloak>✓ Copied</span>
                    </button>
                </div>
                <pre class="px-4 py-3 text-sm whitespace-pre-wrap font-sans max-h-72 overflow-y-auto text-blue-900">{{ $extraction->translation_vi }}</pre>
            </div>
        @endif

        {{-- Key-values với action tracking --}}
        <div class="bg-white rounded-md border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-200">
                <h2 class="font-semibold text-gray-900">Trường dữ liệu OCR</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Click <b>Copy</b> nếu giá trị đúng · sửa value rồi <b>Copy</b> để mark edited
                    · <b>Skip</b> nếu không cần · <b>Wrong</b> nếu AI bịa.
                    Mọi action được tracked làm implicit feedback cho agent học.
                </p>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2 w-1/4">Field</th>
                        <th class="text-left px-4 py-2">Giá trị (chỉnh sửa được)</th>
                        <th class="text-right px-4 py-2 w-32">Confidence</th>
                        <th class="text-right px-4 py-2 w-80">Actions</th>
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
                        <tr
                            wire:key="row-{{ $key }}"
                            x-data="{
                                value: @js($state['edited_value']),
                                original: @js($state['value']),
                                startedAt: Date.now(),
                                copied: false,
                                doCopy() {
                                    const edited = this.value !== this.original;
                                    navigator.clipboard.writeText(this.value).then(() => {
                                        this.copied = true;
                                        setTimeout(() => this.copied = false, 1500);
                                    });
                                    const dur = Date.now() - this.startedAt;
                                    $wire.set('fieldState.{{ $key }}.edited_value', this.value, false);
                                    $wire.recordAction(@js($key), edited ? 'edit_then_copy' : 'copy_raw', dur);
                                },
                                doSkip() {
                                    $wire.recordAction(@js($key), 'skip', null);
                                },
                                doWrong() {
                                    $wire.recordAction(@js($key), 'mark_wrong', null);
                                }
                            }"
                            class="{{ $rowDimmed ? 'opacity-60' : '' }}"
                        >
                            <td class="px-4 py-2 align-middle">
                                <p class="font-medium text-gray-900 text-sm">{{ OcrFieldLabels::label($key) }}</p>
                                <p class="font-mono text-xs text-gray-500">{{ $key }}</p>
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    type="text"
                                    x-model="value"
                                    @disabled($state['action'] !== null)
                                    class="w-full rounded border-gray-200 text-sm px-2 py-1 focus:border-gray-400 focus:ring-0 disabled:bg-gray-50"
                                >
                            </td>
                            <td class="px-4 py-2 text-right align-middle">
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium {{ $confColor }}">
                                    {{ number_format($conf * 100, 0) }}%
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right space-x-1 align-middle">
                                @if ($state['action'])
                                    <span class="text-xs text-gray-500">✓ {{ $state['action'] }}</span>
                                @else
                                    <button type="button"
                                            x-on:click="doCopy()"
                                            class="rounded bg-gray-900 px-2 py-1 text-xs font-medium text-white hover:bg-gray-800">
                                        <span x-show="!copied">Copy</span>
                                        <span x-show="copied" x-cloak>✓ Copied</span>
                                    </button>
                                    <button type="button"
                                            x-on:click="doSkip()"
                                            class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">
                                        Skip
                                    </button>
                                    <button type="button"
                                            x-on:click="doWrong()"
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

        {{-- KSNB overall comment --}}
        <div class="bg-white rounded-md border border-gray-200 p-4">
            <h2 class="font-semibold text-gray-900">Nhận xét chung của KSNB</h2>
            <p class="text-xs text-gray-500 mt-1 mb-3">
                Comment tự do về tài liệu này: nhận xét chất lượng OCR, vấn đề bạn thấy,
                hoặc gợi ý cải thiện. Agent sẽ đọc note này khi phân tích feedback.
            </p>

            @if ($overallNoteSubmitted)
                <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                    ✓ Đã lưu nhận xét. Cảm ơn bạn.
                </div>
            @else
                <form wire:submit="submitOverallComment" class="space-y-2">
                    <textarea wire:model="overallNote"
                              rows="3"
                              maxlength="2000"
                              placeholder="VD: AI extract sai họ tên (thiếu dấu), confidence 0.95 hơi cao so với thực tế. Nên thêm rule check dấu tiếng Việt."
                              class="w-full rounded border-gray-200 text-sm px-3 py-2 focus:border-gray-400 focus:ring-0"></textarea>
                    @error('overallNote')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-400">Tối đa 2000 ký tự</p>
                        <button type="submit"
                                class="rounded bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">
                            Gửi nhận xét
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <details class="rounded-md border border-gray-200 bg-white">
            <summary class="cursor-pointer px-4 py-2 font-medium text-gray-900">
                Raw text ({{ strlen($extraction->text_full) }} ký tự)
            </summary>
            <pre class="px-4 py-3 border-t border-gray-200 text-xs whitespace-pre-wrap font-mono max-h-96 overflow-y-auto">{{ $extraction->text_full }}</pre>
        </details>
    @endif
</div>

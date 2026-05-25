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
    <div>
        <h1 class="text-2xl font-semibold">{{ $doc->original_name }}</h1>
        <p class="text-sm text-gray-500">
            #{{ $doc->id }} · {{ number_format($doc->size_bytes / 1024, 1) }} KB
            @if ($extraction && $extraction->page_count > 0)
                · {{ $extraction->page_count }} trang
            @endif
            · {{ $doc->created_at?->format('d/m/Y H:i') }}
        </p>
    </div>

    @if ($doc->status === 'failed')
        @php
            $errMsg = (string) ($doc->error_message ?? '');
            if (str_starts_with($errMsg, 'Not a document:')) {
                $vnTitle = 'Đây không phải tài liệu được hỗ trợ';
                $vnHint = 'Hệ thống nhận diện ảnh tải lên không phải tài liệu (vd ảnh chân dung, phong cảnh, screenshot…). Vui lòng upload tài liệu cần OCR như CCCD, hộ chiếu, hợp đồng, hóa đơn…';
                $detail = trim(substr($errMsg, strlen('Not a document:')));
            } elseif (str_starts_with($errMsg, 'Extractor declined:')) {
                $vnTitle = 'Hệ thống trích xuất đã từ chối xử lý';
                $vnHint = 'Ảnh có thể quá mờ, bị che một phần hoặc không đủ thông tin để bóc tách. Vui lòng thử lại với ảnh rõ hơn.';
                $detail = trim(substr($errMsg, strlen('Extractor declined:')));
            } elseif (str_contains($errMsg, 'vượt giới hạn')) {
                $vnTitle = 'File PDF vượt giới hạn trang';
                $vnHint = 'Vui lòng tách file thành nhiều file nhỏ hơn rồi upload lại.';
                $detail = $errMsg;
            } else {
                $vnTitle = 'Không xử lý được tài liệu này';
                $vnHint = 'Có thể do dịch vụ OCR đang gặp sự cố hoặc file bị lỗi. Vui lòng thử lại hoặc liên hệ kỹ thuật nếu vấn đề tiếp diễn.';
                $detail = $errMsg;
            }
        @endphp
        <div class="rounded-md bg-red-50 border border-red-200 p-4">
            <p class="font-semibold text-red-800">{{ $vnTitle }}</p>
            <p class="text-sm text-red-700 mt-1">{{ $vnHint }}</p>
            @if ($detail)
                <details class="mt-2">
                    <summary class="text-xs text-red-600 cursor-pointer hover:underline select-none">
                        Chi tiết kỹ thuật
                    </summary>
                    <p class="text-xs text-red-700 mt-1 font-mono break-words">{{ $detail }}</p>
                </details>
            @endif
        </div>
    @elseif ($doc->status === 'processing' || $doc->status === 'pending')
        <div class="rounded-md bg-blue-50 border border-blue-200 p-4">
            <p class="text-sm text-blue-700">Đang xử lý… Reload page sau 30s.</p>
        </div>
    @endif

    @if ($extraction)
        @php
            $docTypeLabels = [
                'cccd' => 'CCCD/CMND', 'passport' => 'Hộ chiếu',
                'national_id_foreign' => 'CMND nước ngoài',
                'gpkd' => 'Giấy phép kinh doanh',
                'contract_vi' => 'Hợp đồng (VI)', 'contract_en' => 'Hợp đồng (EN)',
                'contract_zh' => 'Hợp đồng (ZH)', 'invoice' => 'Hóa đơn',
                'legal_doc' => 'Văn bản pháp lý',
                'customs_declaration' => 'Tờ khai hải quan',
                'bill_of_lading' => 'Vận đơn',
                'aml_charter' => 'Điều lệ AML',
                'power_of_attorney' => 'Giấy ủy quyền',
                'labor_contract' => 'Hợp đồng lao động',
                'financial_report' => 'Báo cáo tài chính',
                'other' => 'Khác',
            ];
            $langLabels = ['vi'=>'Tiếng Việt','en'=>'Tiếng Anh','zh'=>'Tiếng Trung','mixed'=>'Đa ngôn ngữ','und'=>'Không xác định'];
            $qualityLabels = ['high'=>'Tốt','medium'=>'Trung bình','low'=>'Thấp'];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-md border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">Loại tài liệu</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $docTypeLabels[$extraction->doc_type] ?? $extraction->doc_type }}</p>
            </div>
            <div class="rounded-md border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">Ngôn ngữ</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $langLabels[$extraction->language_detected] ?? $extraction->language_detected }}</p>
            </div>
            <div class="rounded-md border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">Độ tin cậy</p>
                <p class="font-semibold text-gray-900 mt-1">
                    {{ number_format($extraction->confidence_overall * 100, 1) }}%
                </p>
            </div>
            <div class="rounded-md border p-3 {{ $qualityColor }}">
                <p class="text-xs opacity-75">Chất lượng</p>
                <p class="font-semibold mt-1">
                    {{ $qualityLabels[$quality] ?? $quality }}
                    @if ($extraction->requires_review)
                        · cần kiểm tra
                    @endif
                </p>
            </div>
        </div>

        {{-- File gốc — toggle xem ảnh/PDF đã upload --}}
        <div x-data="{ open: false }" class="bg-white border border-gray-200 rounded-md">
            <div class="px-4 py-2 flex items-center justify-between">
                <button type="button"
                        x-on:click="open = !open"
                        class="flex items-center gap-2 font-semibold text-gray-900 hover:text-gray-700">
                    <span class="inline-block w-3 text-center text-xs leading-none transition-transform"
                          :class="open ? 'rotate-90' : ''">▶</span>
                    File gốc
                    <span class="text-xs font-normal text-gray-500">({{ $doc->mime }})</span>
                </button>
                <a href="{{ route('ocr.file', $doc->id) }}" target="_blank" rel="noopener"
                   class="text-xs text-blue-600 hover:underline">Mở tab mới ↗</a>
            </div>
            <div x-show="open" x-cloak class="border-t border-gray-200 p-3 bg-gray-50">
                @if (str_starts_with($doc->mime, 'image/'))
                    <img src="{{ route('ocr.file', $doc->id) }}"
                         alt="{{ $doc->original_name }}"
                         class="max-w-full max-h-[600px] mx-auto rounded shadow-sm">
                @elseif ($doc->mime === 'application/pdf')
                    <embed src="{{ route('ocr.file', $doc->id) }}"
                           type="application/pdf"
                           class="w-full h-[700px] rounded">
                @else
                    <p class="text-sm text-gray-500 text-center py-4">
                        Định dạng {{ $doc->mime }} không xem trực tiếp được —
                        <a href="{{ route('ocr.file', $doc->id) }}" target="_blank" class="text-blue-600 hover:underline">tải về</a>
                    </p>
                @endif
            </div>
        </div>

        @php
            $warningLabels = [
                'CRITICAL_RULE_FAILED' => 'Sai định dạng',
                'JUDGE_FLAGGED' => 'Cần kiểm tra lại',
                'SELF_VS_JUDGE_DISAGREE' => 'Kết quả không chắc chắn',
                'LOW_CONFIDENCE' => 'Độ tin cậy thấp',
                'MEDIUM_CONFIDENCE' => 'Độ tin cậy trung bình',
                'CRITICAL_FIELDS_FAILED' => 'Trường quan trọng có lỗi',
                'MULTI_SIGNAL_DISAGREE' => 'Các kiểm tra không nhất quán',
                'LOW_IMAGE_QUALITY' => 'Chất lượng ảnh thấp',
            ];
        @endphp
        @if (! empty($extraction->warnings))
            <div class="rounded-md bg-amber-50 border border-amber-200 p-3 space-y-1">
                <p class="text-sm font-medium text-amber-900">Ghi chú cần lưu ý</p>
                @foreach ($extraction->warnings as $w)
                    @php
                        $field = $w['field'] ?? '';
                        $fieldLabel = $field === '_global' ? 'Toàn bộ tài liệu' :
                            (\App\Support\OcrFieldLabels::label($field));
                    @endphp
                    <p class="text-xs text-amber-800">
                        <span class="font-medium">{{ $warningLabels[$w['code'] ?? ''] ?? ($w['code'] ?? '') }}</span>
                        · {{ $fieldLabel }}
                        @if (! empty($w['msg']))
                            — {{ $w['msg'] }}
                        @endif
                    </p>
                @endforeach
            </div>
        @endif

        {{-- Translation tiếng Việt — show prominent cho non-VN docs, có thể thu gọn --}}
        @if ($extraction->translation_vi && $extraction->language_detected !== 'vi')
            <div x-data="{ open: true }" class="bg-blue-50 border border-blue-200 rounded-md">
                <div class="px-4 py-2 border-b border-blue-200 flex items-center justify-between"
                     :class="open ? 'border-b' : 'border-b-0'">
                    <button type="button"
                            x-on:click="open = !open"
                            class="flex items-center gap-2 font-semibold text-blue-900 hover:text-blue-700">
                        <span class="inline-block w-3 text-center text-xs leading-none transition-transform"
                              :class="open ? 'rotate-90' : ''">▶</span>
                        Bản dịch tiếng Việt
                        <span class="text-xs font-normal text-blue-700">
                            (gốc: {{ $langLabels[$extraction->language_detected] ?? strtoupper($extraction->language_detected) }})
                        </span>
                    </button>
                    <button type="button"
                            x-data="{ copied: false }"
                            x-on:click.stop="navigator.clipboard.writeText(@js($extraction->translation_vi)).then(() => { copied = true; setTimeout(() => copied = false, 1500); })"
                            class="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700">
                        <span x-show="!copied">Sao chép bản dịch</span>
                        <span x-show="copied" x-cloak>✓ Đã chép</span>
                    </button>
                </div>
                <div x-show="open" x-cloak>
                @include('livewire.partials.paged-text', [
                    'text' => $extraction->translation_vi,
                    'colorClass' => 'text-blue-900',
                    'fontClass' => 'font-sans',
                    'maxHeight' => 'max-h-72',
                ])
                </div>
            </div>
        @endif

        {{-- Key-values với action tracking --}}
        <div class="bg-white rounded-md border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-200">
                <h2 class="font-semibold text-gray-900">Thông tin trích xuất</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Bấm <b>Sao chép</b> để lấy giá trị. Có thể sửa trực tiếp trước khi sao chép.
                    <b>Bỏ qua</b> nếu không cần dùng · <b>Báo sai</b> nếu thông tin không chính xác.
                </p>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2 w-1/4">Trường thông tin</th>
                        <th class="text-left px-4 py-2">Giá trị (chỉnh sửa được)</th>
                        <th class="text-right px-4 py-2 w-32">Độ tin cậy</th>
                        <th class="text-right px-4 py-2 w-80">Thao tác</th>
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
                                @php
                                    $kvEntry = $doc->extraction->key_values[$key] ?? null;
                                    $translatedVi = is_array($kvEntry) ? ($kvEntry['value_translated_vi'] ?? null) : null;
                                @endphp
                                @if ($translatedVi)
                                    <p class="mt-1 text-xs text-gray-500 leading-snug">
                                        🌐 {{ $translatedVi }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right align-middle">
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium {{ $confColor }}">
                                    {{ number_format($conf * 100, 0) }}%
                                </span>
                            </td>
                            <td class="px-4 py-2 align-middle whitespace-nowrap">
                                @if ($state['action'])
                                    @php
                                        $actionLabels = [
                                            'copy_raw' => 'Đã sao chép',
                                            'edit_then_copy' => 'Đã sửa & sao chép',
                                            'skip' => 'Đã bỏ qua',
                                            'mark_wrong' => 'Đã báo sai',
                                        ];
                                    @endphp
                                    <div class="flex items-center justify-end gap-2 flex-nowrap">
                                        <span class="text-xs text-gray-500 whitespace-nowrap">✓ {{ $actionLabels[$state['action']] ?? $state['action'] }}</span>
                                        <button type="button"
                                                wire:click="rollbackAction(@js($key))"
                                                wire:loading.attr="disabled"
                                                wire:target="rollbackAction"
                                                title="Hoàn tác để thao tác lại"
                                                class="shrink-0 rounded border border-gray-300 px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-50 whitespace-nowrap">
                                            ↶ Hoàn tác
                                        </button>
                                    </div>
                                @else
                                    <div class="flex items-center justify-end gap-1 flex-nowrap">
                                        <button type="button"
                                                x-on:click="doCopy()"
                                                class="shrink-0 rounded bg-gray-900 px-2 py-1 text-xs font-medium text-white hover:bg-gray-800 whitespace-nowrap">
                                            <span x-show="!copied">Sao chép</span>
                                            <span x-show="copied" x-cloak>✓ Đã chép</span>
                                        </button>
                                        <button type="button"
                                                x-on:click="doSkip()"
                                                class="shrink-0 rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 whitespace-nowrap">
                                            Bỏ qua
                                        </button>
                                        <button type="button"
                                                x-on:click="doWrong()"
                                                class="shrink-0 rounded border border-red-300 text-red-600 px-2 py-1 text-xs hover:bg-red-50 whitespace-nowrap">
                                            Báo sai
                                        </button>
                                    </div>
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
            <h2 class="font-semibold text-gray-900">Nhận xét của bạn</h2>
            <p class="text-xs text-gray-500 mt-1 mb-3">
                Bạn có thể ghi nhận xét về chất lượng OCR hoặc các vấn đề bạn thấy ở tài liệu này.
                Phản hồi của bạn giúp hệ thống cải thiện chính xác hơn.
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
                              placeholder="VD: Hệ thống đọc sai họ tên (thiếu dấu). Hoặc: Một số trường bị nhầm giữa bên A và bên B."
                              class="w-full rounded border-gray-200 text-sm px-3 py-2 focus:border-gray-400 focus:ring-0"></textarea>
                    @error('overallNote')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-400">Tối đa 2000 ký tự</p>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="submitOverallComment"
                                class="inline-flex items-center gap-2 rounded bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800 disabled:bg-gray-400">
                            <svg wire:loading wire:target="submitOverallComment"
                                 class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                            </svg>
                            Gửi nhận xét
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <details class="rounded-md border border-gray-200 bg-white">
            <summary class="cursor-pointer px-4 py-2 font-medium text-gray-900">
                Toàn bộ nội dung tài liệu ({{ number_format(strlen($extraction->text_full)) }} ký tự)
            </summary>
            <div class="border-t border-gray-200">
                @include('livewire.partials.paged-text', [
                    'text' => $extraction->text_full,
                    'colorClass' => 'text-gray-800',
                    'fontClass' => 'font-mono text-xs',
                    'maxHeight' => 'max-h-96',
                ])
            </div>
        </details>
    @endif
</div>

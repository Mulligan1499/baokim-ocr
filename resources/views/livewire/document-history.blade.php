@php
    $docTypeLabels = [
        'cccd' => 'CCCD/CMND', 'passport' => 'Hộ chiếu',
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
    $langLabels = ['vi'=>'Tiếng Việt','en'=>'Tiếng Anh','zh'=>'Tiếng Trung','mixed'=>'Đa ngôn ngữ','und'=>'—'];
    $qualityLabels = ['high'=>'Tốt','medium'=>'Trung bình','low'=>'Thấp'];
    $statusLabels = [
        'pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý',
        'done' => 'Xong', 'failed' => 'Thất bại',
    ];
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Lịch sử tài liệu</h1>
            <p class="text-sm text-gray-500">Tổng cộng {{ $page->total() }} tài liệu</p>
        </div>
        <a href="/ocr" wire:navigate
           class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800">
            + Tải tài liệu mới
        </a>
    </div>

    <div class="flex items-center gap-3 text-sm">
        <label class="text-gray-600">Trạng thái:</label>
        <select wire:model.live="status"
                class="rounded border-gray-200 text-sm px-2 py-1 focus:border-gray-400 focus:ring-0">
            <option value="">Tất cả</option>
            <option value="pending">Chờ xử lý</option>
            <option value="processing">Đang xử lý</option>
            <option value="done">Xong</option>
            <option value="failed">Thất bại</option>
        </select>
    </div>

    <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="text-left px-4 py-2">#</th>
                    <th class="text-left px-4 py-2">Tài liệu</th>
                    <th class="text-left px-4 py-2">Loại</th>
                    <th class="text-left px-4 py-2">Ngôn ngữ</th>
                    <th class="text-right px-4 py-2">Độ tin cậy</th>
                    <th class="text-left px-4 py-2">Chất lượng</th>
                    <th class="text-left px-4 py-2">Trạng thái</th>
                    <th class="text-left px-4 py-2">Thời gian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($page->items() as $doc)
                    @php
                        $ext = $doc->extraction;
                        $statusColor = match ($doc->status) {
                            'done' => 'bg-green-100 text-green-800',
                            'processing', 'pending' => 'bg-blue-100 text-blue-800',
                            'failed' => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                        $quality = $ext?->quality;
                        $qualityColor = match ($quality) {
                            'high' => 'text-green-700',
                            'medium' => 'text-yellow-700',
                            'low' => 'text-red-700',
                            default => 'text-gray-400',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ $doc->id }}</td>
                        <td class="px-4 py-2">
                            <a href="/ocr/{{ $doc->id }}" wire:navigate
                               class="text-gray-900 hover:underline font-medium">
                                {{ Str::limit($doc->original_name, 40) }}
                            </a>
                            <p class="text-xs text-gray-500">{{ number_format($doc->size_bytes / 1024, 1) }} KB</p>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-700">
                            {{ $docTypeLabels[$ext?->doc_type] ?? $ext?->doc_type ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-700">
                            {{ $langLabels[$ext?->language_detected] ?? $ext?->language_detected ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-right text-xs">
                            @if ($ext)
                                {{ number_format($ext->confidence_overall * 100, 0) }}%
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs {{ $qualityColor }}">
                            {{ $qualityLabels[$quality] ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                                {{ $statusLabels[$doc->status] ?? $doc->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-500">
                            {{ $doc->created_at?->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                            Chưa có tài liệu nào.
                            <a href="/ocr" wire:navigate class="text-gray-900 underline">
                                Tải tài liệu đầu tiên
                            </a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="text-sm">
        {{ $page->links() }}
    </div>
</div>

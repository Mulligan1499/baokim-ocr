{{--
    Partial render text có thể chứa marker `=== Trang N ===` thành blocks.
    Props:
      $text       — string text (raw_text hoặc translation_vi)
      $colorClass — Tailwind class cho text color
      $fontClass  — 'font-sans' (translation) hoặc 'font-mono' (raw text)
      $maxHeight  — Tailwind max-h class

    Behavior:
      - Single page (không marker) → render flat
      - Multi page → smart pagination "1 2 3 ... N" + button "Mở tất cả"
--}}
@php
    $pages = \App\Support\PageMarkerParser::parse($text);
    $totalPages = count($pages);
@endphp

@if ($totalPages <= 1)
    {{-- Single page: flat render --}}
    <div class="px-4 py-3 {{ $maxHeight ?? 'max-h-72' }} overflow-y-auto">
        <pre class="text-sm whitespace-pre-wrap {{ $fontClass ?? 'font-sans' }} {{ $colorClass ?? 'text-gray-900' }} m-0">{{ $pages[0]['content'] ?? '' }}</pre>
    </div>
@else
    <div x-data="{
            tab: 1,
            showAll: false,
            total: {{ $totalPages }},
            get visiblePages() {
                const t = this.tab, n = this.total;
                if (n <= 7) return Array.from({length: n}, (_, i) => i + 1);
                const set = new Set([1, 2, n - 1, n, t - 1, t, t + 1]);
                const filtered = [...set].filter(x => x >= 1 && x <= n).sort((a, b) => a - b);
                const out = [];
                for (let i = 0; i < filtered.length; i++) {
                    if (i > 0 && filtered[i] - filtered[i-1] > 1) out.push(null);
                    out.push(filtered[i]);
                }
                return out;
            }
         }"
         class="flex flex-col">

        {{-- Tab bar --}}
        <div class="flex flex-wrap items-center gap-1 px-3 py-2 border-b border-gray-200 bg-gray-50">
            <span class="text-xs font-medium text-gray-500 mr-1">Trang:</span>

            <template x-for="p in visiblePages" :key="p === null ? Math.random() : p">
                <span>
                    <span x-show="p === null" class="px-1 text-gray-400 text-xs">…</span>
                    <button x-show="p !== null"
                            type="button"
                            x-text="p"
                            x-on:click="tab = p; showAll = false"
                            :class="(!showAll && tab === p)
                                ? 'bg-white border-gray-400 text-gray-900 shadow-sm'
                                : 'bg-gray-100 border-gray-200 text-gray-600 hover:bg-white'"
                            class="rounded border px-2 py-0.5 text-xs font-medium min-w-[28px] transition"></button>
                </span>
            </template>

            <div class="flex-1"></div>

            <button type="button"
                    x-on:click="showAll = !showAll"
                    :class="showAll ? 'bg-gray-700 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="rounded px-2.5 py-1 text-xs font-medium transition">
                <span x-show="!showAll">📖 Mở tất cả</span>
                <span x-show="showAll" x-cloak>🔽 Thu gọn</span>
            </button>
        </div>

        {{-- Content area --}}
        <div class="px-4 py-3 {{ $maxHeight ?? 'max-h-72' }} overflow-y-auto space-y-3">
            @foreach ($pages as $page)
                <div x-show="showAll || tab === {{ $page['page_no'] }}"
                     class="{{ ! $loop->first ? 'border-t-2 border-dashed border-gray-200 pt-3' : '' }}"
                     x-cloak>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1"
                         x-show="showAll">
                        Trang {{ $page['page_no'] }}
                    </div>
                    <pre class="text-sm whitespace-pre-wrap {{ $fontClass ?? 'font-sans' }} {{ $colorClass ?? 'text-gray-900' }} m-0">{{ $page['content'] }}</pre>
                </div>
            @endforeach
        </div>
    </div>
@endif

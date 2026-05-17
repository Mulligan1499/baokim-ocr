<!DOCTYPE html>
<html lang="vi" class="bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Baokim OCR' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-5xl px-6 py-4 flex items-center justify-between">
            <a href="/ocr" class="text-lg font-semibold text-gray-900">
                Baokim OCR
                <span class="ml-2 text-xs font-normal text-gray-500">KSNB onboarding</span>
            </a>
            <nav class="flex items-center gap-4 text-sm text-gray-600">
                <a href="/ocr" wire:navigate class="hover:text-gray-900">Upload</a>
                <a href="/ocr/history" wire:navigate class="hover:text-gray-900">Lịch sử</a>
                <a href="/api/documentation" target="_blank" class="hover:text-gray-900">API docs</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-8">
        {{ $slot }}
    </main>

    <footer class="mt-12 border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-5xl px-6 py-4 text-xs text-gray-500">
            Baokim KSNB · Harness 7 stages · {{ config('ocr.llm_provider') }} · {{ now()->format('Y-m-d H:i') }}
        </div>
    </footer>

    @livewireScripts
</body>
</html>

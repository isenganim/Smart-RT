@props(['title' => 'Smart RT'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ $title }} - Smart RT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="min-h-screen">
        <header class="border-b bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="font-semibold text-emerald-700">Smart RT</a>
                    <nav class="flex items-center gap-4 text-sm">
                        <a href="{{ route('dashboard') }}" class="text-slate-600 hover:text-slate-900">Dashboard</a>
                        <a href="{{ route('households.index') }}" class="text-slate-600 hover:text-slate-900">Rumah/KK</a>
                        <a href="{{ route('residents.index') }}" class="text-slate-600 hover:text-slate-900">Warga</a>
                    </nav>
                </div>
                <livewire:auth.logout-button />
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-6">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>

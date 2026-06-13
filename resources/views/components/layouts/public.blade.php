@props(['title' => 'Portal Warga'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#533afd">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $title }} - Smart RT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#f6f9fc] text-[#0d253d] antialiased font-sans selection:bg-[#533afd] selection:text-white">
    <div class="min-h-screen relative">
        <!-- Ambient Gradient Mesh Backdrop in the upper third -->
        <div class="absolute top-0 left-0 right-0 h-[33vh] min-h-[260px] -z-10 overflow-hidden pointer-events-none">
            <svg class="w-full h-full" viewBox="0 0 1440 400" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g filter="url(#blur-public)">
                    <!-- Cream base -->
                    <rect width="1440" height="400" fill="#fbf8f3"/>
                    <!-- Orange/Sherbet wash -->
                    <circle cx="200" cy="100" r="280" fill="#fbe5cd" opacity="0.8"/>
                    <!-- Lavender wash -->
                    <circle cx="650" cy="150" r="320" fill="#ebd9fc" opacity="0.85"/>
                    <!-- Indigo/Electric stop -->
                    <circle cx="1050" cy="100" r="380" fill="#b9b9f9" opacity="0.6"/>
                    <circle cx="1150" cy="200" r="280" fill="#533afd" opacity="0.18"/>
                    <!-- Ruby pink / Magenta stop -->
                    <circle cx="1400" cy="60" r="220" fill="#fca5d3" opacity="0.5"/>
                    <circle cx="850" cy="50" r="180" fill="#ea2261" opacity="0.12"/>
                </g>
                <defs>
                    <filter id="blur-public" x="-200" y="-200" width="1840" height="800" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                        <feGaussianBlur stdDeviation="90" result="effect1_foregroundBlur"/>
                    </filter>
                </defs>
            </svg>
        </div>

        <div class="mx-auto flex min-h-screen max-w-xl flex-col bg-white/90 shadow-level2 border-x border-[#e3e8ee] backdrop-blur-xl">
            <header class="flex items-center justify-between px-6 py-4 border-b border-[#e3e8ee] bg-white/70 sticky top-0 backdrop-blur-md z-30">
                <a href="{{ route('portal.home') }}" class="flex items-center gap-2.5 font-sans font-normal text-[#0d253d] group">
                    <span class="relative flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-full bg-[#533afd] text-xs font-semibold text-white shadow-level1 transition-transform duration-300 group-hover:scale-105">
                        <span>RT</span>
                    </span>
                    <span class="tracking-tight text-base font-semibold text-[#0d253d]">Smart RT</span>
                </a>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#b9b9f9]/40 px-3 py-1 text-[11px] font-semibold text-[#4434d4] ring-1 ring-[#533afd]/10">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#533afd] animate-pulse"></span>
                    Portal Warga
                </span>
            </header>

            <main class="flex-1 px-6 py-6 pb-24">
                {{ $slot }}
            </main>

            <footer class="px-6 py-6 text-center text-xs text-[#64748d] border-t border-[#e3e8ee] bg-[#f6f9fc]/50">
                <p>Portal Warga Smart RT &bull; Hubungi pengurus RT jika data Anda belum terdaftar.</p>
            </footer>
        </div>
    </div>
    @livewireScripts
</body>
</html>

@props(['title' => 'Login'])

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
<body class="min-h-screen bg-canvas-soft text-ink antialiased font-sans selection:bg-primary selection:text-white">
    <div class="relative min-h-screen overflow-hidden">
        <!-- Ambient Gradient Mesh Backdrop in the upper third -->
        <div class="pointer-events-none absolute top-0 left-0 right-0 -z-10 h-[40vh] min-h-[280px] overflow-hidden">
            <svg class="h-full w-full" viewBox="0 0 1440 400" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g filter="url(#blur-auth)">
                    <rect width="1440" height="400" fill="#fbf8f3"/>
                    <circle cx="200" cy="100" r="280" fill="#fbe5cd" opacity="0.8"/>
                    <circle cx="650" cy="150" r="320" fill="#ebd9fc" opacity="0.85"/>
                    <circle cx="1050" cy="100" r="380" fill="#b9b9f9" opacity="0.6"/>
                    <circle cx="1150" cy="200" r="280" fill="#533afd" opacity="0.18"/>
                    <circle cx="1400" cy="60" r="220" fill="#fca5d3" opacity="0.5"/>
                    <circle cx="850" cy="50" r="180" fill="#ea2261" opacity="0.12"/>
                </g>
                <defs>
                    <filter id="blur-auth" x="-200" y="-200" width="1840" height="800" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                        <feGaussianBlur stdDeviation="90" result="effect1_foregroundBlur"/>
                    </filter>
                </defs>
            </svg>
        </div>

        <main class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <a href="{{ route('portal.home') }}" class="group mb-8 flex items-center gap-2.5 font-sans text-ink">
                <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white shadow-level1 transition-transform duration-300 group-hover:scale-105">
                    <span>RT</span>
                </span>
                <span class="text-base font-semibold tracking-tight">Smart RT</span>
            </a>

            <section class="w-full max-w-md rounded-xl border border-hairline bg-canvas shadow-level2">
                <div class="border-b border-hairline px-8 py-7 text-center">
                    <h1 class="display-md text-ink">{{ $title }}</h1>
                    <p class="mt-2 text-sm text-ink-mute">Khusus pengurus RT</p>
                </div>
                <div class="px-8 py-8">
                    {{ $slot }}
                </div>
            </section>

            <p class="mt-6 max-w-md text-center text-xs leading-5 text-ink-mute">
                Bukan pengurus? Kembali ke
                <a href="{{ route('portal.home') }}" class="font-medium text-primary hover:text-primary-deep">Portal Warga</a>.
            </p>
        </main>
    </div>
    @livewireScripts
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @stack('head')
    <title>{{ $title ?? 'DocChat' }} · DocChat</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4">
            <a href="{{ route('chat.show') }}" class="text-lg font-semibold tracking-tight">DocChat</a>
            <nav class="flex gap-5 text-sm">
                <a href="{{ route('chat.show') }}" class="{{ request()->routeIs('chat.*') ? 'font-medium text-stone-900' : 'text-stone-500 hover:text-stone-900' }}">Chat</a>
                <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.*') ? 'font-medium text-stone-900' : 'text-stone-500 hover:text-stone-900' }}">Documents</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>

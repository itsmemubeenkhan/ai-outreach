<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-slate-50">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-slate-200 bg-white lg:ml-64">
                    <div class="px-5 py-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="lg:ml-64">
                @if(session('success'))<div class="mx-5 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 lg:mx-8">{{ session('success') }}</div>@endif
                @if($dueFollowUps->isNotEmpty())
                    <div class="mx-5 mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm lg:mx-8">
                        <div class="font-black">🔔 {{ $dueFollowUps->count() }} follow-up {{ Str::plural('alert', $dueFollowUps->count()) }} due</div>
                        @foreach($dueFollowUps as $followUp)<div class="mt-2 flex flex-wrap items-center justify-between gap-2"><span><strong>{{ $followUp->lead->business_name ?: $followUp->lead->phone }}</strong> · {{ $followUp->due_at->format('M j, Y g:i A') }}</span><form method="POST" action="{{ route('tasks.complete', $followUp) }}">@csrf<button class="rounded-lg bg-amber-900 px-3 py-1.5 text-xs font-bold text-white">Mark done</button></form></div>@endforeach
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
        @auth<script type="module">
        (()=>{const check=async()=>{try{const response=await fetch(@json(route('follow-ups.due')),{headers:{Accept:'application/json'}}),data=await response.json(),seen=JSON.parse(sessionStorage.getItem('due-follow-ups')||'[]'),fresh=(data.tasks||[]).filter(task=>!seen.includes(task.id));if(fresh.length){sessionStorage.setItem('due-follow-ups',JSON.stringify([...seen,...fresh.map(task=>task.id)]));alert(`Follow-up due:\n\n${fresh.map(task=>`${task.lead} — ${new Date(task.due_at).toLocaleString()}`).join('\n')}`);}}catch(e){}};check();setInterval(check,60000);})();
        </script>@endauth
    </body>
</html>

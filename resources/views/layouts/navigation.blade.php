<aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-slate-950 text-slate-300 lg:flex">
    <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6"><div class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-500 font-black text-white">AI</div><div><div class="font-bold text-white">Outreach CRM</div><div class="text-xs text-slate-500">Pipeline command</div></div></div>
    @php
        $items = auth()->user()->isAdmin()
            ? [['dashboard','Dashboard','◈'],['leads.index','Leads','◉'],['dialer.index','Power Dialer','☎'],['imports.index','Imports','⇧'],['campaigns.index','Campaigns','◇'],['sending-accounts.index','Sending Accounts','✉'],['outbound-emails.index','Outbound Log','↗'],['inbox.index','Inbox','↩'],['hot-leads.index','Hot Leads','🔥'],['tasks.index','Tasks','✓'],['suppressions.index','Suppression List','⊘']]
            : [['leads.index','Leads','◉'],['dialer.index','Power Dialer','☎']];
        $soon = auth()->user()->isAdmin() ? ['Settings'] : [];
    @endphp
    <nav class="flex-1 space-y-1 overflow-y-auto p-4">
        @foreach($items as [$route,$label,$icon])<a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold {{ request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route) ? 'bg-indigo-500 text-white shadow-lg shadow-indigo-950/30' : 'hover:bg-white/5 hover:text-white' }}"><span>{{ $icon }}</span>{{ $label }}</a>@endforeach
        @if($soon)<div class="px-4 pb-1 pt-5 text-[10px] font-bold uppercase tracking-[.2em] text-slate-600">Phase 1 roadmap</div>@endif
        @foreach($soon as $label)<div class="flex items-center justify-between rounded-xl px-4 py-2.5 text-sm text-slate-500"><span>{{ $label }}</span><span class="rounded bg-white/5 px-1.5 py-0.5 text-[9px]">SOON</span></div>@endforeach
    </nav>
</aside>
<div class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-5 backdrop-blur lg:ml-64 lg:px-8"><div><p class="text-xs font-semibold uppercase tracking-wider text-indigo-500">AI Outreach CRM</p><p class="text-sm text-slate-500">Prospecting automation, human closing.</p></div><div class="flex items-center gap-3"><span class="hidden text-sm font-medium text-slate-700 sm:block">{{ auth()->user()->name }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Sign out</button></form></div></div>

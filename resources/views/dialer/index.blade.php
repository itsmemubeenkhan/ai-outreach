<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-bold">Power Dialer</h1><p class="text-sm text-slate-500">Lead intelligence, website research and Zoom auto-dial in one workspace.</p></x-slot>
    <div class="p-5 lg:p-8">
        @if(!$session)
            <form method="POST" action="{{ route('dialer.start') }}" class="mx-auto max-w-xl rounded-2xl border bg-white p-7 shadow-sm">@csrf
                <h2 class="font-black">Start a calling session</h2>
                <label class="mt-5 block text-sm font-bold">Category<select name="category" class="mt-1 w-full rounded-xl border-slate-200"><option value="">All categories</option>@foreach($categories as $category)<option>{{ $category }}</option>@endforeach</select></label>
                <div class="mt-4 rounded-xl bg-indigo-50 p-4 text-sm text-indigo-900"><strong>30-second research pause</strong><p class="mt-1 text-xs">After Zoom reports a call ended, the next lead and website load immediately. The next call starts after 30 seconds.</p></div>
                <button class="mt-6 w-full rounded-xl bg-indigo-600 py-3 font-bold text-white">Start Dialer</button>
            </form>
        @else
            @php($lead = $session->currentLead)
            <div class="grid gap-5 xl:grid-cols-[390px_minmax(0,1fr)]">
                <aside class="space-y-5">
                    <section class="rounded-2xl border bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between"><div class="text-xs font-bold uppercase tracking-wider text-indigo-600">Runtime AI Sales Assistant</div><span id="ai-provider" class="text-[10px] font-bold text-slate-400">OPENROUTER</span></div>
                        <div id="ai-loading" class="mt-5 text-sm text-slate-500">Reading the website and generating a grounded recommendation…</div>
                        <div id="ai-error" class="mt-5 hidden rounded-lg bg-rose-50 p-3 text-sm text-rose-700"></div>
                        <div id="ai-result" class="hidden">
                            <h2 id="ai-best-offer" class="mt-3 text-xl font-black text-slate-950"></h2>
                            <p id="ai-summary" class="mt-3 text-sm text-slate-600"></p>
                            <h3 class="mt-5 text-xs font-black uppercase tracking-wide text-slate-500">Why this offer</h3><ul id="ai-reasons" class="mt-2 space-y-2 text-sm text-slate-700"></ul>
                            <h3 class="mt-5 text-xs font-black uppercase tracking-wide text-slate-500">Opening pitch</h3><p id="ai-opening" class="mt-2 rounded-lg bg-indigo-50 p-3 text-sm text-indigo-950"></p>
                            <h3 class="mt-5 text-xs font-black uppercase tracking-wide text-slate-500">Discovery questions</h3><ul id="ai-questions" class="mt-2 space-y-2 text-sm text-slate-700"></ul>
                        </div>
                    </section>

                    <section class="rounded-2xl border bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between"><h2 class="font-black">Complete lead data</h2><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">Score {{ $lead?->lead_score ?? 0 }}</span></div>
                        <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-4 text-sm">
                            @foreach([
                                'Business' => $lead?->business_name, 'Contact' => $lead?->contact_person ?: trim(($lead?->first_name ?? '').' '.($lead?->last_name ?? '')),
                                'Email' => $lead?->email, 'Corporate email' => $lead?->corporate_email, 'Phone' => $lead?->phone, 'Phone type' => $lead?->phone_type,
                                'Category' => $lead?->category, 'Employees' => $lead?->number_of_employees, 'Website' => $lead?->website, 'Source' => $lead?->source,
                                'Status' => $lead?->lead_status, 'Email status' => $lead?->email_status, 'Street' => $lead?->street_address, 'City' => $lead?->city,
                                'State' => $lead?->state, 'ZIP' => $lead?->zip_code, 'Country' => $lead?->country, 'Last contacted' => $lead?->last_contacted_at?->format('M j, Y H:i'),
                            ] as $label => $value)
                                <div class="min-w-0"><dt class="text-xs font-bold uppercase text-slate-400">{{ $label }}</dt><dd class="mt-1 break-words font-medium text-slate-800">{{ filled($value) ? $value : '—' }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                </aside>

                <main class="space-y-5">
                    <section class="rounded-2xl bg-slate-950 p-7 text-white">
                        <div class="flex justify-between gap-4"><div><div class="text-xs font-bold uppercase tracking-wider text-indigo-300">{{ $session->status }} · {{ $session->category ?: 'All categories' }}</div><h2 class="mt-2 text-3xl font-black">{{ $lead?->business_name ?: 'Queue complete' }}</h2><p class="mt-2 text-slate-300">{{ $lead?->contact_person }} · {{ $lead?->phone }}</p></div><div class="text-right"><div class="text-3xl font-black">{{ $session->calls_completed }}</div><div class="text-xs text-slate-400">calls completed</div></div></div>
                        @if($lead && $session->status === 'active')<button id="dial-button" class="mt-7 rounded-xl bg-emerald-500 px-6 py-3 font-black text-slate-950 disabled:opacity-60">☎ Dial with Zoom</button><p id="dialer-status" class="mt-3 text-sm text-slate-300"></p>@endif
                        <div class="mt-5 flex gap-2">@foreach(['pause','resume','skip','stop'] as $action)<form method="POST" action="{{ route('dialer.control', $session) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">{{ ucfirst($action) }}</button></form>@endforeach</div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b p-4"><div><h2 class="font-black">Website intelligence</h2><p class="text-xs text-slate-500">Server-read content replaces blocked iframe previews.</p></div>@if($websiteUrl)<a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white">Open full website ↗</a>@endif</div>
                        <div id="website-intelligence" class="p-6 text-sm text-slate-500">Reading website content…</div>
                    </section>

                    <section id="disposition-panel" class="hidden rounded-2xl border bg-white p-6"><h3 class="font-black">Call outcome</h3><form id="disposition-form" method="POST" class="mt-4 grid gap-3 md:grid-cols-2">@csrf<select name="disposition" class="rounded-xl border-slate-200">@foreach(['answered','no_answer','busy','callback','interested','wrong_number','not_interested'] as $value)<option value="{{ $value }}">{{ str($value)->replace('_',' ')->title() }}</option>@endforeach</select><input name="notes" placeholder="Call notes" class="rounded-xl border-slate-200"><button class="rounded-xl bg-slate-900 px-4 py-3 font-bold text-white md:col-span-2">Save & Load Next Lead</button></form></section>
                </main>
            </div>
        @endif

        <section class="mx-auto mt-8 rounded-2xl border bg-white"><div class="border-b p-5 font-black">Recent calls</div>@forelse($recentCalls as $call)<div class="grid gap-2 border-b p-4 text-sm md:grid-cols-5"><strong>{{ $call->lead->business_name }}</strong><span>{{ $call->phone_number }}</span><span>{{ $call->disposition ?: $call->status }}</span><span>{{ $call->duration_seconds ? gmdate('i:s', $call->duration_seconds) : '—' }}</span><span class="truncate">{{ $call->ai_summary ?: str($call->summary_status)->replace('_',' ')->title() }}</span></div>@empty<div class="p-10 text-center text-slate-500">No calls recorded.</div>@endforelse<div class="p-4">{{ $recentCalls->links() }}</div></section>
    </div>

    @if($session?->currentLead)
        <script>
            const dialButton = document.getElementById('dial-button'); let activeCallId = null; let pollTimer = null;
            const listItems=(values)=>Array.isArray(values)?values.map(value=>`<li class="rounded-lg bg-slate-50 px-3 py-2">${escapeHtml(String(value))}</li>`).join(''):'';
            function escapeHtml(value){const node=document.createElement('div');node.textContent=value;return node.innerHTML;}
            const loadInsight=async()=>{try{const response=await fetch(@json(route('leads.sales-insight',$lead)),{headers:{'Accept':'application/json'}});const data=await response.json();if(!response.ok)throw new Error(data.message||'Analysis failed.');document.getElementById('ai-loading').classList.add('hidden');document.getElementById('ai-result').classList.remove('hidden');document.getElementById('ai-best-offer').textContent=data.analysis.best_offer||'Sales recommendation';document.getElementById('ai-summary').textContent=data.analysis.summary||'';document.getElementById('ai-reasons').innerHTML=listItems(data.analysis.reasons);document.getElementById('ai-opening').textContent=data.analysis.opening_pitch||'';document.getElementById('ai-questions').innerHTML=listItems(data.analysis.discovery_questions);const site=data.website;document.getElementById('website-intelligence').innerHTML=`<h3 class="text-xl font-black text-slate-950">${escapeHtml(site.title||'Website content')}</h3><p class="mt-2 text-slate-600">${escapeHtml(site.description||'No meta description found.')}</p><h4 class="mt-5 text-xs font-black uppercase tracking-wide text-slate-400">Pages and service signals</h4><ul class="mt-2 grid gap-2 md:grid-cols-2">${listItems(site.headings)}</ul><h4 class="mt-5 text-xs font-black uppercase tracking-wide text-slate-400">AI website findings</h4><ul class="mt-2 space-y-2">${listItems(data.analysis.website_findings)}</ul>`;}catch(error){document.getElementById('ai-loading').classList.add('hidden');document.getElementById('ai-error').textContent=error.message;document.getElementById('ai-error').classList.remove('hidden');document.getElementById('website-intelligence').textContent=error.message;}};
            loadInsight();
            const dial = async () => { dialButton.disabled = true; document.getElementById('dialer-status').textContent = 'Calling in Zoom…'; const response = await fetch(@json(route('dialer.dial', $session)), {method:'POST', headers:{'X-CSRF-TOKEN':@json(csrf_token()), 'Accept':'application/json'}}); const data = await response.json(); if(data.dial_url){ activeCallId=data.call_id; document.getElementById('disposition-form').action=@json(url('/calls'))+'/'+data.call_id+'/disposition'; document.getElementById('disposition-panel').classList.remove('hidden'); window.location.href=data.dial_url; pollTimer=setInterval(checkCallState,2000); } else { dialButton.disabled=false; } };
            const checkCallState = async () => { const response=await fetch(@json(route('dialer.state',$session)),{headers:{'Accept':'application/json'}}); const data=await response.json(); if(activeCallId&&data.latest_call?.id===activeCallId&&data.latest_call.status==='completed'){ clearInterval(pollTimer); window.location.href=@json(route('dialer.index')).concat('?auto=1&wait=30'); } };
            dialButton?.addEventListener('click',dial);
            const params=new URLSearchParams(window.location.search); if(params.get('auto')==='1'){let remaining=Number(params.get('wait')||30);document.getElementById('dialer-status').textContent=`Research the lead and website. Auto-dial in ${remaining}s…`;const countdown=setInterval(()=>{remaining--;document.getElementById('dialer-status').textContent=`Research the lead and website. Auto-dial in ${remaining}s…`;if(remaining<=0){clearInterval(countdown);dialButton?.click();}},1000);}
        </script>
    @endif
</x-app-layout>

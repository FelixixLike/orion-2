<div class="space-y-1">
    <div class="text-xs text-slate-600">{{ $title }}</div>
    @if (empty($codes))
        <div class="text-sm text-slate-500">Aún no hay registros.</div>
    @else
        <div class="flex flex-wrap">
            @foreach ($codes as $code)
                <span class="mr-2 mb-2 inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {{ $code }}
                </span>
            @endforeach
        </div>
    @endif
</div>

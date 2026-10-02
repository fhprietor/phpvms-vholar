@once
<style>
.lb-pilot-list { list-style:none;margin:0;padding:0; }
.lb-pilot-entry {
  display:flex;align-items:center;gap:10px;
  padding:9px 14px;
  border-bottom:1px solid rgba(255,255,255,0.045);
  transition:background 0.12s;
}
.lb-pilot-entry:last-child { border-bottom:none; }
.lb-pilot-entry:hover { background:rgba(255,255,255,0.03); }
.lb-pilot-ident {
  font-size:0.88rem;font-weight:800;letter-spacing:0.04em;
  text-decoration:none;color:var(--vh-text) !important;
}
.lb-pilot-ident:hover { color:var(--vh-silver) !important; }
.lb-pilot-name { font-size:0.75rem;color:var(--vh-text-muted);letter-spacing:0.03em;margin-top:1px; }
.lb-pilot-rank {
  font-size:0.62rem;font-weight:700;color:var(--vh-text-muted);letter-spacing:0.04em;
  white-space:nowrap;background:var(--vh-primary-soft);border-radius:4px;
  padding:2px 7px;flex-shrink:0;
}
</style>
@endonce

<ul class="lb-pilot-list">
    @foreach ($users as $u)
        <li class="lb-pilot-entry">
            <div style="flex-shrink:0;">
                @if ($u->avatar)
                    <img src="{{ $u->avatar->url }}" class="rounded-circle"
                         style="width:36px;height:36px;object-fit:cover;">
                @else
                    <img src="{{ public_asset('images/logo.png') }}" class="rounded-circle"
                         style="width:36px;height:36px;object-fit:contain;background:var(--vh-surface);padding:3px;">
                @endif
            </div>
            <div style="flex:1;min-width:0;">
                <a href="{{ route('frontend.users.show.public', [$u->id]) }}" class="lb-pilot-ident">{{ $u->ident }}</a>
                <div class="lb-pilot-name">{{ $u->name_private }}</div>
            </div>
            @if($u->rank)
                <span class="lb-pilot-rank">{{ $u->rank->name }}</span>
            @endif
        </li>
    @endforeach
</ul>

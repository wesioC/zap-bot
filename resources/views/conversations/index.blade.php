@extends('layouts.app')

@section('content')
<style>
  .grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; }
  .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .muted { color:#6b7280; font-size:12px; }
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600; }
  .badge-active { background:#dcfce7; color:#166534; }
  .badge-completed { background:#e5e7eb; color:#374151; }
  .badge-archived { background:#fee2e2; color:#991b1b; }
  .btn { display:inline-block; padding:6px 10px; border-radius:8px; font-size:12px; font-weight:600; border:1px solid #e5e7eb; background:#f9fafb; cursor:pointer; }
  .btn + .btn { margin-left:6px; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
  <h1 style="font-size:18px;margin:0;">Conversas</h1>
  @if(session('ok')) <div class="muted">{{ session('ok') }}</div> @endif
</div>

<div class="grid">
  @forelse($conversations as $c)
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="font-weight:700;">#{{ $c->id }}</div>
        @php
          $badgeClass = $c->status === 'active' ? 'badge-active' : ($c->status === 'completed' ? 'badge-completed' : 'badge-archived');
        @endphp
        <span class="badge {{ $badgeClass }}">{{ $c->status }}</span>
      </div>

      <div class="muted" style="margin-top:6px;">
        @if(isset($c->customer_id))
          Cliente: <strong>{{ $c->customer_id }}</strong>
        @elseif(isset($c->customer) && $c->customer?->id)
          Cliente: <strong>{{ $c->customer->id }}</strong>
        @else
          Cliente: <em>—</em>
        @endif
      </div>

    <div class="muted" style="margin-top:6px;">
      Telefone: <strong>{{ $c->customer?->phone ?? '—' }}</strong>
      @if($c->customer?->phone)
        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $c->customer->phone) }}" target="_blank" style="margin-left:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.567-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.513" fill="#25D366"/>
        </svg>
        </a>
      @endif
    </div></svg>
    

      <div class="muted" style="margin-top:6px;">
        Última atividade:
        <strong>{{ optional($c->last_message_at ?? $c->updated_at)->format('d/m/Y H:i') }}</strong>
      </div>

      <div style="margin-top:10px;">
        <form method="POST" action="{{ route('conversations.updateStatus', $c) }}" style="display:inline;">
          @csrf
          <input type="hidden" name="status" value="active">
          <button class="btn" {{ $c->status === 'active' ? 'disabled' : '' }}>Ativar</button>
        </form>

        <form method="POST" action="{{ route('conversations.updateStatus', $c) }}" style="display:inline;">
          @csrf
          <input type="hidden" name="status" value="completed">
          <button class="btn" {{ $c->status === 'completed' ? 'disabled' : '' }}>Completar</button>
        </form>
      </div>
    </div>
  @empty
    <div class="muted">Nenhuma conversa encontrada.</div>
  @endforelse
</div>

<div style="margin-top:12px;">
  {{ $conversations->links() }}
</div>
@endsection

@if($awards->count() > 0)
  <div class="card border-blue-bottom">
    <div class="card-body">
      <table class="table">
        <tr>
          <td>Ident</td>
          <td>Name</td>
          <td>Award</td>
          <td>Date</td>
        </tr>
        @foreach($awards as $a)
          <tr>
            <td style="width:44px;" class="ps-2">
              @if (optional($a->user)->avatar)
                <img src="{{ $a->user->avatar->url }}" class="rounded-circle"
                    style="width:34px;height:34px;object-fit:cover;">
              @else
                <img src="{{ public_asset('images/logo.png') }}" class="rounded-circle"
                    style="width:34px;height:34px;object-fit:contain;background:#1f1c27;padding:3px;">
              @endif
            </td>
            <td>{{ optional($a->user)->ident }}</td>
            <td>{{ optional($a->user)->name_private }}</td>
            <td>{{ optional($a->award)->name }}</td>
            <td>{{ $a->created_at->format('d.M.Y H:i') }}</td>
          </tr>
        @endforeach
      </table>
    </div>
  </div>
@endif
@php
  // Portada: si existe una imagen del tour en el tema se usa como foto de portada
  // (public/assets/themes/vholar/tours/{CODE}.jpg|jpeg|png|webp); si no, se queda el
  // degradado del CSS con el codigo del tour de fondo. Asi se imita la tarjeta con
  // foto de la web de referencia sin tocar la tabla del modulo (DS_Tour no tiene
  // columna de imagen).
  $cover = null;
  foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
      if (file_exists(public_path("assets/themes/vholar/tours/{$tour->tour_code}.{$ext}"))) {
          $cover = public_asset("assets/themes/vholar/tours/{$tour->tour_code}.{$ext}");
          break;
      }
  }

  $url = route('DSpecial.tour', [$tour->tour_code]);
  $daysLeft = $carbon_now->lte($tour->end_date) ? (int) $carbon_now->diffInDays($tour->end_date) : 0;

  if ($carbon_now > $tour->end_date) {
      $badge = ['Finalizado', 'is-past'];
  } elseif ($carbon_now < $tour->start_date) {
      $badge = ['Proximo', 'is-next'];
  } elseif ($daysLeft <= 30) {
      $badge = ['Termina en '.$daysLeft.' d', 'is-soon'];
  } else {
      $badge = ['En curso', 'is-now'];
  }

  $nm = isset($leg_distance) && $leg_distance !== null ? (float) $leg_distance : null;
@endphp
<div class="col-md-4 col-lg-3">
  <div class="card vh-tour-card h-100">
    <a href="{{ $url }}" class="vh-tour-cover" @if($cover) style="background-image: url('{{ $cover }}');" @endif>
      @unless($cover)
        <span class="vh-tour-cover-code">{{ $tour->tour_code }}</span>
      @endunless
      @if($tour->airline)
        <img class="vh-tour-airline" src="{{ $tour->airline->logo }}" alt="{{ $tour->airline->name }}">
      @endif
      <span class="vh-tour-badge {{ $badge[1] }}">{{ $badge[0] }}</span>
    </a>

    <div class="card-body p-3">
      <h6 class="vh-tour-title mb-1"><a href="{{ $url }}">{{ $tour->tour_name }}</a></h6>

      <div class="vh-tour-desc mb-2">
        @if(filled($tour->tour_desc))
          {!! \Illuminate\Support\Str::limit(strip_tags($tour->tour_desc), 130) !!}
        @else
          <span class="fst-italic">—</span>
        @endif
      </div>

      <div class="vh-tour-meta">
        <i class="bi {{ $tour->airline ? 'bi-building' : 'bi-globe2' }}"></i>
        {{ $tour->airline ? __('DSpecial::tours.tairline') : __('DSpecial::tours.topen') }}
        &nbsp;·&nbsp; <i class="bi bi-hash"></i> {{ $tour->tour_code }}
        @if(filled($tour->tour_fplremark))
          <i class="bi bi-info-circle ms-1" title="{{ 'FPL Remark: '.$tour->tour_fplremark }}"></i>
        @endif
      </div>

      <div class="vh-tour-meta mt-1">
        <i class="bi bi-calendar3"></i>
        {{ $tour->start_date->format('d M Y') }} — {{ $tour->end_date->format('d M Y') }}
      </div>
    </div>

    <div class="card-footer vh-tour-footer p-2">
      <span><i class="bi bi-signpost-2"></i> {{ $tour->legs_count }} legs</span>
      @if($nm)
        <span>{{ number_format($nm) }} nm ({{ number_format($nm * 1.852) }} km)</span>
      @endif
    </div>

    @if($tour->tour_token > 0 && isset($user_tokens) && !in_array($tour->tour_token, $user_tokens))
      <div class="card-footer vh-tour-footer p-2 justify-content-end">
        Requiere <a class="ms-1" href="{{ route('DSpecial.market').'?cat='.$market_cat }}"><i class="bi bi-bag"></i> {{ optional($tour->token)->name }}</a>
      </div>
    @endif
  </div>
</div>

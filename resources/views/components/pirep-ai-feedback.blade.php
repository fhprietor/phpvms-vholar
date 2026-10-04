{{--
  Tarjeta de retroalimentación automática del vuelo.

  Va en su propio parcial (y no inline en pireps/show) por dos razones: mantiene
  la vista del PIREP legible, y permite renderizarla en los tests. Renderizar
  pireps/show completo en la suite no es posible: referencia rutas del módulo
  DisposableBasic (inactivo) y el tema activo en la base de datos de test es
  `seven`, no `vholar`.

  COLORES: se usan los tokens --vh-* de tokens.css, NO las clases semánticas de
  Bootstrap (bg-warning, text-dark, alert-warning...). El <html> lleva
  data-bs-theme="dark", y en modo oscuro Bootstrap redefine las variables de las
  alertas: alert-warning pasa a fondo #332701 (mostaza oscuro) con texto #ffda6a.
  Al ponerle encima la clase `text-dark` el texto quedaba en #212529, es decir
  1.05:1 de contraste, prácticamente invisible. Las clases semánticas de
  Bootstrap tampoco siguen las reglas de contraste que documenta tokens.css.

  Esquema aplicado, medido con la fórmula de luminancia relativa WCAG 2.1:
    acento (--vh-<sev>) sobre su propio -soft .... 4.86:1 a 6.77:1   (AA)
    texto (--vh-text) sobre el -soft ............. 12.2:1 a 12.7:1   (AAA)
  El cuerpo va en --vh-text y el color de gravedad se reserva al acento (borde,
  icono y etiqueta), que es el patrón documentado en tokens.css.

  Espera $aiFeedback (App\Models\PirepAiFeedback). Si viene vacío no pinta nada.
--}}
@if(!empty($aiFeedback))
  @php
    $sev = (int) $aiFeedback->severity;
    $sevIcon   = $sev === 0 ? 'check-circle-fill' : ($sev === 1 ? 'exclamation-triangle-fill' : 'x-octagon-fill');
    $sevAccent = $sev === 0 ? '--vh-success' : ($sev === 1 ? '--vh-warning' : '--vh-danger');
    $sevSoft   = $sev === 0 ? '--vh-success-soft' : ($sev === 1 ? '--vh-warning-soft' : '--vh-danger-soft');
  @endphp
  <div class="card vholar-card mt-3" style="border-color: var({{ $sevAccent }})">
    <div class="card-header d-flex align-items-center gap-2">
      <h5 class="mb-0"><i class="bi bi-person-video3"></i> Análisis del Instructor</h5>
      <span class="badge"
            style="background: var({{ $sevSoft }}); color: var({{ $sevAccent }}); border: 1px solid var({{ $sevAccent }});">
        <i class="bi bi-{{ $sevIcon }} me-1"></i>{{ $aiFeedback->severityLabel() }}
      </span>
      <span class="ms-auto small text-muted" title="Análisis generado automáticamente a partir de la telemetría ACARS">
        <i class="bi bi-robot"></i> {{ $aiFeedback->model }}
      </span>
    </div>
    <div class="card-body">
      @if(!empty($aiFeedback->verdict))
        <p class="fs-5 mb-3 fw-semibold" style="color: var({{ $sevAccent }})">{{ $aiFeedback->verdict }}</p>
      @endif

      <div class="row g-3">
        @if(!empty($aiFeedback->good_points))
          <div class="col-md-6">
            <p class="small text-muted mb-1 fw-semibold">
              <i class="bi bi-hand-thumbs-up-fill me-1" style="color: var(--vh-success)"></i>Puntos fuertes
            </p>
            <ul class="list-unstyled mb-0 small">
              @foreach($aiFeedback->good_points as $point)
                <li class="mb-1"><i class="bi bi-check2 me-1" style="color: var(--vh-success)"></i>{{ $point }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @if(!empty($aiFeedback->errors))
          <div class="col-md-6">
            <p class="small text-muted mb-1 fw-semibold">
              <i class="bi bi-tools me-1" style="color: var(--vh-danger)"></i>A revisar
            </p>
            <ul class="list-unstyled mb-0 small">
              @foreach($aiFeedback->errors as $error)
                <li class="mb-1"><i class="bi bi-arrow-right-short me-1" style="color: var(--vh-danger)"></i>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif
      </div>

      @if(!empty($aiFeedback->areas))
        @php
          $areaLabels = [
            'arranque'    => 'Arranque',
            'rodaje'      => 'Rodaje',
            'despegue'    => 'Despegue',
            'aterrizaje'  => 'Aterrizaje',
            'combustible' => 'Combustible',
            'economia'    => 'Economía',
          ];
        @endphp
        <hr class="my-3" style="border-color: var(--vh-border)">
        <p class="small text-muted mb-2 fw-semibold">
          <i class="bi bi-clipboard2-pulse me-1"></i>Valoración por área
        </p>
        <div class="row g-2">
          @foreach($aiFeedback->areas as $area)
            @php
              $av   = (int) ($area['valoracion'] ?? 1);
              $aVar = $av === 0 ? '--vh-success' : ($av === 1 ? '--vh-warning' : '--vh-danger');
              $aIcon = $av === 0 ? 'check-circle-fill' : ($av === 1 ? 'exclamation-triangle-fill' : 'x-octagon-fill');
            @endphp
            <div class="col-md-6">
              <div class="p-2 h-100 rounded" style="background: var(--vh-surface); border-left: 3px solid var({{ $aVar }});">
                <span class="small fw-semibold" style="color: var({{ $aVar }})">
                  <i class="bi bi-{{ $aIcon }} me-1"></i>{{ $areaLabels[$area['area']] ?? ucfirst($area['area']) }}
                </span>
                <span class="small d-block mt-1">{{ $area['nota'] ?? '' }}</span>
              </div>
            </div>
          @endforeach
        </div>
      @endif

      @if(!empty($aiFeedback->action))
        {{-- Fondo -soft con el acento en el borde y el texto en --vh-text.
             Nada de texto coloreado sobre su propio color. --}}
        <div class="alert mt-3 mb-0 py-2"
             style="background: var({{ $sevSoft }}); border: 1px solid var({{ $sevAccent }}); color: var(--vh-text);">
          <span class="fw-semibold" style="color: var({{ $sevAccent }})">
            <i class="bi bi-bullseye me-1"></i>Próximo vuelo:
          </span>
          {{ $aiFeedback->action }}
        </div>
      @endif

      <p class="small text-muted mb-0 mt-3">
        <i class="bi bi-info-circle me-1"></i>Análisis orientativo generado a partir de la telemetría
        de tu cliente ACARS. No afecta a la validez del vuelo.
      </p>
    </div>
  </div>
@endif

@component('mail::message')
  # Análisis de tu vuelo {{ $pirep->ident }}

  {{ $pirep->dpt_airport_id }} → {{ $pirep->arr_airport_id }}
  @if($pirep->aircraft)
    · {{ $pirep->aircraft->ident }}
  @endif
  · {{ $feedback->severityLabel() }}

  @if(!empty($feedback->verdict))
    > **{{ $feedback->verdict }}**
  @endif

  @if(!empty($metrics))
    ## Datos del aterrizaje

    @foreach($metrics as $label => $value)
      - **{{ $label }}:** {{ $value }}
    @endforeach
  @endif

  @if(!empty($feedback->good_points))
    ## Lo que hiciste bien

    @foreach($feedback->good_points as $point)
      - {{ $point }}
    @endforeach
  @endif

  @if(!empty($feedback->errors))
    ## A revisar

    @foreach($feedback->errors as $error)
      - {{ $error }}
    @endforeach
  @endif

  @if(!empty($feedback->areas))
    ## Valoración por área

    @foreach($feedback->areas as $area)
      - **{{ ucfirst($area['area']) }}** ({{ $feedback->areaLabel((int) ($area['valoracion'] ?? 1)) }}): {{ $area['nota'] ?? '' }}
    @endforeach
  @endif

  @if(!empty($feedback->action))
    ## Para tu próximo vuelo

    {{ $feedback->action }}
  @endif

  @component('mail::button', ['url' => route('frontend.pireps.show', [$pirep->id])])
    Ver el PIREP
  @endcomponent

  Este análisis lo genera un instructor automático a partir de la telemetría que
  envió tu cliente ACARS. Es orientativo: no afecta a la validez del vuelo.

  Gracias,<br>
  {{ config('app.name') }}
@endcomponent

@component('mail::message')
  # PIREP Accepted!

  Your PIREP has been accepted

  @if($pirep->comments->count() > 0)
    ## Observaciones
    @foreach($pirep->comments as $comment)
      - {{ $comment->comment }}
    @endforeach
  @endif

  @component('mail::button', ['url' => route('frontend.pireps.show', [$pirep->id])])
    View PIREP
  @endcomponent

  Thanks,<br>
  {{ config('app.name') }}
@endcomponent

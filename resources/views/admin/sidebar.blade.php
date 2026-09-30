<div class="sidebar" data-background-color="black" data-active-color="info">

  <!--
      Tip 1: you can change the color of the sidebar's background using: data-background-color="white | black"
      Tip 2: you can change the color of the active button using the data-active-color="primary | info | success | warning | danger"
  -->


  <div class="sidebar-wrapper">
    <div class="logo" style="background: var(--vh-surface2, #2a2633); margin: 0; text-align: center; padding: 18px 0;">
      <a href="{{ url('/dashboard') }}">
        <img src="{{ public_asset('images/vholar_logoweb.png') }}" height="38" alt="Vholar">
      </a>
    </div>

    <ul class="nav">
      @include('admin.menu')
    </ul>

    <br/>

    <div class="row" style="margin-bottom: 20px;">
      <div class="col-xs-12 text-center">
        <a class="small"
           style="cursor: pointer"
           data-container="body"
           data-toggle="popover"
           data-placement="right"
           data-content="{{$version_full}}">
          version {{ $version }}
        </a>
      </div>
    </div>
<div class="row" style="margin-bottom: 10px;">
    <div class="col-xs-12 text-center">
        @foreach(config('languages') as $code => $language)
            <a href="{{ url('lang/' . $code) }}"
               style="margin: 0 4px; font-size: 12px; {{ app()->getLocale() === $code ? 'font-weight:bold;' : 'color:#aaa;' }}">
                {{ $language['display'] }}
            </a>
        @endforeach
    </div>
</div>
  </div>
</div>

<tr>
  <td class="header">
    <a href="{{ $url }}" style="display: inline-block;">
      @if (trim($slot) === 'Laravel' || trim($slot) === '')
        <img src="{{ url('/images/vholar_logoweb.png') }}" class="logo" alt="Vholar Virtual Airlines" style="height:40px;">
      @else
        {{ $slot }}
      @endif
    </a>
  </td>
</tr>

<div style="display:flex; justify-content:center;">
    <div style="width:240px; height:348px; overflow:hidden;">
        <div style="transform:scale(0.8); transform-origin:top left; width:300px;">
            <a href="https://metar-taf.com/es/metar/{{ strtoupper($config['icao']) }}" id="metartaf-ons7HDjV" style="font-size:18px; font-weight:500; color:#EDEAF1; width:300px; height:435px; display:block">METAR {{ strtoupper($config['icao']) }}</a>
            <script async defer crossorigin="anonymous" src="https://metar-taf.com/es/embed-js/{{ strtoupper($config['icao']) }}?bg_color=28212F&qnh=hPa&rh=rh&target=ons7HDjV"></script>
        </div>
    </div>
</div>

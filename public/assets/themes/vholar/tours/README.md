# Portadas de los tours

Deja aqui la imagen de portada de un tour usando el **codigo del tour** como nombre:

    public/assets/themes/vholar/tours/ANDES.jpg

Se admiten `jpg`, `jpeg`, `png` y `webp`. La vista del tema
(`resources/views/layouts/vholar/modules/DisposableSpecial/tours/table.blade.php`) la
usa como fondo de la tarjeta; si no hay imagen, la portada es un degradado con el
codigo del tour de fondo.

Recomendado: proporcion 16:9 (por ejemplo 800x400) y menos de ~300 KB. El recorte lo
hace el CSS (`background-size: cover`), asi que se recorta por el centro.

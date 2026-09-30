# 1. Limpiar caché de configuración
php artisan config:clear

# 2. Limpiar caché de rutas
php artisan route:clear

# 3. Limpiar caché de vistas (Blade)
php artisan view:clear

# 4. Limpiar caché de aplicaciones (cache driver)
php artisan cache:clear

# 5. Limpiar caché de Laravel data wrapper
php artisan debugbar:clear  # Si tienes debugbar instalado

# 6. Recargar la caché de configuración (opcional, pero recomendado)
php artisan config:cache

# 7. Recargar caché de rutas (opcional)
php artisan route:cache

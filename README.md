Plugin RideCR Booking Search

1. Descripción General
Plugin WordPress que integra el sistema de búsqueda y reservas de RideCR, permitiendo a los usuarios:

Buscar rutas disponibles

Seleccionar parámetros de viaje

Redirigirse al sistema de booking con los parámetros pre-cargados

2. Requisitos del Sistema

WordPress 5.6 o superior

PHP 7.4+

Habilitado WP_DEBUG para desarrollo

Acceso a las APIs de RideCR

3. Instalación
Descargar el archivo ZIP del plugin

Ir a WordPress Admin → Plugins → Añadir nuevo → Subir plugin

Activar el plugin "RideCR Booking Search"

4. Uso Básico
Insertar el shortcode en páginas/posts:

[ridecr_search]

5. Estructura de Archivos

ridecr-booking-search/
├── ridecr-booking-search.php        # Archivo principal
├── assets/
│   ├── css/style.css               # Estilos del formulario
│   └── js/script.js                # Lógica frontend
└── includes/
    └── api-endpoints.php           # Manejo de APIs externas

6. Shortcodes Disponibles
Shortcode	Descripción	Parámetros
[ridecr_search]	Muestra el formulario de búsqueda

7. Hooks y Filtros
7.1 Actions
php
add_action('wp_ajax_ridecr_api_request', [$this, 'handle_api_request']);
add_action('wp_ajax_nopriv_ridecr_api_request', [$this, 'handle_api_request']);

7.2 Filtros (Disponibles para extensión)
php
apply_filters('ridecr_before_search', $requestData);
apply_filters('ridecr_after_response', $apiResponse);

8. Manejo de Errores
Códigos de error comunes:
Código	Descripción	Solución
401	No autorizado	Verificar tokens
404	Ruta no encontrada	Validar parámetros
500	Error interno	Revisar logs
   
9. Personalización
9.1 Sobreescribir estilos
Añadir CSS en el theme:
css
/* Cambiar color del botón */
.booking-form .book-now-btn {
    background-color: #ff5722 !important;
}

9.2 Modificar parámetros
php
add_filter('ridecr_before_search', function($data) {
    $data['custom_param'] = 'value';
    return $data;
});

10. Consideraciones de Seguridad
    
Todos los requests pasan por WordPress admin-ajax

Sanitización de datos implementada

Nonce verification en cada petición

Tokens almacenados en memoria (no en base de datos)

11. Registro de Actividad
Los logs se guardan en:

wp-content/debug.log
Ejemplo de entrada:

[RideCR API] 2025-05-10 10:00:45 - GET /api/home - 200 OK

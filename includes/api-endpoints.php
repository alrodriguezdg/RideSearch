<?php
class RideCR_API_Endpoints {
    private $credentials = [
        'email' => 'smarteamcr@ridecr.com',
        'password' => 'T&O@WO=TAzAth7ne$p=ZUJacReThe='
    ];
    
    public function __construct() {
        add_action('wp_ajax_ridecr_api_request', [$this, 'handle_api_request']);
        add_action('wp_ajax_nopriv_ridecr_api_request', [$this, 'handle_api_request']);
    }
    
    public function handle_api_request() {
        try {
            // Verificar nonce
            if (!check_ajax_referer('ridecr_nonce', 'nonce', false)) {
                throw new Exception('Invalid nonce');
            }
            
            $endpoint = isset($_POST['endpoint']) ? sanitize_text_field($_POST['endpoint']) : '';
            $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : 'GET';
            $data = isset($_POST['data']) ? $this->sanitize_data($_POST['data']) : [];
            
            $response = $this->make_api_call($endpoint, $method, $data);
            
            wp_send_json_success($response);
        } catch (Exception $e) {
            error_log("RideCR API Error - " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function make_api_call($endpoint, $method, $data) {
        $url = '';
        $headers = [];
        $body = null;
        
        switch($endpoint) {
            case 'login':
                $url = 'https://partner.ridecr.com/api/login';
                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ];
                $body = $this->credentials;
                break;
                
            case 'home':
                if (empty($data['token'])) {
                    throw new Exception('Token is required');
                }
                $url = 'https://partner.ridecr.com/api/home';
                $headers = [
                    'Authorization' => 'Bearer ' . sanitize_text_field($data['token']),
                    'Accept' => 'application/json'
                ];
                break;
                
            case 'search':
                if (empty($data['token'])) {
                    throw new Exception('Token is required');
                }
                
                // Validar datos requeridos
                $required_fields = ['departure_id', 'arrival_id', 'date', 'adults'];
                foreach ($required_fields as $field) {
                    if (empty($data[$field])) {
                        throw new Exception("Field {$field} is required");
                    }
                }
                
                $url = 'https://partner.ridecr.com/api/service/search';
                $headers = [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . sanitize_text_field($data['token']),
                    'Accept' => 'application/json'
                ];
                
                // Preparar datos de búsqueda con validación
                $body = [
                    'departure' => (int)$data['departure_id'],
                    'arrival' => (int)$data['arrival_id'],
                    'date' => sanitize_text_field($data['date']),
                    'adults' => max(1, (int)$data['adults']),
                    'children' => max(0, (int)($data['children'] ?? 0)),
                    'infants' => max(0, (int)($data['infants'] ?? 0))
                ];
                
                // Validar que departure y arrival no sean iguales
                if ($body['departure'] === $body['arrival']) {
                    throw new Exception('Departure and arrival cannot be the same');
                }
                
                // Validar fecha
                $search_date = DateTime::createFromFormat('Y-m-d', $body['date']);
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                
                if (!$search_date || $search_date < $today) {
                    throw new Exception('Invalid or past date');
                }
                
                break;
                
            default:
                throw new Exception('Invalid endpoint: ' . $endpoint);
        }
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'body' => $body ? json_encode($body) : null,
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'RideCR-Plugin/1.6.2 WordPress/' . get_bloginfo('version')
        ];
        
        // Log de request para debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("RideCR API Request - Endpoint: {$endpoint}, Method: {$method}");
            if ($body) {
                error_log("Request Body: " . json_encode($body));
            }
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('Network error: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Log para depuración
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("RideCR API Response - Endpoint: {$endpoint}");
            error_log("Status Code: {$response_code}");
            error_log("Response Body: " . substr($response_body, 0, 500) . (strlen($response_body) > 500 ? '...' : ''));
        }

        // Verificar códigos de estado HTTP específicos
        switch ($response_code) {
            case 200:
            case 201:
                // OK
                break;
            case 401:
                throw new Exception('Unauthorized - Token may be expired');
            case 404:
                // Para búsquedas, 404 puede ser válido (ruta no encontrada)
                if ($endpoint === 'search') {
                    return [
                        'status' => 'no_routes',
                        'message' => 'No routes found for the selected criteria',
                        'status_code' => 404
                    ];
                }
                throw new Exception('API endpoint not found');
            case 422:
                throw new Exception('Invalid data provided');
            case 429:
                throw new Exception('Too many requests - please try again later');
            case 500:
            case 502:
            case 503:
                throw new Exception('Service temporarily unavailable');
            default:
                throw new Exception("API returned status code: {$response_code}");
        }

        // Verificar si la respuesta está vacía
        if (empty($response_body)) {
            throw new Exception('Empty response from API');
        }

        $decoded = json_decode($response_body, true);
        
        // Si falla el decode JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            error_log('Raw response: ' . $response_body);
            
            // Para debugging, devolver respuesta cruda
            return [
                'raw_response' => $response_body,
                'status_code' => $response_code,
                'headers' => $response_headers->getAll(),
                'json_error' => json_last_error_msg()
            ];
        }
        
        // Manejo específico por endpoint
        switch ($endpoint) {
            case 'login':
                if (!isset($decoded['get_data_token']) || !isset($decoded['post_data_token'])) {
                    throw new Exception('Invalid login response - missing tokens');
                }
                break;
                
            case 'home':
                if (!isset($decoded['departures']) || !isset($decoded['arrivals'])) {
                    throw new Exception('Invalid home response - missing location data');
                }
                break;
                
            case 'search':
                // Para búsquedas, verificar diferentes tipos de respuesta válida
                if (isset($decoded['slug'])) {
                    // Ruta encontrada
                    return $decoded;
                } elseif (isset($decoded['message']) || isset($decoded['error'])) {
                    // Error explícito de la API
                    return [
                        'status' => 'no_routes',
                        'message' => $decoded['message'] ?? $decoded['error'] ?? 'Route not available',
                        'original_response' => $decoded
                    ];
                } elseif (empty($decoded) || (isset($decoded['data']) && empty($decoded['data']))) {
                    // Respuesta vacía o sin datos
                    return [
                        'status' => 'no_routes',
                        'message' => 'No routes available for the selected criteria'
                    ];
                }
                break;
        }
        
        return $decoded;
    }
    
    private function sanitize_data($data) {
        if (!is_array($data)) {
            return sanitize_text_field($data);
        }
        
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = is_array($value) 
                ? $this->sanitize_data($value) 
                : sanitize_text_field($value);
        }
        
        return $sanitized;
    }
}
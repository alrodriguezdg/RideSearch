<?php
/*
Plugin Name: RideCR Booking Search
Description: Plugin para integrar el buscador de RideCR
Version: 1.6.3
Author: Smarteam
*/

defined('ABSPATH') or die('Acceso directo no permitido');

// Definir constantes
define('RIDECR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RIDECR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluir archivos
require_once RIDECR_PLUGIN_DIR . 'includes/api-endpoints.php';

class RideCR_Booking_Search {
public function __construct() {
    // Registrar shortcode
    add_shortcode('RideSearch', [$this, 'render_search_form']);
    
    // Cargar assets
    add_action('wp_enqueue_scripts', [$this, 'load_assets']);
    
    // Inicializar endpoints API
    new RideCR_API_Endpoints();
}

public function load_assets() {
    // CSS de Select2
    wp_enqueue_style(
        'select2-css',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0-rc.0'
    );
    
    // CSS propio
    wp_enqueue_style(
        'ridecr-booking-style',
        RIDECR_PLUGIN_URL . 'assets/css/style.css',
        ['select2-css'],
        filemtime(RIDECR_PLUGIN_DIR . 'assets/css/style.css')
    );
    
    // JS de Select2
    wp_enqueue_script(
        'select2-js',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0-rc.0',
        true
    );
    
    // JS propio (depende de Select2)
    wp_enqueue_script(
        'ridecr-booking-script',
        RIDECR_PLUGIN_URL . 'assets/js/script.js',
        ['jquery', 'select2-js'],
        filemtime(RIDECR_PLUGIN_DIR . 'assets/js/script.js'),
        true
    );
    
    // Localizar script con variables PHP
    wp_localize_script('ridecr-booking-script', 'ridecr_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ridecr_nonce')
    ]);
}

public function render_search_form() {
    ob_start();
    ?>
    <form id="ride-form" class="booking-form">
        <div class="form-group w-20">
            <label for="departures">Departure</label>
            <select id="departures">
                <option value="">Select...</option>
            </select>
        </div>
        
        <div class="form-group w-20">
            <label for="arrivals">Arrival</label>
            <select id="arrivals">
                <option value="">Select...</option>
            </select>
        </div>
        
        <div class="form-group w-20">
            <label for="date">Date</label>
            <input type="date" id="date" placeholder="DD/MM/YYYY">
        </div>
        
        <div class="form-group">
            <label for="adults">Adults</label>
            <input type="number" id="adults" min="1" value="1">
        </div>
        
        <div class="form-group">
            <label for="children">Children</label>
            <input type="number" id="children" min="0" value="0">
        </div>
        
        <div class="form-group">
            <label for="infants">Infants</label>
            <input type="number" id="infants" min="0" value="0">
        </div>
        
        <button type="submit" class="book-now-btn w-20">
            BOOK NOW <span class="arrow">→</span>
        </button>
    </form>

    <!-- Modal para error -->
    <div class="modal-booking-container">
    <div class="modal-overlay" id="modal-overlay"></div>
        <div id="modal">
            <button onclick="closeModal()" class="modal-close-btn">×</button>
            <p id="modal-message"></p>
            <a id="whatsapp-link" href="#" target="_blank" rel="noopener noreferrer" class="whatsapp-btn">
                Contact us by WhatsApp
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
}

new RideCR_Booking_Search();
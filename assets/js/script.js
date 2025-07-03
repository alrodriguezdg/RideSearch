jQuery(document).ready(function($) {

    // Inicializar Select2 para los selects
    function initSelect2() {
        $('#departures, #arrivals').select2({
            placeholder: "Select or type to search...",
            allowClear: true,
            width: 'resolve',
            minimumResultsForSearch: 3,
            language: {
                noResults: function() {
                    return "No results found";
                }
            }
        });
    }
    
    // Variables globales
    let tokens = {
        get_data: null,
        post_data: null
    };
    
    const destinationsMap = {
        departures: {},
        arrivals: {}
    };
    
    let isSearching = false; // Flag para prevenir búsquedas simultáneas
    
    // ================= FUNCIONES AUXILIARES ================= //
    
    function showModal(message) {
        // Configurar mensaje
        $('#modal-message').text(message);
        
        // Configurar enlace de WhatsApp con datos actuales
        const departure = $('#departures option:selected').text();
        const arrival = $('#arrivals option:selected').text();
        const date = $('#date').val();
        const adults = $('#adults').val() || 1;
        const children = $('#children').val() || 0;
        const infants = $('#infants').val() || 0;
        
        const whatsappMsg = `Hola, necesito ayuda con esta ruta:
        
    Origen: ${departure}
    Destino: ${arrival}
    Fecha: ${date}
    Pasajeros: ${adults} adulto(s), ${children} niño(s), ${infants} infante(s)`;
    
        $('#whatsapp-link').attr('href', `https://wa.me/+50687270999?text=${encodeURIComponent(whatsappMsg)}`);
        
        // Mostrar modal
        $('#modal, #modal-overlay').show();
        
        // Agregar indicador visual de que se recargará
        console.log('Modal shown - page will reload when closed');
    }
    
    function closeModal() {
        $('#modal, #modal-overlay').hide();
        
        // Recargar página y renovar tokens cuando se cierra el modal
        console.log('Modal closed - reloading page and refreshing tokens...');
        location.reload();
    }
    
    function resetFormState() {
        // Limpiar estado de carga
        $('#ride-form').removeClass('loading');
        isSearching = false;
        
        console.log('Form state reset - will reload page if modal was shown');
    }
    
    function populateSelect(id, items) {
        const $select = $(`#${id}`);
        $select.empty().append('<option value=""></option>'); // Select2 necesita option vacío para placeholder
        
        // Limpiar el mapa de destinos para este select
        destinationsMap[id] = {};
        
        items.forEach(item => {
            destinationsMap[id][item.destination_id] = item.name;
            
            $select.append($('<option>', {
                value: item.destination_id,
                'data-name': item.name,
                text: item.name
            }));
        });
        
        // Si ya está inicializado Select2, actualizarlo
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.trigger('change.select2');
        }
    }
    
    // ================= MANEJO DE API ================= //
    
    async function ridecrAPIRequest(endpoint, method = 'GET', data = {}) {
        try {
            const response = await $.ajax({
                url: ridecr_vars.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ridecr_api_request',
                    endpoint: endpoint,
                    method: method,
                    data: data,
                    nonce: ridecr_vars.nonce
                },
                timeout: 15000 // Aumentar timeout a 15 segundos
            });
            
            if (!response) throw new Error('Empty server response');
            if (!response.success) throw new Error(response.data || 'API error');
            
            return response.data;
            
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            
            // Si es un error de token, intentar renovar
            if (error.responseText && error.responseText.includes('401')) {
                console.log('Token expired, attempting to refresh...');
                throw new Error('TOKEN_EXPIRED');
            }
            
            throw error;
        }
    }
    
    // ================= FUNCIONES PRINCIPALES ================= //
    
    async function loginAndFetchToken() {
        try {
            console.log('Logging in and fetching tokens...');
            const data = await ridecrAPIRequest('login', 'POST');
            
            if (data?.get_data_token && data?.post_data_token) {
                tokens.get_data = data.get_data_token;
                tokens.post_data = data.post_data_token;
                console.log('Tokens received successfully');
                
                await fetchHomeData();
                
                // Solo inicializar Select2 si no está ya inicializado
                if (!$('#departures').hasClass('select2-hidden-accessible')) {
                    initSelect2();
                }
            } else {
                throw new Error('Invalid token response');
            }
        } catch (error) {
            console.error('Login failed:', error);
            showModal('Connection error. The page will reload when you close this message.');
            // La página se recargará automáticamente cuando se cierre el modal
        }
    }
    
    async function fetchHomeData() {
        try {
            console.log('Fetching home data...');
            const data = await ridecrAPIRequest('home', 'GET', {
                token: tokens.get_data
            });
            
            if (data?.departures && data?.arrivals) {
                populateSelect('departures', data.departures);
                populateSelect('arrivals', data.arrivals);
                console.log('Locations loaded successfully');
            } else {
                throw new Error('Invalid locations data');
            }
        } catch (error) {
            console.error('Failed to load locations:', error);
            
            if (error.message === 'TOKEN_EXPIRED') {
                // Intentar renovar token
                await loginAndFetchToken();
            } else {
                showModal('Error loading destinations. The page will reload when you close this message.');
            }
        }
    }
    
    async function searchRoute(event) {
        if (event) event.preventDefault();
        
        // Prevenir búsquedas simultáneas
        if (isSearching) {
            console.log('Search already in progress');
            return;
        }
        
        // Resetear estado previo
        $('#modal, #modal-overlay').hide();
        resetFormState();
        
        // Obtener datos del formulario
        const departureId = $('#departures').val();
        const arrivalId = $('#arrivals').val();
        const date = $('#date').val();
        
        // Validación básica
        if (!departureId || !arrivalId) {
            return showModal('Please select origin and destination');
        }
        
        if (!arrivalId === departureId) {
            return showModal('Origin and destination cannot be the same');
        }
        
        if (!date) {
            return showModal('Please select a date');
        }
        
        // Validar que la fecha no sea en el pasado
        const selectedDate = new Date(date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            return showModal('Please select a future date');
        }
    
        try {
            // Marcar que estamos buscando
            isSearching = true;
            $('#ride-form').addClass('loading');
            
            console.log('Starting search...', {
                departureId,
                arrivalId,
                date
            });
            
            const searchData = {
                token: tokens.post_data,
                departure_id: parseInt(departureId),
                arrival_id: parseInt(arrivalId),
                date: date,
                adults: parseInt($('#adults').val()) || 1,
                children: parseInt($('#children').val()) || 0,
                infants: parseInt($('#infants').val()) || 0
            };
            
            const data = await ridecrAPIRequest('search', 'POST', searchData);
            
            console.log('Search response:', data);
            
            // Verificar diferentes tipos de respuesta exitosa
            if (data?.slug) {
                // Ruta encontrada con slug
                const params = new URLSearchParams({
                    adults: searchData.adults,
                    infants: searchData.infants,
                    children: searchData.children,
                    departure: encodeURIComponent($('#departures option:selected').text()),
                    arrival: encodeURIComponent($('#arrivals option:selected').text()),
                    date: encodeURIComponent(date)
                });
                
                console.log('Redirecting to booking page...');
                window.location.href = `https://booking.ridecr.com/transport-route/${data.slug}?${params.toString()}`;
                
            } else if (data?.message || data?.status === 'no_routes') {
                // Respuesta válida pero sin rutas disponibles
                console.log('No routes available for this search');
                showModal('For the selected route, please contact us via WhatsApp.');
                
            } else if (data?.success === false || data?.error) {
                // Error explícito de la API
                console.warn('API returned error:', data);
                showModal('Route not available. Please contact us via WhatsApp.');
                
            } else {
                // Respuesta inesperada
                console.warn('Unexpected response format:', data);
                showModal('For the selected route, please contact us via WhatsApp.');
            }
            
        } catch (error) {
            console.error('Search failed:', error);
            
            if (error.message === 'TOKEN_EXPIRED') {
                // Intentar renovar token y reintentar
                try {
                    await loginAndFetchToken();
                    console.log('Token refreshed, retrying search...');
                    // Reintentar la búsqueda una vez
                    setTimeout(() => {
                        isSearching = false;
                        searchRoute();
                    }, 1000);
                    return;
                } catch (refreshError) {
                    console.error('Failed to refresh token:', refreshError);
                }
            }
            
            // Para cualquier otro error, mostrar modal que recargará al cerrarse
            showModal('Search temporarily unavailable. Please contact us via WhatsApp.');
            
        } finally {
            // Siempre limpiar el estado de búsqueda
            isSearching = false;
            $('#ride-form').removeClass('loading');
        }
    }
    
    // ================= INICIALIZACIÓN ================= //
    
    // Eventos
    $('#ride-form').on('submit', searchRoute);
    $('.modal-close-btn, #modal-overlay').on('click', closeModal);
    
    // WhatsApp - Recargar después de hacer clic
    $('#whatsapp-link').on('click', function(e) {
        e.stopPropagation();
        // Recargar la página después de un pequeño delay para que se abra WhatsApp
        setTimeout(() => {
            location.reload();
        }, 1000);
    });
    
    // Manejo especial del campo fecha
    $('#date').on('focus', function() {
        this.type = 'date';
    }).on('blur', function() {
        if (!this.value) this.type = 'text';
    });
    
    // Botón manual para refrescar datos (opcional)
    function addRefreshButton() {
        if ($('#refresh-data-btn').length === 0) {
            $('#ride-form').after(`
                <button id="refresh-data-btn" type="button" style="margin-top: 10px; padding: 8px 16px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Refresh Destinations
                </button>
            `);
            
            $('#refresh-data-btn').on('click', async function() {
                $(this).prop('disabled', true).text('Refreshing...');
                try {
                    await loginAndFetchToken();
                    $(this).text('Refreshed!');
                    setTimeout(() => {
                        $(this).prop('disabled', false).text('Refresh Destinations');
                    }, 2000);
                } catch (error) {
                    $(this).text('Error - Try again');
                    setTimeout(() => {
                        $(this).prop('disabled', false).text('Refresh Destinations');
                    }, 2000);
                }
            });
        }
    }
    
    // Iniciar
    loginAndFetchToken().then(() => {
        console.log('Plugin initialized successfully');
        // Agregar botón de refresh en desarrollo
        if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
            addRefreshButton();
        }
    });
    
    // Renovar token cada 8 minutos (más frecuente para evitar expiraciones)
    setInterval(() => {
        console.log('Auto-refreshing tokens...');
        loginAndFetchToken();
    }, 8 * 60 * 1000);
    
    // Exponer función global para debugging
    window.rideCRDebug = {
        tokens: tokens,
        destinations: destinationsMap,
        refreshTokens: loginAndFetchToken,
        testSearch: searchRoute
    };
});
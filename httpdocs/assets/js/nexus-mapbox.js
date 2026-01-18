document.addEventListener('DOMContentLoaded', () => {
    // Check if page actually needs Mapbox GEOCODER before doing anything
    // Only check for geocoder-specific elements, not generic #map containers
    function pageNeedsMapboxGeocoder() {
        const hasInputs = document.querySelectorAll('.mapbox-location-input-v2').length > 0;
        const hasDirectContainer = document.getElementById('nexus-mapbox-direct-container') !== null;
        return hasInputs || hasDirectContainer;
    }

    // Early exit if page doesn't need Mapbox geocoder - no polling, no timeout warnings
    if (!pageNeedsMapboxGeocoder()) {
        return;
    }

    // Initialize Mapbox geocoder functionality
    function initMapboxGeocoder() {
        // Check if both mapboxgl and MapboxGeocoder are loaded
        if (typeof mapboxgl === 'undefined' || typeof MapboxGeocoder === 'undefined') {
            return false;
        }

        const token = window.NEXUS_MAPBOX_TOKEN ? window.NEXUS_MAPBOX_TOKEN.trim() : '';

        console.log('Project NEXUS Mapbox: Initializing...');
        if (!token) {
            console.error('Project NEXUS: Mapbox Token is MISSING.');
            return true; // Return true to stop retrying - token issue, not loading issue
        }
        console.log('Project NEXUS Mapbox: Token Found. Length: ' + token.length);

        mapboxgl.accessToken = token;

        initGeocoderInputs();
        initDirectContainer();

        return true;
    }

    function initGeocoderInputs() {
        // Find all inputs that want geolocation
        const inputs = document.querySelectorAll('.mapbox-location-input-v2');
        console.log(`Project NEXUS Mapbox: Found ${inputs.length} inputs.`);

        inputs.forEach(input => {
            // Skip if already initialized
            if (input.dataset.mapboxInitialized) return;
            input.dataset.mapboxInitialized = 'true';

            try {
                // Create container for geocoder
                const container = document.createElement('div');
                container.className = 'mapbox-geocoder-container';
                input.parentNode.insertBefore(container, input);

                // Hide original input (we will sync value to it)
                input.style.display = 'none';
                input.type = 'hidden'; // Ensure it is hidden even if CSS overrides display

                const geocoder = new MapboxGeocoder({
                    accessToken: mapboxgl.accessToken,
                    types: 'place,locality,neighborhood',
                    placeholder: input.placeholder || 'Search for a location...',
                    mapboxgl: mapboxgl,
                    marker: false,
                    proximity: 'ip', // Bias results to user location
                    language: 'en'
                });

                // Add geocoder to container
                container.appendChild(geocoder.onAdd());

                // Pre-fill if value exists
                if (input.value) {
                    geocoder.setInput(input.value);
                }

                // Sync selection to hidden input
                geocoder.on('result', (e) => {
                    if (e.result && e.result.place_name) {
                        input.value = e.result.place_name;

                        // Coordinates [lon, lat]
                        if (e.result.center) {
                            const lon = e.result.center[0];
                            const lat = e.result.center[1];

                            // Find closest (or specifically named) hidden inputs relative to this container/form
                            const form = input.closest('form');
                            if (form) {
                                const latInput = form.querySelector('input[name="latitude"]');
                                const lonInput = form.querySelector('input[name="longitude"]');
                                if (latInput) latInput.value = lat;
                                if (lonInput) lonInput.value = lon;
                            }
                        }
                    }
                });

                // Handle clear
                geocoder.on('clear', () => {
                    input.value = '';
                    const form = input.closest('form');
                    if (form) {
                        const latInput = form.querySelector('input[name="latitude"]');
                        const lonInput = form.querySelector('input[name="longitude"]');
                        if (latInput) latInput.value = '';
                        if (lonInput) lonInput.value = '';
                    }
                });
            } catch (err) {
                console.error('Project NEXUS Mapbox Error:', err);
                // Fallback: Show original input if Mapbox crashes
                input.style.display = 'block';
                input.style.opacity = '1';
            }
        });
    }

    // ---------------------------------------------------------
    // REDESIGNED LOGIC (v3): Direct Container Injection
    // No "hiding" inputs. We render into a clean <div>.
    // ---------------------------------------------------------
    function initDirectContainer() {
        const directContainer = document.getElementById('nexus-mapbox-direct-container');
        if (directContainer && !directContainer.dataset.mapboxInitialized) {
            directContainer.dataset.mapboxInitialized = 'true';
            console.log('Project NEXUS Mapbox: Direct Container Found. Initializing v3 Logic.');
            try {
                const geocoder = new MapboxGeocoder({
                    accessToken: mapboxgl.accessToken,
                    types: 'place,locality,neighborhood',
                    placeholder: directContainer.dataset.placeholder || 'Search for a location...',
                    mapboxgl: mapboxgl,
                    marker: false,
                    proximity: 'ip',
                    language: 'en'
                });

                // Clear any existing content
                directContainer.innerHTML = '';
                directContainer.appendChild(geocoder.onAdd());

                // Pre-fill (SILENTLY - Do not trigger search)
                const locationValEl = document.getElementById('location-value');
                const locationVal = locationValEl ? locationValEl.value : '';
                if (locationVal && locationVal !== 'Remote') {
                    // Manual DOM injection to avoid opening the menu
                    const wrapper = directContainer.querySelector('.mapboxgl-ctrl-geocoder--input');
                    if (wrapper) wrapper.value = locationVal;
                    // Fallback if class differs
                    const fallbackInput = directContainer.querySelector('input');
                    if (fallbackInput) fallbackInput.value = locationVal;
                }

                // Events
                geocoder.on('result', (e) => {
                    if (e.result) {
                        const locVal = document.getElementById('location-value');
                        const latEl = document.getElementById('latitude');
                        const lonEl = document.getElementById('longitude');
                        if (locVal) locVal.value = e.result.place_name;
                        if (e.result.center) {
                            if (latEl) latEl.value = e.result.center[1];
                            if (lonEl) lonEl.value = e.result.center[0];
                        }
                    }
                });

                geocoder.on('clear', () => {
                    const locVal = document.getElementById('location-value');
                    const latEl = document.getElementById('latitude');
                    const lonEl = document.getElementById('longitude');
                    if (locVal) locVal.value = '';
                    if (latEl) latEl.value = '';
                    if (lonEl) lonEl.value = '';
                });

                // Styling adjustments for the injected geocoder
                const geocoderCtrl = directContainer.querySelector('.mapboxgl-ctrl-geocoder');
                if (geocoderCtrl) {
                    geocoderCtrl.style.boxShadow = 'none';
                    geocoderCtrl.style.background = 'transparent';
                    geocoderCtrl.style.width = '100%';
                    geocoderCtrl.style.maxWidth = '100%';
                }

                const input = directContainer.querySelector('input');
                if (input) {
                    input.style.boxShadow = 'none';
                    input.style.background = 'transparent';
                    input.style.width = '100%';
                    input.style.height = '100%';
                    input.style.padding = '15px';
                }

            } catch (err) {
                console.error('Project NEXUS Mapbox v3 Error:', err);
                directContainer.innerHTML = '<input type="text" name="location_fallback" placeholder="Error loading map. Type location manually." style="width:100%; padding:15px; border-radius:12px; border:1px solid #ccc;">';
            }
        }
    }

    // Try to initialize immediately, or wait for Mapbox to load
    if (!initMapboxGeocoder()) {
        // Mapbox not loaded yet - wait for it (lazy-loaded scripts)
        let attempts = 0;
        const maxAttempts = 50; // 5 seconds max wait
        const checkInterval = setInterval(() => {
            attempts++;
            if (initMapboxGeocoder() || attempts >= maxAttempts) {
                clearInterval(checkInterval);
                if (attempts >= maxAttempts) {
                    console.warn('Project NEXUS Mapbox: Timed out waiting for Mapbox to load');
                }
            }
        }, 100);
    }
});

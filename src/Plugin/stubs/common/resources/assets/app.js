import './app.css';

/**
 * Main JavaScript file for %plugin_name% plugin.
 * 
 * This file is the entry point for the plugin's frontend JavaScript.
 * It includes the main CSS file and sets up any necessary JavaScript functionality.
 */

class %plugin_namespace%Plugin {
    constructor() {
        this.init();
    }

    /**
     * Initialize the plugin
     */
    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.onDOMReady());
        } else {
            this.onDOMReady();
        }
    }

    /**
     * Called when DOM is ready
     */
    onDOMReady() {
        console.log('%plugin_name% plugin loaded');
        
        // Add your initialization code here
        this.setupPlugin();
    }

    /**
     * Setup plugin functionality
     */
    setupPlugin() {
        // Add your plugin-specific initialization code here
        // Examples:
        // - Initialize event listeners
        // - Setup components
        // - Configure AJAX endpoints
        // - Initialize third-party libraries
    }

    /**
     * Utility method for AJAX requests
     * 
     * @param {string} action WordPress AJAX action name
     * @param {FormData|Object} data Data to send
     * @returns {Promise} Promise resolving to response data
     */
    async ajaxRequest(action, data = {}) {
        const formData = data instanceof FormData ? data : new FormData();
        
        if (!(data instanceof FormData)) {
            Object.entries(data).forEach(([key, value]) => {
                formData.append(key, value);
            });
        }

        // Add WordPress AJAX parameters
        formData.append('action', action);
        formData.append('nonce', window.%plugin_function_name%_ajax?.nonce || '');

        try {
            const response = await fetch(window.%plugin_function_name%_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            });

            return await response.json();
        } catch (error) {
            console.error('AJAX request failed:', error);
            throw error;
        }
    }
}

// Initialize the plugin
new %plugin_namespace%Plugin();
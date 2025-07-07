/**
 * Admin JavaScript file for %plugin_name% plugin.
 * 
 * This file handles admin-specific functionality.
 */

class %plugin_namespace%Admin {
    constructor() {
        this.init();
    }

    /**
     * Initialize admin functionality
     */
    init() {
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
        console.log('%plugin_name% admin script loaded');
        
        // Add your admin initialization code here
        this.setupAdmin();
    }

    /**
     * Setup admin functionality
     */
    setupAdmin() {
        // Add your admin-specific initialization code here
        // Examples:
        // - Initialize settings forms
        // - Setup admin event listeners
        // - Configure admin components
        // - Initialize admin-specific libraries
    }

    /**
     * Utility method for admin AJAX requests
     * 
     * @param {string} action WordPress AJAX action name
     * @param {FormData|Object} data Data to send
     * @returns {Promise} Promise resolving to response data
     */
    async adminAjaxRequest(action, data = {}) {
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
            console.error('Admin AJAX request failed:', error);
            throw error;
        }
    }

    /**
     * Show admin notice
     * 
     * @param {string} message Notice message
     * @param {string} type Notice type (success, error, warning, info)
     */
    showAdminNotice(message, type = 'info') {
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `<p>${message}</p>`;
        
        // Insert at the top of the admin content
        const adminContent = document.querySelector('.wrap') || document.querySelector('#wpbody-content');
        if (adminContent) {
            adminContent.insertBefore(notice, adminContent.firstChild);
        }
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notice.remove();
        }, 5000);
    }
}

// Initialize admin functionality
new %plugin_namespace%Admin();
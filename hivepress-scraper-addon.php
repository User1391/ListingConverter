<?php
/**
 * Plugin Name: HivePress Listing Scraper
 * Description: Adds FB and Sailing Forums listing scraping functionality to HivePress
 * Version: 1.0
 * Author: Max Penders
 */

// Add scraper section to the listing form
add_filter('hivepress/v1/forms/listing_submit', function($form) {
    $form['fields'] = array_merge(
        [
            'scraper_section' => [
                'type' => 'content',
                '_order' => 0,
                'content' => '
                    <div class="hp-form__field">
                        <label class="hp-field__label">Import Listing</label>
                        <input type="text" id="listing-url" class="hp-field hp-field--text" placeholder="Enter Facebook or SailingForums URL">
                        <button id="scrape-button" class="hp-button hp-button--secondary" style="margin-top: 10px; margin-bottom: 20px;">Import Data</button>
                        <div id="scraper-status" class="hp-form__messages"></div>
                    </div>
                ',
            ],
        ],
        $form['fields']
    );
    
    return $form;
});

// Add the JavaScript for the scraper functionality
add_action('wp_footer', function() {
    if (!is_page('submit-listing')) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        const DEBUG = true;
        
        function debug(message, data = null) {
            if (!DEBUG) return;
            if (data) {
                console.log(`Scraper Debug: ${message}`, data);
            } else {
                console.log(`Scraper Debug: ${message}`);
            }
        }

        function initScraper() {
            const scrapeButton = $('#scrape-button');
            const urlInput = $('#listing-url');
            const status = $('#scraper-status');
            
            debug('Initializing scraper...');
            
            if (!scrapeButton.length || !urlInput.length || !status.length) {
                debug('Error: Required elements not found');
                return;
            }
            
            scrapeButton.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const url = urlInput.val().trim();
                debug('Scrape button clicked with URL:', url);
                
                if (!url) {
                    debug('Error: Empty URL provided');
                    status.html('Please enter a URL')
                          .removeClass('hp-form__message--success')
                          .addClass('hp-form__message--error');
                    return;
                }
                
                status.html('Importing data...')
                      .removeClass('hp-form__message--error hp-form__message--success');
                
                debug('Sending AJAX request...');
                
                // Updated AJAX call to use WordPress admin-ajax.php
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'scrape_listing',
                        url: url
                    },
                    success: function(response) {
                        debug('Received response:', response);
                        
                        if (response.success && response.data) {
                            debug('Attempting to update form fields');
                            
                            // Try different possible field name formats
                            const titleField = $('input[name="listing[title]"], input[name="title"], #title');
                            const descField = $('textarea[name="listing[description]"], textarea[name="description"], #description');
                            const priceField = $('input[name="listing[price]"], input[name="price"], #price');
                            const locField = $('input[name="listing[location]"], input[name="location"], #location');
                            
                            debug('Found fields:', {
                                title: titleField.length,
                                description: descField.length,
                                price: priceField.length,
                                location: locField.length
                            });
                            
                            if (titleField.length) titleField.val(response.data.title || '');
                            if (descField.length) descField.val(response.data.description || '');
                            
                            if (response.data.price && priceField.length) {
                                const price = response.data.price.replace(/[^0-9.]/g, '');
                                priceField.val(price);
                            }
                            
                            if (locField.length) locField.val(response.data.location || '');
                            
                            debug('Form fields updated');
                            
                            status.html('Data imported successfully!')
                                  .removeClass('hp-form__message--error')
                                  .addClass('hp-form__message--success');
                        } else {
                            debug('Error: Invalid response format', response);
                            status.html(response.data?.message || 'Error: Failed to import data')
                                  .removeClass('hp-form__message--success')
                                  .addClass('hp-form__message--error');
                        }
                    },
                    error: function(xhr, status, error) {
                        debug('AJAX error:', {
                            error: error,
                            status: status,
                            response: xhr.responseText
                        });
                        
                        let errorMessage = 'Error importing data';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data?.message) {
                                errorMessage += ': ' + response.data.message;
                            }
                        } catch (e) {
                            errorMessage += ': ' + error;
                        }
                        
                        status.html(errorMessage)
                              .removeClass('hp-form__message--success')
                              .addClass('hp-form__message--error');
                    }
                });
            });
        }

        initScraper();
    });
    </script>
    <?php
}, 100);

// Add AJAX handlers
add_action('wp_ajax_scrape_listing', 'handle_scrape_request');
add_action('wp_ajax_nopriv_scrape_listing', 'handle_scrape_request');

function handle_scrape_request() {
    try {
        $url = $_POST['url'] ?? '';
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL is required']);
        }

        $data = scrape_listing_data($url);
        wp_send_json_success($data);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// Register REST API endpoint
add_action('rest_api_init', function() {
    register_rest_route('hivepress-scraper/v1', '/scrape', [
        'methods' => 'POST',
        'callback' => function($request) {
            $url = $request->get_param('url');
            
            // Add debug logging
            error_log('Scraper API called with URL: ' . $url);
            
            if (empty($url)) {
                return new WP_Error('missing_url', 'URL parameter is required', ['status' => 400]);
            }
            
            try {
                // Your existing scraping logic here
                $data = scrape_listing_data($url);
                return ['success' => true, 'data' => $data];
            } catch (Exception $e) {
                return new WP_Error('scraping_failed', $e->getMessage(), ['status' => 500]);
            }
        },
        'permission_callback' => function() {
            return true; // Adjust permissions as needed
        }
    ]);
});

// Add this function if you don't already have it
function scrape_listing_data($url) {
    // Call the Python API
    $response = wp_remote_post('https://boatersmkt.com:5000/scrape', [
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'url' => $url
        ]),
        'timeout' => 30,
        'sslverify' => false // Only if needed for development
    ]);

    // Log the response for debugging
    error_log('Python API Response: ' . print_r($response, true));

    if (is_wp_error($response)) {
        throw new Exception('API request failed: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!$data || !isset($data['success'])) {
        throw new Exception('Invalid response from API');
    }

    if (!$data['success']) {
        throw new Exception($data['error'] ?? 'Unknown error from API');
    }

    return $data['data'];
}
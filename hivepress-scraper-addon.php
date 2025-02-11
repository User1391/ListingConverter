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
            
            // Log all input fields and their names
            $('input, textarea, select').each(function() {
                debug('Found form field:', {
                    name: $(this).attr('name'),
                    id: $(this).attr('id'),
                    type: $(this).prop('tagName'),
                    value: $(this).val()
                });
            });
            
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
                
                $.ajax({
                    url: 'https://boatersmkt.com/scrape',
                    method: 'POST',
                    data: JSON.stringify({ url: url }),
                    contentType: 'application/json',
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
                            
                            titleField.val(response.data.title || '');
                            descField.val(response.data.description || '');
                            
                            if (response.data.price) {
                                const price = response.data.price.replace(/[^0-9.]/g, '');
                                priceField.val(price);
                            }
                            
                            locField.val(response.data.location || '');
                            
                            debug('Form fields updated');
                            
                            status.html('Data imported successfully!')
                                  .removeClass('hp-form__message--error')
                                  .addClass('hp-form__message--success');
                        } else {
                            debug('Error: Invalid response format', response);
                            status.html('Error: Failed to import data')
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
                        
                        status.html('Error importing data: ' + error)
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
    // Your existing scraping logic
    // For testing, return dummy data
    return [
        'title' => 'Test Boat',
        'price' => '100000',
        'description' => 'Test description'
    ];
}
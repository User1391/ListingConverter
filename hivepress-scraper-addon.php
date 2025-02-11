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
                debug('Error: Required elements not found', {
                    scrapeButton: scrapeButton.length,
                    urlInput: urlInput.length,
                    status: status.length
                });
                return;
            }
            
            debug('Scraper elements found and initialized');
            
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
                    url: '/wp-json/hivepress-scraper/v1/scrape',
                    method: 'POST',
                    data: JSON.stringify({ url: url }),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    },
                    success: function(response) {
                        debug('Received response:', response);
                        
                        if (response.success && response.data) {
                            debug('Updating form fields with data');
                            
                            // Update title
                            $('input[name="listing_title"]').val(response.data.title || '');
                            
                            // Update description - handle both textarea and TinyMCE if present
                            const description = response.data.description || '';
                            $('textarea[name="listing_description"]').val(description);
                            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('listing_description')) {
                                tinyMCE.get('listing_description').setContent(description);
                            }
                            
                            // Update price - remove currency symbols and non-numeric chars
                            if (response.data.price) {
                                const price = response.data.price.replace(/[^0-9.]/g, '');
                                $('input[name="listing_price"]').val(price);
                            }
                            
                            // Update location
                            $('input[name="listing_location"]').val(response.data.location || '');
                            
                            debug('Form fields updated successfully');
                            
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
                        
                        let errorMessage = 'Error importing data';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage += ': ' + response.message;
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
            
            debug('Scraper initialization complete');
        }

        initScraper();
    });
    </script>
    <?php
}, 100);
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
        // Initialize scraper functionality
        function initScraper() {
            const scrapeButton = $('#scrape-button');
            const urlInput = $('#listing-url');
            const status = $('#scraper-status');
            
            if (!scrapeButton.length || !urlInput.length || !status.length) {
                console.warn('Scraper elements not found');
                return;
            }
            
            scrapeButton.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const url = urlInput.val().trim();
                
                if (!url) {
                    status.html('Please enter a URL')
                          .removeClass('hp-form__message--success')
                          .addClass('hp-form__message--error');
                    return;
                }
                
                status.html('Importing data...')
                      .removeClass('hp-form__message--error hp-form__message--success');
                
                $.ajax({
                    url: 'https://boatersmkt.com/scrape',
                    method: 'POST',
                    data: JSON.stringify({ url: url }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success && response.data) {
                            // Update form fields
                            $('input[name="listing[title]"]').val(response.data.title || '');
                            $('textarea[name="listing[description]"]').val(response.data.description || '');
                            
                            if (response.data.price) {
                                $('input[name="listing[price]"]').val(
                                    response.data.price.replace(/[^0-9.]/g, '')
                                );
                            }
                            
                            $('input[name="listing[location]"]').val(response.data.location || '');
                            
                            // Show success message
                            status.html('Data imported successfully!')
                                  .removeClass('hp-form__message--error')
                                  .addClass('hp-form__message--success');
                        } else {
                            status.html('Error: Failed to import data')
                                  .removeClass('hp-form__message--success')
                                  .addClass('hp-form__message--error');
                        }
                    },
                    error: function(xhr, status, error) {
                        status.html('Error importing data: ' + error)
                              .removeClass('hp-form__message--success')
                              .addClass('hp-form__message--error');
                    }
                });
            });
        }

        // Initialize when document is ready
        initScraper();
    });
    </script>
    <?php
}, 100);

// Optional: Keep the hook logging for debugging
add_action('all', function($tag) {
    if (strpos($tag, 'hivepress') !== false) {
        error_log('HivePress Hook: ' . $tag);
    }
}); 
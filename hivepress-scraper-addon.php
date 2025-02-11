<?php
/**
 * Plugin Name: HivePress Listing Scraper
 * Description: Adds FB and Sailing Forums listing scraping functionality to HivePress
 * Version: 1.0
 * Author: Max Penders
 */

// Add scraper fields to the listing submission form
add_filter('hivepress/v1/forms/listing_submit', function($form) {
    $form['fields'] = array_merge(
        [
            'scraper_url' => [
                'label' => 'Import Listing',
                'type' => 'text',
                'display_type' => 'text',
                'placeholder' => 'Enter Facebook or SailingForums URL',
                '_order' => 1,
                'required' => false,
                'attributes' => [
                    'id' => 'listing-url',
                ],
            ],
            'scraper_button' => [
                'type' => 'button',
                'display_type' => 'button',
                'label' => 'Import Data',
                '_order' => 2,
                'attributes' => [
                    'id' => 'scrape-button',
                    'class' => ['hp-button', 'hp-button--secondary'],
                    'style' => 'margin-top: 10px; margin-bottom: 20px;',
                ],
            ],
            'scraper_status' => [
                'type' => 'content',
                '_order' => 3,
                'content' => '<div id="scraper-status" class="hp-form__messages"></div>',
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
                console.error('Scraper elements not found:', {
                    button: scrapeButton.length,
                    input: urlInput.length,
                    status: status.length
                });
                return;
            }
            
            console.log('Scraper initialized successfully');
            
            scrapeButton.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const url = urlInput.val().trim();
                console.log('Attempting to scrape URL:', url);
                
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
                        console.log('Scraper response:', response);
                        
                        if (response.success && response.data) {
                            // Log form field updates
                            console.log('Updating form fields with:', response.data);
                            
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
                            console.error('Invalid response format:', response);
                            status.html('Error: Failed to import data')
                                  .removeClass('hp-form__message--success')
                                  .addClass('hp-form__message--error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ajax error:', {
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

        // Initialize when document is ready
        initScraper();
    });
    </script>
    <?php
}, 100); 
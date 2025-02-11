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

    // Add the JavaScript for the scraper functionality
    add_action('wp_footer', function() {
        if (!is_page('submit-listing')) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Scraper script loaded'); // Debug log
            
            $(document).on('click', '#scrape-button', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Scrape button clicked'); // Debug log
                
                const url = $('#listing-url').val();
                const status = $('#scraper-status');
                
                if (!url) {
                    status.html('Please enter a URL').addClass('hp-form__message--error');
                    return;
                }
                
                status.html('Importing data...').removeClass('hp-form__message--error hp-form__message--success');
                
                $.ajax({
                    url: 'https://boatersmkt.com/scrape',
                    method: 'POST',
                    data: JSON.stringify({ url: url }),
                    contentType: 'application/json',
                    success: function(response) {
                        console.log('Received response:', response); // Debug log
                        
                        if (response.success && response.data) {
                            $('input[name="listing[title]"]').val(response.data.title || '');
                            $('textarea[name="listing[description]"]').val(response.data.description || '');
                            if (response.data.price) {
                                $('input[name="listing[price]"]').val(
                                    response.data.price.replace(/[^0-9.]/g, '')
                                );
                            }
                            $('input[name="listing[location]"]').val(response.data.location || '');
                            
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
                        console.error('Ajax error:', error); // Debug log
                        console.error('Response:', xhr.responseText); // Debug log
                        
                        status.html('Error importing data: ' + error)
                              .removeClass('hp-form__message--success')
                              .addClass('hp-form__message--error');
                    }
                });
            });
        });
        </script>
        <?php
    }, 100);

    return $form;
});

// Optional: Keep the hook logging for debugging
add_action('all', function($tag) {
    if (strpos($tag, 'hivepress') !== false) {
        error_log('HivePress Hook: ' . $tag);
    }
}); 
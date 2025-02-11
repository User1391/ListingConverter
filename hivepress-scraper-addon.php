<?php
/**
 * Plugin Name: HivePress Listing Scraper
 * Description: Adds FB and Sailing Forums listing scraping functionality to HivePress
 * Version: 1.0
 * Author: Max Penders
 */

// Add scraper fields to the listing submission form
add_filter('hivepress/v1/forms/submit_listing', function($form) {
    $form['fields'] = array_merge(
        [
            'scraper_url' => [
                'label' => 'Import Listing',
                'type' => 'text',
                'display_type' => 'text',
                'placeholder' => 'Enter Facebook or SailingForums URL',
                '_order' => 1,
                'attributes' => [
                    'id' => 'listing-url',
                ],
            ],
            'scraper_button' => [
                'type' => 'button',
                'display_type' => 'submit',
                'label' => 'Import Data',
                '_order' => 2,
                'attributes' => [
                    'id' => 'scrape-button',
                    'class' => ['hp-button', 'hp-button--primary'],
                    'style' => 'margin-top: 10px;',
                ],
            ],
            'scraper_status' => [
                'type' => 'content',
                '_order' => 3,
                'content' => '<div id="scraper-status"></div>',
            ],
        ],
        $form['fields']
    );

    // Add the JavaScript for the scraper functionality
    add_action('wp_footer', function() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#scrape-button').click(function(e) {
                e.preventDefault();
                const url = $('#listing-url').val();
                const status = $('#scraper-status');
                
                status.html('Importing data...');
                
                $.ajax({
                    url: 'https://boatersmkt.com/scrape',
                    method: 'POST',
                    data: JSON.stringify({ url: url }),
                    contentType: 'application/json',
                    success: function(data) {
                        // Populate HivePress fields
                        $('input[name="listing_title"]').val(data.title);
                        $('textarea[name="listing_description"]').val(data.description);
                        $('input[name="listing_price"]').val(data.price.replace('$', ''));
                        $('input[name="listing_location"]').val(data.location);
                        
                        status.html('Data imported successfully!');
                    },
                    error: function(xhr, status, error) {
                        status.html('Error importing data: ' + error);
                    }
                });
            });
        });
        </script>
        <?php
    });

    return $form;
});

// Optional: Keep the hook logging for debugging
add_action('all', function($tag) {
    if (strpos($tag, 'hivepress') !== false) {
        error_log('HivePress Hook: ' . $tag);
    }
}); 
<?php
/**
 * Plugin Name: HivePress Listing Scraper
 * Description: Adds FB and Sailing Forums listing scraping functionality to HivePress
 * Version: 1.0
 * Author: Max Penders
 */

// Debug: Log when plugin is loaded
error_log('HivePress Scraper: Plugin file loaded');

// Hook into WordPress init to ensure HivePress is loaded
add_action('init', function() {
    error_log('HivePress Scraper: Init hook triggered');
    
    // Check if HivePress exists
    if (!class_exists('HivePress')) {
        error_log('HivePress Scraper: HivePress class not found');
        return;
    }
    
    error_log('HivePress Scraper: HivePress class found');
});

// Add scraper fields to the listing submission form
add_action('wp_loaded', function() {
    error_log('HivePress Scraper: wp_loaded action triggered');
    
    add_filter('hivepress/v1/forms/submit_listing', function($form) {
        error_log('HivePress Scraper: Form filter triggered');
        
        if (!is_array($form) || !isset($form['fields'])) {
            error_log('HivePress Scraper: Invalid form structure');
            return $form;
        }
        
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

        // Debug log the form structure
        error_log('HivePress Scraper: Form structure modified');

        return $form;
    }, 20);  // Added priority to ensure it runs after HivePress
});

// Add the JavaScript in the footer
add_action('wp_footer', function() {
    error_log('HivePress Scraper: Adding footer script');
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('Scraper script loaded');
        if ($('#scrape-button').length) {
            console.log('Scrape button found');
        } else {
            console.log('Scrape button not found');
        }
        
        $('#scrape-button').click(function(e) {
            e.preventDefault();
            const url = $('#listing-url').val();
            const status = $('#scraper-status');
            
            console.log('Scrape button clicked, URL:', url);
            status.html('Importing data...');
            
            $.ajax({
                url: 'https://boatersmkt.com/scrape',
                method: 'POST',
                data: JSON.stringify({ url: url }),
                contentType: 'application/json',
                success: function(data) {
                    console.log('Data received:', data);
                    // Populate HivePress fields
                    $('input[name="listing_title"]').val(data.title);
                    $('textarea[name="listing_description"]').val(data.description);
                    $('input[name="listing_price"]').val(data.price.replace('$', ''));
                    $('input[name="listing_location"]').val(data.location);
                    
                    status.html('Data imported successfully!');
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    status.html('Error importing data: ' + error);
                }
            });
        });
    });
    </script>
    <?php
}, 100);  // Added high priority to ensure it loads after other scripts

// Debug: Log all HivePress hooks
add_action('all', function($tag) {
    if (strpos($tag, 'hivepress') !== false) {
        error_log('HivePress Hook Fired: ' . $tag);
    }
});

// Debug: Log plugin activation
register_activation_hook(__FILE__, function() {
    error_log('HivePress Scraper: Plugin activated');
}); 
<?php
/**
 * Plugin Name: HivePress Listing Scraper
 * Description: Adds FB and Sailing Forums listing scraping functionality to HivePress
 * Version: 1.0
 * Author: Max Penders
 */

// Debug logging function
function scraper_log($message) {
    error_log('HivePress Scraper: ' . $message);
}

scraper_log('Plugin file loaded');

// Hook earlier to ensure HivePress is loaded
add_action('plugins_loaded', function() {
    scraper_log('Plugins loaded hook triggered');
    
    // Add filter for modifying form fields
    add_filter('hivepress/v1/forms/submit_listing', function($form) {
        scraper_log('Fields filter triggered');
        
        // Add our custom fields at the beginning
        return array_merge(
            [
                'scraper_url' => [
                    'label' => 'Import Listing',
                    'type' => 'text',
                    '_order' => 1,
                ],
                'scraper_button' => [
                    'type' => 'button',
                    'display_type' => 'submit',
                    'label' => 'Import Data',
                    '_order' => 2,
                    'attributes' => [
                        'id' => 'scrape-button',
                        'class' => ['hp-button', 'hp-button--primary'],
                    ],
                ],
                'scraper_status' => [
                    'type' => 'content',
                    '_order' => 3,
                    'content' => '<div id="scraper-status"></div>',
                ],
            ],
            $fields
        );
    }, 20);
    
    // Add the JavaScript
    add_action('wp_footer', function() {
        if (!is_page('submit-listing')) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Scraper script loaded');
            
            $('#scrape-button').on('click', function(e) {
                e.preventDefault();
                const url = $('input[name="scraper_url"]').val();
                const status = $('#scraper-status');
                
                console.log('Scrape button clicked, URL:', url);
                status.html('Importing data...');
                
                $.ajax({
                    url: 'https://boatersmkt.com/scrape',
                    method: 'POST',
                    data: JSON.stringify({ url: url }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success && response.data) {
                            $('input[name="title"]').val(response.data.title);
                            $('textarea[name="description"]').val(response.data.description);
                            $('input[name="price"]').val(response.data.price.replace('$', ''));
                            $('input[name="location"]').val(response.data.location);
                            status.html('Data imported successfully!');
                        } else {
                            status.html('Error: Failed to import data');
                        }
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
    }, 100);
});

// Debug: Log all HivePress-related hooks
add_action('all', function($tag) {
    if (strpos($tag, 'hivepress') !== false) {
        scraper_log('Hook fired: ' . $tag);
    }
});

// Add the URL field to listing attributes
add_filter('hivepress/v1/models/listing/attributes', function($attributes) {
    error_log('HivePress Scraper: Listing attributes hook fired');
    
    $attributes['scraper_url'] = [
        'editable'  => true,
        'name'      => 'scraper_url',
        'label'     => 'URL to Scrape',
        'type'      => 'url',
        'required'  => true,
        '_order'    => 15,
        'settings'  => [
            'max_length' => 2048,
        ],
    ];
    
    return $attributes;
});

// Add the scraper form before the main listing form
add_action('hivepress/v1/templates/listing_submit_page/content', function() {
    ?>
    <div class="hp-form hp-form--narrow">
        <h3>Import Listing Data</h3>
        <div class="hp-form__fields">
            <div class="hp-form__field hp-form__field--text">
                <label class="hp-field__label">URL to Import</label>
                <input type="text" name="scraper_url" class="hp-field hp-field--text">
            </div>
            <button type="button" id="scrape-button" class="hp-button hp-button--primary hp-button--block">
                Import Data
            </button>
            <div id="scraper-status" class="hp-form__message"></div>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        console.log('Scraper script loaded');
        
        $('#scrape-button').on('click', function(e) {
            e.preventDefault();
            const url = $('input[name="scraper_url"]').val();
            const status = $('#scraper-status');
            
            console.log('Scrape button clicked, URL:', url);
            status.html('Importing data...');
            
            $.ajax({
                url: 'https://boatersmkt.com/scrape',
                method: 'POST',
                data: JSON.stringify({ url: url }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success && response.data) {
                        $('input[name="title"]').val(response.data.title);
                        $('textarea[name="description"]').val(response.data.description);
                        $('input[name="price"]').val(response.data.price.replace('$', ''));
                        $('input[name="location"]').val(response.data.location);
                        status.html('Data imported successfully!').addClass('hp-form__message--success');
                    } else {
                        status.html('Error: Failed to import data').addClass('hp-form__message--error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    status.html('Error importing data: ' + error).addClass('hp-form__message--error');
                }
            });
        });
    });
    </script>
    <?php
}, 5);

// Remove the old form modifications
// ... remove or comment out the previous listing_update filter ...

// ... existing code ... 
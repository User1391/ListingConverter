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

// Add the scraper form to the listing submit form
add_filter('hivepress/v1/forms/submit_listing', function($form) {
    scraper_log('Adding scraper field to listing submit form');
    
    // Add our scraper section before other fields
    $form['fields'] = array_merge(
        [
            'scraper_section' => [
                'type'   => 'section',
                'title'  => 'Import Listing',
                '_order' => 5,
                'fields' => [
                    'scraper_url' => [
                        'label'     => 'URL to Import From',
                        'type'      => 'url',
                        'required'  => false,
                        '_order'    => 10,
                        'max_length' => 2048,
                        '_render'   => false, // This prevents HivePress from processing the field
                        'html'      => '
                            <div class="hp-form__field hp-form__field--text">
                                <label class="hp-field__label">URL to Import</label>
                                <input type="url" name="scraper_url" class="hp-field__input">
                                <button id="scrape-button" class="hp-button hp-button--primary">Import Data</button>
                                <div id="scraper-status"></div>
                            </div>
                        ',
                    ],
                ],
            ],
        ],
        $form['fields']
    );
    
    // Add our JavaScript
    add_action('wp_footer', function() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Scraper script loaded');
            
            $('#scrape-button').on('click', function(e) {
                e.preventDefault();
                const url = $('input[name="scraper_url"]').val();
                const status = $('#scraper-status');
                
                console.log('Scrape button clicked, URL:', url);
                status.html('Importing data...').addClass('hp-form__message');
                
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
                            status.html('Data imported successfully!').removeClass('hp-form__message--error').addClass('hp-form__message--success');
                        } else {
                            status.html('Error: Failed to import data').removeClass('hp-form__message--success').addClass('hp-form__message--error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ajax error:', error);
                        status.html('Error importing data: ' + error).removeClass('hp-form__message--success').addClass('hp-form__message--error');
                    }
                });
            });
        });
        </script>
        <?php
    });
    
    return $form;
});

// Remove the old form modifications
// ... remove or comment out the previous listing_update filter ...

// Add the scraper form to the page content
add_filter('the_content', function($content) {
    // Only add to the submit listing page
    if (!is_page('submit-listing')) {
        return $content;
    }
    
    scraper_log('Adding scraper form to content');
    
    $scraper_form = '
    <div class="hp-form hp-form--narrow">
        <h3>Import Listing Data</h3>
        <div class="hp-form__fields">
            <div class="hp-form__field hp-form__field--text">
                <label class="hp-field__label">URL to Import</label>
                <input type="url" name="scraper_url" class="hp-field__input">
            </div>
            <button id="scrape-button" class="hp-button hp-button--primary">Import Data</button>
            <div id="scraper-status"></div>
        </div>
    </div>';
    
    // Add our JavaScript
    add_action('wp_footer', function() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Scraper script loaded');
            
            $('#scrape-button').on('click', function(e) {
                e.preventDefault();
                const url = $('input[name="scraper_url"]').val();
                const status = $('#scraper-status');
                
                console.log('Scrape button clicked, URL:', url);
                status.html('Importing data...').addClass('hp-form__message');
                
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
                            status.html('Data imported successfully!').removeClass('hp-form__message--error').addClass('hp-form__message--success');
                        } else {
                            status.html('Error: Failed to import data').removeClass('hp-form__message--success').addClass('hp-form__message--error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ajax error:', error);
                        status.html('Error importing data: ' + error).removeClass('hp-form__message--success').addClass('hp-form__message--error');
                    }
                });
            });
        });
        </script>
        <?php
    });
    
    // Add our form before the main content
    return $scraper_form . $content;
}, 5);

// ... existing code ... 
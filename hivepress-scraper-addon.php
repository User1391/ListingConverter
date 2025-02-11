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

// Add scraper fields to the listing form
add_filter('hivepress/v1/forms/listing_submit/fields', function($fields) {
    scraper_log('Adding scraper fields to form');
    
    $scraper_fields = [
        'scraper_url' => [
            'label' => 'Import Listing',
            'type' => 'text',
            'placeholder' => 'Enter Facebook or SailingForums URL',
            '_order' => 1,
        ],
        'scraper_button' => [
            'type' => 'button',
            'label' => 'Import Data',
            'caption' => 'Import',
            '_order' => 2,
            'attributes' => [
                'id' => 'scrape-button',
            ],
        ],
        'scraper_status' => [
            'type' => 'content',
            '_order' => 3,
            'content' => '<div id="scraper-status" class="hp-form__message"></div>',
        ],
    ];
    
    return array_merge($scraper_fields, $fields);
});

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
                    console.error('Ajax error:', error);
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

// Debug logging for HivePress hooks
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
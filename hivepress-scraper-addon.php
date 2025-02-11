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

// Add scraper section to the listing submission template
add_filter('hivepress/v1/templates/listing_submit_form/blocks', function($blocks) {
    scraper_log('Adding scraper section to template blocks');
    
    array_unshift($blocks, [
        'type' => 'section',
        'title' => 'Import Listing',
        'blocks' => [
            [
                'type' => 'form',
                'form' => 'scraper',
                'blocks' => [
                    [
                        'type' => 'row',
                        'blocks' => [
                            [
                                'type' => 'text',
                                'name' => 'scraper_url',
                                'label' => 'Import from Facebook or SailingForums',
                                'placeholder' => 'Enter listing URL',
                                '_order' => 10,
                            ],
                            [
                                'type' => 'button',
                                'label' => 'Import Data',
                                'id' => 'scrape-button',
                                'class' => ['hp-button', 'hp-button--primary'],
                                '_order' => 20,
                            ],
                        ],
                    ],
                    [
                        'type' => 'content',
                        'content' => '<div id="scraper-status" class="hp-form__message"></div>',
                        '_order' => 30,
                    ],
                ],
            ],
        ],
    ]);
    
    return $blocks;
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
                        // Update form fields with proper HivePress field names
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
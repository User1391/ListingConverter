<?php
/**
 * Plugin Name: HivePress Listing Scraper
 * Description: Adds FB and Sailing Forums listing scraping functionality to HivePress
 * Version: 1.0
 * Author: Max Penders
 */

// Add scraper section before the listing form
add_action('hivepress/v1/templates/listing_submit_page/blocks', function($blocks) {
    array_unshift($blocks, [
        'type' => 'container',
        'blocks' => [
            [
                'type' => 'content',
                'content' => '
                    <div class="hp-form hp-form--narrow">
                        <div class="hp-form__field">
                            <label class="hp-field__label">Import Listing</label>
                            <input type="text" id="listing-url" class="hp-field hp-field--text" placeholder="Enter Facebook or SailingForums URL">
                        </div>
                        <button id="scrape-button" class="hp-button hp-button--secondary" style="margin-top: 10px; margin-bottom: 20px;">Import Data</button>
                        <div id="scraper-status" class="hp-form__messages"></div>
                    </div>
                ',
            ],
        ],
    ]);
    
    return $blocks;
});

// Add the JavaScript for the scraper functionality
add_action('wp_footer', function() {
    if (!is_page('submit-listing')) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('Scraper script loaded');
        
        $(document).on('click', '#scrape-button', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Scrape button clicked');
            
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
                    console.log('Received response:', response);
                    
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
                    console.error('Response:', xhr.responseText);
                    
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

// Optional: Keep the hook logging for debugging
add_action('all', function($tag) {
    if (strpos($tag, 'hivepress') !== false) {
        error_log('HivePress Hook: ' . $tag);
    }
}); 
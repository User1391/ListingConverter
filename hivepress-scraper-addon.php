<?php
/**
 * Plugin Name: HivePress Listing Scraper
 * Description: Adds FB and Sailing Forums listing scraping functionality to HivePress
 * Version: 1.0
 * Author: Max Penders
 */

// Add the scraper button and input field to the listing submission form
add_action('hivepress/v1/templates/listing_submit_details_page/content', function() {
    ?>
    <div class="hp-form__field scraper-container" style="margin-bottom: 20px;">
        <label class="hp-form__label">Import Listing</label>
        <input type="text" id="listing-url" class="hp-field hp-field--text" placeholder="Enter Facebook or SailingForums URL">
        <button id="scrape-button" class="hp-button hp-button--primary" style="margin-top: 10px;">
            Import Data
        </button>
        <div id="scraper-status"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#scrape-button').click(function(e) {
            e.preventDefault();
            const url = $('#listing-url').val();
            const status = $('#scraper-status');
            
            status.html('Importing data...');
            
            $.ajax({
                url: 'YOUR_PYTHON_SERVICE_URL/scrape',
                method: 'POST',
                data: JSON.stringify({ url: url }),
                contentType: 'application/json',
                success: function(data) {
                    // Populate HivePress fields
                    // Adjust these selectors based on your actual HivePress field IDs
                    $('input[name="listing_title"]').val(data.title);
                    $('textarea[name="listing_description"]').val(data.description);
                    $('input[name="listing_price"]').val(data.price.replace('$', ''));
                    $('input[name="listing_location"]').val(data.location);
                    
                    // Handle images
                    // Note: You'll need to implement proper image handling
                    // as HivePress expects local files rather than URLs
                    
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

// Or more specifically for the form itself
add_action('hivepress/v1/forms/listing_submit/header', function() {
    ?>
    <div class="hp-form__field scraper-container" style="margin-bottom: 20px;">
        <label class="hp-form__label">Import Listing</label>
        <input type="text" id="listing-url" class="hp-field hp-field--text" placeholder="Enter Facebook or SailingForums URL">
        <button id="scrape-button" class="hp-button hp-button--primary" style="margin-top: 10px;">
            Import Data
        </button>
        <div id="scraper-status"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#scrape-button').click(function(e) {
            e.preventDefault();
            const url = $('#listing-url').val();
            const status = $('#scraper-status');
            
            status.html('Importing data...');
            
            $.ajax({
                url: 'YOUR_PYTHON_SERVICE_URL/scrape',
                method: 'POST',
                data: JSON.stringify({ url: url }),
                contentType: 'application/json',
                success: function(data) {
                    // Populate HivePress fields
                    // Adjust these selectors based on your actual HivePress field IDs
                    $('input[name="listing_title"]').val(data.title);
                    $('textarea[name="listing_description"]').val(data.description);
                    $('input[name="listing_price"]').val(data.price.replace('$', ''));
                    $('input[name="listing_location"]').val(data.location);
                    
                    // Handle images
                    // Note: You'll need to implement proper image handling
                    // as HivePress expects local files rather than URLs
                    
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

add_action('all', function($tag) {
    if (strpos($tag, 'hivepress') !== false) {
        error_log('HivePress Hook: ' . $tag);
    }
}); 
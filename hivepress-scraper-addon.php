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

// Hook into HivePress form
add_action('init', function() {
    scraper_log('Init hook triggered');
    
    add_filter('hivepress/v1/forms/submit_listing', function($form) {
        scraper_log('Form filter triggered');
        
        // Add just the URL field first
        $form['fields'] = array_merge(
            [
                'scraper_url' => [
                    'label' => 'Import Listing',
                    'type' => 'text',
                    '_order' => 1,
                ],
            ],
            $form['fields']
        );
        
        scraper_log('Form modified');
        return $form;
    });
});

// Log all HivePress hooks to see what's available
add_action('all', function($tag) {
    if (strpos($tag, 'hivepress') !== false) {
        scraper_log('Hook fired: ' . $tag);
    }
}); 
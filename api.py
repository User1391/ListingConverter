from flask import Flask, request, jsonify
from flask_cors import CORS
from scrape import extract_listing_data
import traceback
import logging
import sys

# Configure logging
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout),  # Log to stdout for systemd to capture
        logging.FileHandler('/var/log/listing-scraper.log')  # Also log to file
    ]
)
logger = logging.getLogger('listing-scraper')

app = Flask(__name__)
CORS(app, resources={
    r"/scrape": {
        "origins": ["https://boatersmkt.com"],
        "methods": ["POST"],
        "allow_headers": ["Content-Type"]
    }
})

@app.route('/scrape', methods=['POST'])
def scrape():
    try:
        if not request.is_json:
            logger.error("Request is not JSON")
            return jsonify({
                'success': False,
                'error': 'Content-Type must be application/json'
            }), 400

        url = request.json.get('url')
        if not url:
            logger.error("No URL provided")
            return jsonify({
                'success': False,
                'error': 'URL is required'
            }), 400

        logger.info(f"Received scrape request for URL: {url}")
        
        # Call the scraping function from scrape.py
        logger.info("Calling extract_listing_data...")
        data = extract_listing_data(url)
        logger.info(f"Received data from scraper: {data}")
        
        if data:
            logger.info(f"Successfully scraped data: {data}")
            return jsonify({
                'success': True,
                'data': data
            })
        else:
            logger.error(f"Failed to extract data from URL: {url}")
            return jsonify({
                'success': False,
                'error': 'Failed to extract data'
            }), 400
            
    except Exception as e:
        logger.error(f"Error processing request: {str(e)}")
        logger.error(f"Stack trace: {traceback.format_exc()}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

if __name__ == '__main__':
    app.debug = True
    logger.info("Starting Flask application...")
    app.run(host='0.0.0.0', port=5000, ssl_context='adhoc') 
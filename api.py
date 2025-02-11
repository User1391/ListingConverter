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
        data = request.get_json()
        url = data.get('url')
        
        if not url:
            return jsonify({"error": "URL is required"}), 400
            
        logging.info(f"Received scrape request for URL: {url}")
        
        result = extract_listing_data(url)
        
        # Check for error in result
        if result.get("error"):
            return jsonify({
                "success": False,
                "error": result["error"],
                "data": None
            }), 403
            
        logging.info(f"Successfully scraped data: {result}")
        return jsonify({
            "success": True,
            "error": None,
            "data": result
        })

    except Exception as e:
        logging.error(f"Error processing request: {str(e)}")
        return jsonify({
            "success": False,
            "error": str(e),
            "data": None
        }), 500

if __name__ == '__main__':
    app.debug = True
    logger.info("Starting Flask application...")
    app.run(host='0.0.0.0', port=5000, ssl_context='adhoc') 
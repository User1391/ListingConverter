from flask import Flask, request, jsonify
from flask_cors import CORS
from scrape import extract_listing_data  # Import the function from scrape.py
import traceback

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
            print("Request is not JSON")  # Debug log
            return jsonify({
                'success': False,
                'error': 'Content-Type must be application/json'
            }), 400

        url = request.json.get('url')
        if not url:
            print("No URL provided")  # Debug log
            return jsonify({
                'success': False,
                'error': 'URL is required'
            }), 400

        print(f"Received scrape request for URL: {url}")  # Debug log
        
        # Call the scraping function from scrape.py
        print("Calling extract_listing_data...")  # Debug log
        data = extract_listing_data(url)
        print(f"Received data from scraper: {data}")  # Debug log
        
        if data:
            print(f"Successfully scraped data: {data}")  # Debug log
            return jsonify({
                'success': True,
                'data': data
            })
        else:
            print(f"Failed to extract data from URL: {url}")  # Debug log
            return jsonify({
                'success': False,
                'error': 'Failed to extract data'
            }), 400
            
    except Exception as e:
        print(f"Error processing request: {str(e)}")  # Debug log
        print(f"Stack trace: {traceback.format_exc()}")  # Debug log
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

if __name__ == '__main__':
    app.debug = True  # Enable debug mode
    app.run(host='0.0.0.0', port=5000, ssl_context='adhoc')  # Enable HTTPS 
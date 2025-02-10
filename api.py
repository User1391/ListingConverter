from flask import Flask, request, jsonify
from flask_cors import CORS
from scrape import extract_listing_data
import requests
from io import BytesIO

app = Flask(__name__)
CORS(app)  # Enable CORS for WordPress integration

@app.route('/scrape', methods=['POST'])
def scrape():
    try:
        url = request.json['url']
        data = extract_listing_data(url)
        
        if data:
            # Download and process images if needed
            processed_images = []
            for image_url in data['images']:
                # You might want to download and store images locally here
                # or handle them differently based on your setup
                processed_images.append(image_url)
            
            data['images'] = processed_images
            
            return jsonify({
                'success': True,
                'data': data
            })
        else:
            return jsonify({
                'success': False,
                'error': 'Failed to extract data'
            }), 400
            
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000) 
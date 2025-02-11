from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
from io import BytesIO
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By
import time
import re

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
            return jsonify({
                'success': False,
                'error': 'Content-Type must be application/json'
            }), 400

        url = request.json.get('url')
        if not url:
            return jsonify({
                'success': False,
                'error': 'URL is required'
            }), 400

        print(f"Received scrape request for URL: {url}")  # Debug log
        
        # Initialize the driver
        driver = setup_driver()
        try:
            # Use our new function
            if 'facebook.com' in url:
                data = extract_facebook_data(driver, url)
            else:
                data = None  # Add other handlers as needed
                
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
        finally:
            # Make sure to close the driver
            try:
                driver.quit()
            except:
                pass
            
    except Exception as e:
        print(f"Error processing request: {str(e)}")  # Debug log
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

def setup_driver():
    chrome_options = Options()
    chrome_options.add_argument('--headless')
    chrome_options.add_argument('--disable-notifications')
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--disable-gpu')
    chrome_options.add_argument('--lang=en')
    # Add these options for better handling of dynamic content
    chrome_options.add_argument('--window-size=1920,1080')
    chrome_options.add_argument('--start-maximized')
    chrome_options.add_argument('--disable-blink-features=AutomationControlled')
    # Add a more realistic user agent
    chrome_options.add_argument('--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
    
    driver = webdriver.Chrome(options=chrome_options)
    driver.implicitly_wait(10)  # Add implicit wait
    return driver

def extract_facebook_data(driver, url):
    try:
        driver.get(url)
        # Add longer wait time for Facebook
        time.sleep(5)
        
        # Debug logging
        print("Page source length:", len(driver.page_source))
        print("Current URL:", driver.current_url)
        
        listing_data = {}
        
        try:
            # Wait for main content to load
            WebDriverWait(driver, 10).until(
                EC.presence_of_element_located((By.CSS_SELECTOR, 'div[role="main"]'))
            )
            main_content = driver.find_element(By.CSS_SELECTOR, 'div[role="main"]').text
            print("Main content found:", main_content[:200])  # Print first 200 chars
        except Exception as e:
            print("Error finding main content:", str(e))
            main_content = driver.page_source
        
        content_lines = main_content.split('\n')
        
        # Get title and price
        for i, line in enumerate(content_lines):
            if '$' in line and i > 0:
                listing_data['title'] = content_lines[i-1].strip()
                listing_data['price'] = line.strip()
                break
        
        # Get location
        location_pattern = r'in ([^,\n]+),\s*([A-Z]{2})'
        location_match = re.search(location_pattern, main_content)
        listing_data['location'] = f"{location_match.group(1)}, {location_match.group(2)}" if location_match else "Location not specified"
        
        # Get description
        desc_pattern = r'Description\n(.*?)(?=\n(?:Location|Category|Condition)|$)'
        desc_match = re.search(desc_pattern, main_content, re.DOTALL | re.IGNORECASE)
        listing_data['description'] = desc_match.group(1).strip() if desc_match else "Description not found"
        
        # Get images
        try:
            images = driver.find_elements(By.CSS_SELECTOR, 'img[class*="x5yr21d"], img[alt*="Product"], div[role="img"]')
            image_urls = []
            for img in images:
                src = img.get_attribute('src')
                if src and 'https://' in src and not any(x in src.lower() for x in ['profile', 'avatar']):
                    image_urls.append(src)
            listing_data['images'] = image_urls
            print(f"Found {len(image_urls)} images")
        except Exception as e:
            print("Error getting images:", str(e))
            listing_data['images'] = []
        
        print("Extracted data:", listing_data)  # Debug log
        return listing_data
        
    except Exception as e:
        print(f"Error extracting Facebook data: {str(e)}")
        return None

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, ssl_context='adhoc')  # Enable HTTPS 
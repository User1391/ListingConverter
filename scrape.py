from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
import json
import time
import re

def setup_driver():
    chrome_options = Options()
    chrome_options.add_argument('--headless')  # Run in headless mode
    chrome_options.add_argument('--disable-notifications')
    chrome_options.add_argument('--no-sandbox')  # Required for running as root
    chrome_options.add_argument('--disable-dev-shm-usage')  # Required on some servers
    chrome_options.add_argument('--disable-gpu')  # Required on some servers
    chrome_options.add_argument('--lang=en')
    chrome_options.add_argument('--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36')
    
    driver = webdriver.Chrome(options=chrome_options)
    return driver

def extract_sailingforums_data(driver, url):
    driver.get(url)
    time.sleep(3)
    
    listing_data = {}
    
    try:
        # Get title from thread title
        title = driver.find_element(By.CSS_SELECTOR, '.p-title-value').text
        listing_data['title'] = title
        
        # Get price from structured field
        try:
            price_element = driver.find_element(By.CSS_SELECTOR, 'dl[data-field="Price"] dd')
            if price_element:
                price_value = price_element.text.strip()
                listing_data['price'] = f"${price_value}"
            else:
                # Fallback to searching in title/content
                first_post = driver.find_element(By.CSS_SELECTOR, '.message-body').text
                price_pattern = r'\$[\d,]+(?:\.\d{2})?'
                price_match = re.search(price_pattern, first_post)
                listing_data['price'] = price_match.group() if price_match else "Price not specified"
        except:
            listing_data['price'] = "Price not specified"
        
        # Get location from structured fields
        try:
            city_element = driver.find_element(By.CSS_SELECTOR, 'dl[data-field="City"] dd')
            state_element = driver.find_element(By.CSS_SELECTOR, 'dl[data-field="State"] dd')
            
            if city_element and state_element:
                city = city_element.text.strip()
                state = state_element.text.strip()
                listing_data['location'] = f"{city}, {state}"
            else:
                # Fallback to searching in content
                first_post = driver.find_element(By.CSS_SELECTOR, '.message-body').text
                location_match = re.search(r'(?:currently in|located in) ([^.\n]+)', first_post, re.IGNORECASE)
                listing_data['location'] = location_match.group(1) if location_match else "Location not specified"
        except:
            listing_data['location'] = "Location not specified"
        
        # Get description from first post
        description = driver.find_element(By.CSS_SELECTOR, '.message-body').text
        listing_data['description'] = description.strip()
        
        # Get images from first post and attachments
        images = driver.find_elements(By.CSS_SELECTOR, '.message-attachments img, .message-body img')
        image_urls = []
        seen_urls = set()
        for img in images:
            src = img.get_attribute('src')
            # Check for full-size image URL
            data_url = img.get_attribute('data-url')
            if data_url:  # Use the full-size image if available
                src = data_url
            if src and src not in seen_urls and not src.endswith(('.gif', '.svg')):
                image_urls.append(src)
                seen_urls.add(src)
        listing_data['images'] = image_urls
        
    except Exception as e:
        print(f"Error extracting sailing forums data: {str(e)}")
        return None
        
    return listing_data

def extract_facebook_data(driver, url):
    try:
        driver.get(url)
        time.sleep(3)
        
        # Close any login popup if it appears
        try:
            close_button = driver.find_element(By.CSS_SELECTOR, '[aria-label="Close"]')
            close_button.click()
        except:
            pass
        
        listing_data = {}
        main_content = driver.find_element(By.CSS_SELECTOR, 'div[role="main"]').text
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
        desc_pattern = r'Condition\n.*?\n(.*?)\n(?=(?:Location|Smithfield|[A-Z][a-z]+,\s*[A-Z]{2}))'
        desc_match = re.search(desc_pattern, main_content, re.DOTALL)
        listing_data['description'] = desc_match.group(1).strip() if desc_match else "Description not found"
        
        # Get images
        images = driver.find_elements(By.CSS_SELECTOR, 'img[class*="x5yr21d"]')
        image_urls = []
        seen_urls = set()
        for img in images:
            src = img.get_attribute('src')
            if src and src not in seen_urls:
                image_urls.append(src)
                seen_urls.add(src)
        listing_data['images'] = image_urls
        
        return listing_data
        
    except Exception as e:
        print(f"Error extracting Facebook data: {str(e)}")
        return None

def extract_listing_data(url):
    driver = setup_driver()
    try:
        if "sailingforums.com" in url:
            return extract_sailingforums_data(driver, url)
        elif "facebook.com/marketplace" in url or "facebook.com/share" in url:
            return extract_facebook_data(driver, url)
        else:
            print("Unsupported URL type")
            return None
    finally:
        driver.quit()

def save_to_json(data, filename="listing_data.json"):
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=4, ensure_ascii=False)

def main():
    # Example usage
    print("You can enter a Facebook Marketplace URL or a Sailing Forums URL")
    url = input("Enter listing URL: ")
    
    # Fix the validation to accept both Facebook and Sailing Forums URLs
    if not (url.startswith("https://www.facebook.com/marketplace") or 
            url.startswith("https://www.facebook.com/share") or 
            url.startswith("https://sailingforums.com")):
        print("Please enter a valid Facebook Marketplace or Sailing Forums URL")
        return
    
    print("Scraping listing data...")
    listing_data = extract_listing_data(url)
    
    if listing_data:
        save_to_json(listing_data)
        print(f"Data successfully saved to listing_data.json")
    else:
        print("Failed to extract listing data")

if __name__ == "__main__":
    main()

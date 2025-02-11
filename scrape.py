from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
import json
import time
import re
import traceback
import logging
import random

logger = logging.getLogger('listing-scraper')

def setup_driver():
    chrome_options = Options()
    chrome_options.add_argument('--disable-notifications')
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--disable-gpu')
    chrome_options.add_argument('--lang=en')
    
    # Add the profile directory
    profile_dir = '/home/bitnami/.config/chrome-profile'
    chrome_options.add_argument(f'--user-data-dir={profile_dir}')
    chrome_options.add_argument('--profile-directory=Default')
    
    # Keep existing anti-detection options
    chrome_options.add_argument('--disable-blink-features=AutomationControlled')
    chrome_options.add_experimental_option("excludeSwitches", ["enable-automation"])
    chrome_options.add_experimental_option('useAutomationExtension', False)
    chrome_options.add_argument('--window-size=1920,1080')
    
    # Use a consistent user agent for the profile
    chrome_options.add_argument('--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36')
    
    driver = webdriver.Chrome(options=chrome_options)
    
    # Additional settings after driver creation
    driver.execute_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})")
    
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
        logger.error(f"Error extracting sailing forums data: {str(e)}")
        return None
        
    return listing_data

def extract_facebook_data(driver, url):
    try:
        logger.info(f"Starting Facebook scraping for URL: {url}")
        
        # Try to access the mobile version first
        mobile_url = url.replace('www.facebook.com', 'm.facebook.com')
        logger.info(f"Trying mobile URL: {mobile_url}")
        driver.get(mobile_url)
        time.sleep(3)
        
        # If mobile version fails, try desktop version
        if "login" in driver.current_url.lower():
            logger.info("Mobile version requires login, trying desktop version")
            driver.get(url)
            time.sleep(3)
        
        logger.info(f"Current URL after load: {driver.current_url}")
        
        # Try multiple selectors for the close button
        close_button_selectors = [
            '[aria-label="Close"]',
            'div[role="button"][tabindex="0"]',
            'div[aria-label="Close"][role="button"]',
            'i[data-visualcompletion="css-img"][style*="background-position"]',
            'button[type="submit"][value="Close"]',  # Mobile close button
            '.layerCancel',  # Another common close button class
            '[data-testid="popup_close_button"]'
        ]
        
        for selector in close_button_selectors:
            try:
                close_buttons = driver.find_elements(By.CSS_SELECTOR, selector)
                for button in close_buttons:
                    try:
                        if button.is_displayed():
                            logger.info(f"Found visible close button with selector: {selector}")
                            # Try both click() and JavaScript click
                            try:
                                button.click()
                            except:
                                driver.execute_script("arguments[0].click();", button)
                            logger.info("Clicked close button")
                            time.sleep(1)
                            break
                    except Exception as e:
                        logger.info(f"Error clicking button: {str(e)}")
                        continue
            except Exception as e:
                logger.info(f"Error finding close button with selector {selector}: {str(e)}")
                continue

        # Check if we're still on a login page
        if "login" in driver.current_url.lower() or "You must log in to continue" in driver.page_source:
            logger.info("Still on login page after attempts to bypass")
            return {
                "error": "Login required to view this listing",
                "location": None,
                "description": None,
                "images": []
            }

        listing_data = {}
        
        try:
            logger.info("Looking for main content...")
            main_content = driver.find_element(By.CSS_SELECTOR, 'div[role="main"]')
            logger.info("Found main content element")
            main_text = main_content.text
            logger.info(f"Main content text (first 200 chars): {main_text[:200]}")
        except Exception as e:
            logger.error(f"Error finding main content: {str(e)}")
            return None
        
        content_lines = main_text.split('\n')
        logger.info(f"Split content into {len(content_lines)} lines")
        
        # Get title and price
        logger.info("Looking for title and price...")
        for i, line in enumerate(content_lines):
            logger.info(f"Line {i}: {line}")
            if '$' in line and i > 0:
                listing_data['title'] = content_lines[i-1].strip()
                listing_data['price'] = line.strip()
                break
                
        # Get location
        logger.info("Looking for location...")
        location_pattern = r'in ([^,\n]+),\s*([A-Z]{2})'
        location_match = re.search(location_pattern, main_text)
        listing_data['location'] = f"{location_match.group(1)}, {location_match.group(2)}" if location_match else "Location not specified"
        logger.info(f"Location found: {listing_data['location']}")
        
        # Get description
        logger.info("Looking for description...")
        desc_pattern = r'Condition\n.*?\n(.*?)\n(?=(?:Location|Category|Condition)|$)'
        desc_match = re.search(desc_pattern, main_text, re.DOTALL | re.IGNORECASE)
        listing_data['description'] = desc_match.group(1).strip() if desc_match else "Description not found"
        logger.info(f"Description found: {listing_data['description'][:100]}...")
        
        # Get images
        logger.info("Looking for images...")
        images = driver.find_elements(By.CSS_SELECTOR, 'img[class*="x5yr21d"], img[alt*="Product"], div[role="img"]')
        logger.info(f"Found {len(images)} potential image elements")
        image_urls = []
        seen_urls = set()
        for img in images:
            src = img.get_attribute('src')
            if src and src not in seen_urls:
                image_urls.append(src)
                seen_urls.add(src)
        listing_data['images'] = image_urls
        logger.info(f"Total valid images found: {len(image_urls)}")
        
        logger.info("Facebook data extraction completed")
        logger.info(f"Final data: {listing_data}")
        return listing_data
        
    except Exception as e:
        logger.error(f"Error extracting Facebook data: {str(e)}")
        logger.error(f"Stack trace: {traceback.format_exc()}")
        return {
            "error": f"Error extracting data: {str(e)}",
            "location": None,
            "description": None,
            "images": []
        }

def extract_listing_data(url):
    logger.info(f"Starting extraction for URL: {url}")  # Debug log
    try:
        logger.info("Initializing Chrome driver...")  # Debug log
        driver = setup_driver()
        logger.info("Chrome driver initialized successfully")  # Debug log
        
        if "sailingforums.com" in url:
            logger.info("Processing SailingForums URL...")  # Debug log
            return extract_sailingforums_data(driver, url)
        elif "facebook.com/marketplace" in url or "facebook.com/share" in url:
            logger.info("Processing Facebook URL...")  # Debug log
            data = extract_facebook_data(driver, url)
            logger.info(f"Facebook data extracted: {data}")  # Debug log
            return data
        else:
            logger.info(f"Unsupported URL type: {url}")  # Debug log
            return None
    except Exception as e:
        logger.error(f"Error in extract_listing_data: {str(e)}")  # Debug log
        logger.error(f"Stack trace: {traceback.format_exc()}")  # Debug log
        return None
    finally:
        try:
            driver.quit()
            logger.info("Driver closed successfully")  # Debug log
        except Exception as e:
            logger.error(f"Error closing driver: {str(e)}")  # Debug log

def save_to_json(data, filename="listing_data.json"):
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=4, ensure_ascii=False)

def main():
    # Example usage
    logger.info("You can enter a Facebook Marketplace URL or a Sailing Forums URL")
    url = input("Enter listing URL: ")
    
    # Fix the validation to accept both Facebook and Sailing Forums URLs
    if not (url.startswith("https://www.facebook.com/marketplace") or 
            url.startswith("https://www.facebook.com/share") or 
            url.startswith("https://sailingforums.com")):
        logger.info("Please enter a valid Facebook Marketplace or Sailing Forums URL")
        return
    
    logger.info("Scraping listing data...")
    listing_data = extract_listing_data(url)
    
    if listing_data:
        save_to_json(listing_data)
        logger.info(f"Data successfully saved to listing_data.json")
    else:
        logger.error("Failed to extract listing data")

if __name__ == "__main__":
    main()

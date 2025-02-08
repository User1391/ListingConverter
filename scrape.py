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
    chrome_options.add_argument('--lang=en')
    chrome_options.add_argument('--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36')
    
    driver = webdriver.Chrome(options=chrome_options)
    return driver

def extract_listing_data(url):
    driver = setup_driver()
    
    try:
        driver.get(url)
        time.sleep(3)  # Wait for the page to load
        
        # Close any login popup if it appears
        try:
            close_button = driver.find_element(By.CSS_SELECTOR, '[aria-label="Close"]')
            close_button.click()
        except:
            pass
        
        # Extract listing information
        listing_data = {}
        
        # Get title and price from the main content
        try:
            main_content = driver.find_element(By.CSS_SELECTOR, 'div[role="main"]').text
            content_lines = main_content.split('\n')
            
            # Find title and price
            for i, line in enumerate(content_lines):
                if '$' in line and i > 0:  # Price line contains '$'
                    listing_data['title'] = content_lines[i-1].strip()
                    listing_data['price'] = line.strip()
                    break
        except:
            listing_data['title'] = "Not found"
            listing_data['price'] = "Not found"
            
        # Get location (looking for text after "in" and before the next section)
        try:
            location_pattern = r'in ([^,\n]+),\s*([A-Z]{2})'
            location_match = re.search(location_pattern, main_content)
            if location_match:
                city, state = location_match.groups()
                listing_data['location'] = f"{city}, {state}"
            else:
                listing_data['location'] = "Not found"
        except:
            listing_data['location'] = "Not found"
            
        # Get description (looking for text between condition and location sections)
        try:
            desc_pattern = r'Condition\n.*?\n(.*?)\n(?=(?:Location|Smithfield|[A-Z][a-z]+,\s*[A-Z]{2}))'
            desc_match = re.search(desc_pattern, main_content, re.DOTALL)
            if desc_match:
                description = desc_match.group(1).strip()
                listing_data['description'] = description
            else:
                listing_data['description'] = "Not found"
        except:
            listing_data['description'] = "Not found"
            
        # Get images
        try:
            images = driver.find_elements(By.CSS_SELECTOR, 'img[class*="x5yr21d"]')
            image_urls = []
            seen_urls = set()  # To prevent duplicate images
            for img in images:
                src = img.get_attribute('src')
                if src and src not in seen_urls:
                    image_urls.append(src)
                    seen_urls.add(src)
            listing_data['images'] = image_urls
        except:
            listing_data['images'] = []
            
        return listing_data
        
    except Exception as e:
        print(f"Error: {str(e)}")
        return None
        
    finally:
        driver.quit()

def save_to_json(data, filename="listing_data.json"):
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=4, ensure_ascii=False)

def main():
    # Example usage
    url = input("Enter Facebook Marketplace listing URL: ")
    
    if not url.startswith("https://www.facebook.com/marketplace") and not url.startswith("https://www.facebook.com/share"):
        print("Please enter a valid Facebook Marketplace URL")
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

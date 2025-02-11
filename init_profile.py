from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
import time
import getpass
import os
import shutil
import subprocess

def kill_chrome_processes():
    try:
        subprocess.run(['pkill', '-f', 'chrome'])
        subprocess.run(['pkill', '-f', 'chromium'])
        time.sleep(2)  # Wait for processes to be killed
        print("Killed existing Chrome processes")
    except Exception as e:
        print(f"Error killing Chrome processes: {e}")

def init_chrome_profile():
    profile_dir = '/home/bitnami/.config/chrome-profile'
    
    # Kill any running Chrome processes
    kill_chrome_processes()
    
    # Clean up existing profile if it exists
    if os.path.exists(profile_dir):
        try:
            shutil.rmtree(profile_dir)
            print(f"Removed existing profile at {profile_dir}")
            time.sleep(2)  # Wait for directory to be fully removed
        except Exception as e:
            print(f"Error removing profile: {e}")
            return
    
    # Create fresh profile directory
    try:
        os.makedirs(profile_dir, exist_ok=True)
        os.chmod(profile_dir, 0o700)
        print(f"Created new profile directory at {profile_dir}")
        time.sleep(1)  # Wait for directory to be ready
    except Exception as e:
        print(f"Error creating profile directory: {e}")
        return
    
    # Set up Chrome options
    chrome_options = Options()
    chrome_options.add_argument('--disable-notifications')
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument(f'--user-data-dir={profile_dir}')
    chrome_options.add_argument('--profile-directory=Default')
    
    driver = None
    try:
        # Start Chrome (not headless for initial setup)
        print("Starting Chrome...")
        driver = webdriver.Chrome(options=chrome_options)
        
        # Go to Facebook login
        print("Navigating to Facebook login...")
        driver.get('https://www.facebook.com/login')
        
        # Get credentials
        email = input("Enter Facebook email: ")
        password = getpass.getpass("Enter Facebook password: ")
        
        # Find and fill login form
        email_field = driver.find_element(By.ID, "email")
        pass_field = driver.find_element(By.ID, "pass")
        
        email_field.send_keys(email)
        pass_field.send_keys(password)
        
        # Click login
        login_button = driver.find_element(By.NAME, "login")
        login_button.click()
        
        # Wait for login to complete
        print("Waiting for login to complete...")
        time.sleep(10)
        
        # Test if login was successful
        if "login" not in driver.current_url.lower():
            print("Login successful! Profile has been initialized.")
        else:
            print("Login may have failed. Please check the browser.")
        
        # Give user time to verify and handle any 2FA if needed
        input("Press Enter after verifying login and handling any additional prompts...")
        
    except Exception as e:
        print(f"Error during initialization: {e}")
    finally:
        if driver:
            try:
                driver.quit()
                print("Chrome closed successfully")
            except:
                print("Error closing Chrome")
                # Force kill Chrome processes again
                kill_chrome_processes()

if __name__ == "__main__":
    init_chrome_profile() 
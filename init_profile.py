from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from pyvirtualdisplay import Display
import time
import getpass
import os
import shutil
import subprocess
import tempfile

def kill_chrome_processes():
    try:
        subprocess.run(['pkill', '-f', 'chrome'])
        subprocess.run(['pkill', '-f', 'chromium'])
        time.sleep(2)
        print("Killed existing Chrome processes")
    except Exception as e:
        print(f"Error killing Chrome processes: {e}")

def init_chrome_profile():
    final_profile_dir = '/home/bitnami/.config/chrome-profile'
    
    # Kill any running Chrome processes
    kill_chrome_processes()
    
    # Start virtual display
    display = Display(visible=0, size=(1920, 1080))
    display.start()
    print("Started virtual display")
    
    # Create a temporary profile directory
    with tempfile.TemporaryDirectory() as temp_profile_dir:
        print(f"Created temporary profile at {temp_profile_dir}")
        
        # Set up Chrome options with temporary profile
        chrome_options = Options()
        chrome_options.add_argument('--disable-notifications')
        chrome_options.add_argument('--no-sandbox')
        chrome_options.add_argument('--disable-dev-shm-usage')
        chrome_options.add_argument(f'--user-data-dir={temp_profile_dir}')
        chrome_options.add_argument('--profile-directory=Default')
        chrome_options.add_argument('--start-maximized')
        
        driver = None
        try:
            # Start Chrome
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
                
                # Give user time to verify and handle any 2FA if needed
                input("Press Enter after verifying login and handling any additional prompts...")
                
                # Close Chrome
                driver.quit()
                driver = None
                
                # Copy the temporary profile to final location
                print("Copying profile to permanent location...")
                if os.path.exists(final_profile_dir):
                    shutil.rmtree(final_profile_dir)
                shutil.copytree(temp_profile_dir, final_profile_dir)
                os.chmod(final_profile_dir, 0o700)
                print("Profile setup complete!")
            else:
                print("Login may have failed. Please check the browser.")
            
        except Exception as e:
            print(f"Error during initialization: {e}")
        finally:
            if driver:
                try:
                    driver.quit()
                except:
                    print("Error closing Chrome")
                    kill_chrome_processes()
            display.stop()  # Stop virtual display

if __name__ == "__main__":
    init_chrome_profile() 
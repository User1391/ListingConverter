import tkinter as tk
from tkinter import ttk, messagebox
import webbrowser
import os
from scrape import extract_listing_data, save_to_json
import shutil

class ListingConverterApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Marketplace Listing Converter")
        self.root.geometry("600x200")
        
        # Create and configure main frame
        main_frame = ttk.Frame(root, padding="20")
        main_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # URL Input
        ttk.Label(main_frame, text="Facebook Marketplace URL:").grid(row=0, column=0, sticky=tk.W, pady=5)
        self.url_var = tk.StringVar()
        self.url_entry = ttk.Entry(main_frame, textvariable=self.url_var, width=50)
        self.url_entry.grid(row=1, column=0, sticky=(tk.W, tk.E), pady=5)
        
        # Progress bar
        self.progress = ttk.Progressbar(main_frame, mode='indeterminate')
        self.progress.grid(row=2, column=0, sticky=(tk.W, tk.E), pady=10)
        
        # Buttons
        button_frame = ttk.Frame(main_frame)
        button_frame.grid(row=3, column=0, sticky=(tk.W, tk.E), pady=10)
        
        ttk.Button(button_frame, text="Convert", command=self.convert_listing).pack(side=tk.LEFT, padx=5)
        ttk.Button(button_frame, text="View Latest", command=self.view_latest).pack(side=tk.LEFT, padx=5)
        
        # Status label
        self.status_var = tk.StringVar()
        self.status_var.set("Ready")
        ttk.Label(main_frame, textvariable=self.status_var).grid(row=4, column=0, sticky=tk.W, pady=5)
        
        # Ensure the listings directory exists
        self.listings_dir = "listings"
        os.makedirs(self.listings_dir, exist_ok=True)

    def convert_listing(self):
        url = self.url_var.get().strip()
        
        if not url:
            messagebox.showerror("Error", "Please enter a URL")
            return
            
        if not url.startswith("https://www.facebook.com/marketplace"):
            messagebox.showerror("Error", "Please enter a valid Facebook Marketplace URL")
            return
        
        self.status_var.set("Converting...")
        self.progress.start()
        self.root.update()
        
        try:
            # Extract the listing data
            listing_data = extract_listing_data(url)
            
            if listing_data:
                # Create a new directory for this listing
                listing_dir = os.path.join(self.listings_dir, listing_data['title'].replace(" ", "_"))
                os.makedirs(listing_dir, exist_ok=True)
                
                # Save the JSON file
                json_path = os.path.join(listing_dir, "listing_data.json")
                save_to_json(listing_data, json_path)
                
                # Copy the index.html template
                shutil.copy("index.html", os.path.join(listing_dir, "index.html"))
                
                self.status_var.set("Conversion successful!")
                messagebox.showinfo("Success", "Listing converted successfully!")
                
                # Open the new listing page
                webbrowser.open(f"http://localhost:8000/{listing_dir}/index.html")
            else:
                self.status_var.set("Conversion failed!")
                messagebox.showerror("Error", "Failed to extract listing data")
                
        except Exception as e:
            self.status_var.set("Error occurred!")
            messagebox.showerror("Error", f"An error occurred: {str(e)}")
            
        finally:
            self.progress.stop()
            self.url_var.set("")  # Clear the URL field

    def view_latest(self):
        try:
            # Get the most recent listing directory
            listings = [d for d in os.listdir(self.listings_dir) 
                       if os.path.isdir(os.path.join(self.listings_dir, d))]
            
            if not listings:
                messagebox.showinfo("Info", "No listings found")
                return
                
            latest_listing = max(listings, key=lambda x: os.path.getctime(
                os.path.join(self.listings_dir, x)))
            
            # Open the latest listing in the browser
            webbrowser.open(f"http://localhost:8000/listings/{latest_listing}/index.html")
            
        except Exception as e:
            messagebox.showerror("Error", f"Could not open latest listing: {str(e)}")

def main():
    root = tk.Tk()
    app = ListingConverterApp(root)
    root.mainloop()

if __name__ == "__main__":
    main() 
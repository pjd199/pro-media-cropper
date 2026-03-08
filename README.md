# Pro Media Cropper

**Pro Media Cropper** is a high-precision image editing and cropping tool built natively for WordPress. It is designed for social media managers, photographers, and content creators who need to generate perfectly sized assets for YouTube, Instagram, X (Twitter), and Pinterest while maintaining automatic metadata and attribution tracking.

---

## 🚀 Key Features

* **Header-Integrated Actions:** Quickly upload local files, browse the WordPress Media Library, or search stock image databases directly from the page header.
* **Intelligent Pillarboxing:**
    * **Echo Blur:** Automatically creates a blurred, darkened background using the source image for vertical content.
    * **Custom Colors:** Use a built-in eyedropper to pick colors directly from your preview for a seamless background.
* **Vector & Document Support:** Native support for rendering and cropping **SVG** and **PDF** files via `PDF.js`.
* **Metadata Tracking:** Automatically carries over author and license information from stock providers into the WordPress Media Library description.
* **Modern Save UI:** Save directly to your library with a single click inside the filename bar.

---

## 🛠 Installation
1. Download the pro-media-cropper zip file from the asset list of the **[latest release](https://github.com/pjd199/pro-media-cropper/releases)** on GitHub.
2.  In your WordPress Admin, go to **Plugins > Add New > Upload Plugin**.
3.  Upload the `pro-media-cropper` zip file and click **Install Now**.
4.  **Activate** the plugin.
5.  Access the tool via **Media > Pro Media Cropper**.

---

## 🔑 Setting Up API Keys

To use the "Search Stock Images" feature, enter your keys in **Settings > Pro Media Cropper**.

| Provider | Where to get the key |
| :--- | :--- |
| **Pixabay** | [Pixabay API Docs](https://pixabay.com/api/docs/) (Search for "Your API key") |
| **Unsplash** | [Unsplash Developers](https://unsplash.com/developers) (Create a New Application) |
| **Pexels** | [Pexels API Page](https://www.pexels.com/api/) (Click "Get Started") |



---

## 🎨 Customizing Aspect Ratios

The plugin comes pre-loaded with standard social media sizes. However, you can define your own "Custom Settings" dimensions to match your specific brand or website theme.

1.  Navigate to **Settings > Pro Media Cropper**.
2.  Locate **Custom Dimensions (px)**.
3.  Enter your required Width and Height (e.g., `1200` x `628` for specific ad units).
4.  Save Changes.
5.  In the Cropper tool, select **Custom Settings** from the dropdown to use these dimensions.

---

## 💡 Usage Tips

* **Pillarbox Mode:** If your image doesn't fit the chosen aspect ratio, toggle **Pillarbox Mode**. This allows you to fit the whole image on the canvas without stretching or cutting important details.
* **The Eyedropper:** When using a "Custom" pillarbox style, click **Pick** and then click anywhere on your preview canvas to grab a specific hex code.
* **Search Cache:** The plugin caches stock search results for 24 hours to keep the interface fast. You can manually wipe this cache in the Settings page if needed.

---

## 📜 License

This project is licensed under the **MIT License**.

**Developed by:** [Pete Dibdin](https://github.com/pjd199)

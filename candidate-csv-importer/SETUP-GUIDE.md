# Superio WordPress Theme — Local Setup Guide

This document covers everything done to set up the Superio job board WordPress theme on a local Windows machine using XAMPP.

---

## 1. Tools Required

| Tool | Purpose | Download |
|------|---------|----------|
| XAMPP | Local server (Apache + MySQL + PHP) | https://www.apachefriends.org/ |
| WordPress | CMS platform | https://wordpress.org/download/ |
| Browser | Chrome / Edge / Firefox | — |
| Code Editor | VS Code / Kiro | — |

---

## 2. Install XAMPP

1. Download XAMPP from https://www.apachefriends.org/
2. Run the installer
3. When the UAC warning appears — **do not install to `C:\Program Files`**. Use the default path `C:\xampp\`
4. Complete the installation
5. Open **XAMPP Control Panel**
6. Click **Start** next to **Apache**
7. Click **Start** next to **MySQL**
8. Open browser and go to `http://localhost` — you should see the XAMPP dashboard

> Only Apache and MySQL are needed. Skip FileZilla, Mercury, and Tomcat.

---

## 3. Create the Database

1. Go to `http://localhost/phpmyadmin`
2. Click the **Databases** tab
3. In the "Create database" field, type `superio_db`
4. Click **Create**

---

## 4. Set Up WordPress

### 4a. Download and Place WordPress Files

1. Download WordPress from https://wordpress.org/download/
2. Extract the zip — you get a folder called `wordpress`
3. Copy the **contents** of the `wordpress` folder into `C:\xampp\htdocs\workojas\`
   - Create the `workojas` folder if it doesn't exist
   - The final structure should look like:
     ```
     C:\xampp\htdocs\workojas\
       ├── wp-admin\
       ├── wp-content\
       ├── wp-includes\
       ├── index.php
       ├── wp-config-sample.php
       └── ...
     ```

### 4b. Create wp-config.php

1. In `C:\xampp\htdocs\workojas\`, copy `wp-config-sample.php` and rename the copy to `wp-config.php`
2. Open `wp-config.php` in your editor and update these lines:

```php
define( 'DB_NAME', 'superio_db' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
```

3. Save the file

### 4c. Run the WordPress Installer

1. Go to `http://localhost/workojas` in your browser
2. Select your language and click **Continue**
3. Fill in the site details:
   - Site Title: `Workojas` (or any name)
   - Username: your admin username
   - Password: your admin password
   - Email: your email
4. Click **Install WordPress**
5. Log in at `http://localhost/workojas/wp-admin`

---

## 5. Increase PHP Upload Limit

The Superio theme zip is ~72MB which exceeds the default PHP upload limit. Fix this before uploading the theme.

1. Open `C:\xampp\php\php.ini` in your editor
2. Find and update these values:

```ini
upload_max_filesize = 128M
post_max_size = 128M
max_execution_time = 300
memory_limit = 128M
```

3. Save the file
4. In XAMPP Control Panel, click **Stop** then **Start** on Apache to restart it

---

## 6. Install the Superio Theme

1. In WordPress dashboard, go to **Appearance → Themes → Add New Theme**
2. Click **Upload Theme**
3. Upload `superio_theme.zip`
4. Click **Install Now**
5. Click **Activate**

---

## 7. Install Required Plugins

After activating the theme, a notice appears at the top: *"This theme requires the following plugins"*. The bulk installer has a PHP 8.x compatibility issue, so install plugins individually.

### 7a. Install from WordPress.org (search in Plugins → Add New)

| Plugin | Search Term |
|--------|------------|
| Elementor Page Builder | Elementor |
| CMB2 | CMB2 |
| Contact Form 7 | Contact Form 7 |
| MailChimp for WordPress | MC4WP |
| WooCommerce | WooCommerce |
| One Click Demo Import | One Click Demo Import |
| SVG Support | SVG Support |

> **Important:** Install Elementor version **3.25.11** (not the latest). The theme uses deprecated Elementor Schemes API that was removed in Elementor 3.26+.
> Download 3.25.11 from: https://wordpress.org/plugins/elementor/advanced/

### 7b. Install Bundled Plugins (from theme folder)

These plugins are included inside the theme at:
`C:\xampp\htdocs\workojas\wp-content\themes\superio\inc\plugins\`

Upload each one via **Plugins → Add New → Upload Plugin**:

| File | Plugin Name |
|------|------------|
| `apus-framework.zip` | Apus Framework For Themes |
| `revslider.zip` | Revolution Slider |
| `wp-job-board-pro.zip` | WP Job Board Pro |
| `wp-job-board-pro-wc-paid-listings.zip` | WP Job Board Pro - WooCommerce Paid Listings |
| `wp-private-message.zip` | WP Private Message |

After uploading each one, click **Activate**.

---

## 8. Import Demo Data

1. Go to **Appearance → Import Demo Data**
2. Hover over the demo template you want (e.g. Home 1) — an **Import Demo** button appears
3. Click **Import Demo**
4. On the next screen, skip the optional plugins (WPForms, All in One SEO, MonsterInsights) — they are not required
5. Click **Continue & Import**
6. Wait 5–10 minutes for the import to complete
7. Click **Visit Site** when done

---

## 9. Verify the Site

Open `http://localhost/workojas` in your browser. You should see the full Superio job board homepage with demo content — job listings, candidates, employers, navigation menus, and all sections loaded correctly.

---

## 10. Project Structure Summary

```
C:\xampp\htdocs\workojas\
  ├── wp-admin\                          WordPress admin files
  ├── wp-content\
  │     ├── themes\
  │     │     └── superio\              ← Your theme workspace (this project)
  │     ├── plugins\                    All installed plugins
  │     └── uploads\                   Media files
  ├── wp-includes\                      WordPress core
  └── wp-config.php                     Database config
```

---

## Notes

- Always keep **Apache** and **MySQL** running in XAMPP while working on the site
- The site is only accessible locally at `http://localhost/workojas`
- Admin panel is at `http://localhost/workojas/wp-admin`
- WordPress version used: **6.9.4**
- Elementor version used: **3.25.11** (pinned — do not update)
- Theme version: **Superio 1.3.14**

# Superio WordPress Theme - Setup Requirements

## 1. Server / Local Environment Requirements

You need a web server that can run WordPress. Pick ONE of these options for local development:

### Option A: XAMPP (Recommended for beginners on Windows)
- Download: https://www.apachefriends.org/
- Includes Apache, PHP, MySQL all in one package

### Option B: Local by Flywheel (Easiest, GUI-based)
- Download: https://localwp.com/
- One-click WordPress setup, no manual config needed

### Option C: WAMP (Windows only)
- Download: https://www.wampserver.com/

### Option D: Docker + WordPress image
- For advanced users comfortable with containers

---

## 2. Core Server Requirements

| Requirement              | Minimum         | Recommended      |
|--------------------------|-----------------|------------------|
| PHP                      | 5.6+            | 7.4 or 8.0+     |
| MySQL                    | 5.6+            | 5.7 or 8.0+     |
| memory_limit             | 64M             | 128M or higher   |
| upload_max_filesize      | 20M             | 64M              |
| post_max_size            | 20M             | 64M              |
| max_execution_time       | 300             | 300              |

---

## 3. Software to Install on Your Laptop

### Must Have
1. **A local server stack** (XAMPP / Local by Flywheel / WAMP) — see above
2. **WordPress** (latest version) — https://wordpress.org/download/
3. **A web browser** (Chrome / Firefox / Edge)
4. **A code editor** (VS Code, Kiro, or similar) — you already have this

### Optional but Useful
5. **Git** — for version control
6. **Node.js + npm** — only if you plan to compile SASS (this theme uses Compass/SASS)
7. **Ruby + Compass** — only if you want to recompile the `.scss` files (the theme has a `config.rb` for Compass)

---

## 4. WordPress Plugins Required by the Theme

After installing WordPress and activating the itSuperio theme,  will prTompt you to install these plugins. he ones marked **bundled** are included in `inc/plugins/`.

### Required Plugins
| Plugin                                      | Source                |
|---------------------------------------------|-----------------------|
| Apus Framework for Themes                   | Bundled (in theme)    |
| Elementor Page Builder                      | WordPress.org (free)  |
| Revolution Slider                           | Bundled (in theme)    |
| CMB2                                        | WordPress.org (free)  |
| MailChimp for WordPress                     | WordPress.org (free)  |
| Contact Form 7                              | WordPress.org (free)  |
| WooCommerce                                 | WordPress.org (free)  |
| WP Job Board Pro                            | Bundled (in theme)    |
| WP Job Board Pro - WC Paid Listings         | Bundled (in theme)    |
| WP Private Message                          | Bundled (in theme)    |

### Optional Plugins
| Plugin                                      | Source                |
|---------------------------------------------|-----------------------|
| One Click Demo Import                       | WordPress.org (free)  |
| SVG Support                                 | WordPress.org (free)  |

---

## 5. Quick Start Steps (after installing everything)

1. Install your local server (e.g., XAMPP) and start Apache + MySQL
2. Create a new MySQL database (e.g., `superio_db`)
3. Download and install WordPress into your server's web root
4. Run through the WordPress install wizard at `http://localhost/your-folder`
5. Go to **Appearance → Themes → Add New → Upload Theme**
6. Upload the `superio_theme.zip` file and activate it
7. The theme will prompt you to install required plugins — click **Begin Installing Plugins** and install + activate all of them
8. Go to **Appearance → Import Demo Data** and click Import to load the demo content
9. Go to **Settings → Reading** and set your static front page
10. Configure Google Maps API key at **Jobs → Settings → General** (if using maps)

---

## 6. PHP Configuration (if uploads fail)

Add to your `php.ini` or `.htaccess`:

```ini
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
memory_limit = 128M
```

---

## 7. For SASS/CSS Development Only

The theme uses Compass (Ruby-based SASS compiler). Only needed if you're editing `.scss` files:

```bash
gem install compass
compass watch
```

The `config.rb` in the project root configures the SASS compilation paths.

---

Source: [Superio Theme Documentation](https://apusthemes.com/guides/superio/) (content was rephrased for compliance with licensing restrictions)

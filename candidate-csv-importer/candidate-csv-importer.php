<?php
/**
 * Plugin Name: Candidate CSV Importer
 * Description: Import candidates from CSV/Excel file and auto-create their accounts.
 * Version: 1.1.0
 * Author: Workojas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Candidate_CSV_Importer {

    const API_SECRET = 'workojas_secret_2026'; // Change this to a strong secret

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_post_import_candidates_csv', array( $this, 'handle_import' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // Step 1: Intercept WP Job Board Pro's email key — return a dummy key
        // so WJBP tries to load a non-existent template and produces empty content.
        add_filter( 'wp_job_board_pro_email_key_candidate_user_register_auto_approve', array( $this, 'maybe_suppress_wjbp_email' ) );

        // Step 2: Intercept wp_mail itself — if WJBP tries to send an email with
        // empty body (result of our dummy key), cancel it completely.
        add_filter( 'wp_mail', array( $this, 'cancel_empty_wjbp_mail' ) );
    }

    // -------------------------------------------------------------------------
    // Email interception
    // -------------------------------------------------------------------------

    /**
     * When our plugin is creating a user, swap WJBP's email key with a
     * dummy value so it renders an empty body — which we then cancel in
     * cancel_empty_wjbp_mail() below.
     */
    public function maybe_suppress_wjbp_email( $email_key ) {
        global $workojas_plugin_created_user_id;

        if ( empty( $workojas_plugin_created_user_id ) ) {
            return $email_key; // Manual registration — don't touch
        }

        // Return a key that doesn't exist → WJBP renders empty content
        return 'workojas_suppressed';
    }

    /**
     * Cancel any wp_mail() call that has an empty or whitespace-only body
     * while our plugin flag is active. This stops WJBP's empty email from
     * being sent at all.
     *
     * @param  array $args  wp_mail arguments: to, subject, message, headers, attachments
     * @return array
     */
    public function cancel_empty_wjbp_mail( $args ) {
        global $workojas_plugin_created_user_id;

        if ( empty( $workojas_plugin_created_user_id ) ) {
            return $args; // Not our flow — don't interfere
        }

        if ( empty( trim( strip_tags( $args['message'] ) ) ) ) {
            // Empty body — cancel by setting recipient to empty
            // wp_mail() will silently fail with no recipient
            $args['to'] = '';
        }

        return $args;
    }

    /**
     * Send the branded bilingual WorkAjos welcome email with actual credentials.
     *
     * @param WP_User $user
     * @param string  $password  The plain-text password generated before wp_insert_user()
     */
    private function send_welcome_credentials_email( $user, $password ) {
        $name      = $user->display_name;
        $email     = $user->user_email;
        $login_url = 'https://www.workajos.com';
        $green     = '#8aba59';

        $subject = 'WorkAjos Account Ready: Login Details Inside | Cuenta lista: datos de acceso aquí';

        $message = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family: Arial, sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4; padding: 30px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">

          <!-- Header -->
          <tr>
            <td style="background-color:' . $green . '; padding: 30px 40px; text-align:center;">
              <h1 style="margin:0; color:#ffffff; font-size:26px; letter-spacing:1px;">WorkAjos</h1>
              <p style="margin:6px 0 0; color:#ffffff; font-size:14px; opacity:0.9;">www.workajos.com</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding: 36px 40px;">

              <p style="margin:0 0 16px; font-size:16px; color:#333333;">
                Hi / Hola <strong>' . esc_html( $name ) . '</strong>,
              </p>

              <p style="margin:0 0 24px; font-size:15px; color:#555555; line-height:1.6;">
                Your WorkAjos profile is now live! &nbsp;/&nbsp; ¡Tu perfil en WorkAjos ya está activo!
              </p>

              <!-- Credentials Table -->
              <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom:28px;">
                <tr>
                  <td style="padding:14px 18px; background-color:#f9f9f9; border:1px solid #dddddd; width:50%; font-size:14px; color:#555555; font-weight:bold;">
                    🔑 Username / Usuario
                  </td>
                  <td style="padding:14px 18px; background-color:#ffffff; border:1px solid #dddddd; font-size:14px; color:#333333; text-align:center;">
                    ' . esc_html( $email ) . '
                  </td>
                </tr>
                <tr>
                  <td style="padding:14px 18px; background-color:#f9f9f9; border:1px solid #dddddd; border-top:none; font-size:14px; color:#555555; font-weight:bold;">
                    🔒 Temporary Password<br>/ Contraseña temporal
                  </td>
                  <td style="padding:14px 18px; background-color:#ffffff; border:1px solid #dddddd; border-top:none; font-size:14px; color:#333333; text-align:center; font-family:monospace; letter-spacing:1px;">
                    ' . esc_html( $password ) . '
                  </td>
                </tr>
              </table>

              <!-- Login Button -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                <tr>
                  <td align="center">
                    <a href="' . esc_url( $login_url ) . '" style="display:inline-block; background-color:' . $green . '; color:#ffffff; text-decoration:none; padding:14px 36px; border-radius:5px; font-size:15px; font-weight:bold;">
                      👉 Login / Inicia sesión
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 12px; font-size:14px; color:#555555; line-height:1.7;">
                Your profile was created using AI to save you time. We recommend taking a quick look to personalise it and make sure everything reflects you accurately.
              </p>
              <p style="margin:0 0 24px; font-size:14px; color:#555555; line-height:1.7;">
                Tu perfil ha sido creado con inteligencia artificial para ahorrarte tiempo — te recomendamos revisarlo rápidamente para personalizarlo y asegurarte de que todo refleje bien tu experiencia.
              </p>

              <p style="margin:0 0 12px; font-size:14px; color:#555555; line-height:1.7;">
                The more complete your profile, the better your chances with employers!<br>
                ¡Cuanto más completo esté tu perfil, más oportunidades tendrás con los empleadores!
              </p>

              <p style="margin:0 0 24px; font-size:14px; color:#555555; line-height:1.7;">
                Any questions? We\'re here to help. / ¿Tienes alguna duda? Estamos aquí para ayudarte.
              </p>

              <p style="margin:0; font-size:15px; color:#333333; font-weight:bold;">
                Welcome aboard! / ¡Bienvenido/a!
              </p>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#f9f9f9; border-top:1px solid #eeeeee; padding:20px 40px; text-align:center;">
              <p style="margin:0; font-size:13px; color:#999999;">
                The WorkAjos Team &nbsp;|&nbsp;
                <a href="' . esc_url( $login_url ) . '" style="color:' . $green . '; text-decoration:none;">www.workajos.com</a>
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: WorkAjos <' . get_option( 'admin_email' ) . '>',
        );

        wp_mail( $email, $subject, $message, $headers );
    }

    /**
     * Parse a comma-separated languages string and return only valid options as an array.
     * Valid options: Spanish, English, French, Polish, Finnish, Dutch,
     *                Russian, Punjabi, Hindi, Urdu, Arabic
     * Meta key: _candidate_languages (Multi Select — stored as array)
     *
     * @param  string $languages_str  e.g. "English,Hindi" or "English, Hindi"
     * @return array                  e.g. ["English", "Hindi"]
     */
    private function parse_languages( $languages_str ) {
        $valid = array(
            'Spanish', 'English', 'French', 'Polish', 'Finnish',
            'Dutch', 'Russian', 'Punjabi', 'Hindi', 'Urdu', 'Arabic',
        );

        $input = array_map( 'trim', explode( ',', $languages_str ) );
        $result = array();

        foreach ( $input as $lang ) {
            // Case-insensitive match against valid list
            foreach ( $valid as $valid_lang ) {
                if ( strcasecmp( $lang, $valid_lang ) === 0 ) {
                    $result[] = $valid_lang;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Map a raw age number to the configured age range option.
     * Ranges: 18-20, 21-25, 26-30, 31-35, 36-40, 41-50, 51-60, 61 and above
     *
     * @param  int|string $age  Raw age number e.g. 30
     * @return string           Matching range string e.g. "26-30"
     */
    private function map_age_to_range( $age ) {
        $age = intval( $age );

        if ( $age >= 18 && $age <= 20 ) return '18-20';
        if ( $age >= 21 && $age <= 25 ) return '21-25';
        if ( $age >= 26 && $age <= 30 ) return '26-30';
        if ( $age >= 31 && $age <= 35 ) return '31-35';
        if ( $age >= 36 && $age <= 40 ) return '36-40';
        if ( $age >= 41 && $age <= 50 ) return '41-50';
        if ( $age >= 51 && $age <= 60 ) return '51-60';
        if ( $age >= 61 )               return '61 and above';

        return ''; // age below 18 or invalid — store nothing
    }

    /**
     * Map a raw experience number (years) to the configured dropdown option.
     * Options: No Experience, 1 Year, 2 Year, 3 Year, 4 Year, 5 Year,
     *          6 - 10 Year, 11 - 15 Year, 15 - 20 Year, 21 Year and above
     *
     * @param  int|string $years  Raw experience years e.g. 3
     * @return string             Matching option string e.g. "3 Year"
     */
    private function map_experience_to_option( $years ) {
        $years = intval( $years );

        if ( $years <= 0  ) return 'No Experience';
        if ( $years == 1  ) return '1 Year';
        if ( $years == 2  ) return '2 Year';
        if ( $years == 3  ) return '3 Year';
        if ( $years == 4  ) return '4 Year';
        if ( $years == 5  ) return '5 Year';
        if ( $years <= 10 ) return '6 - 10 Year';
        if ( $years <= 15 ) return '11 - 15 Year';
        if ( $years <= 20 ) return '15 - 20 Year';
        if ( $years >= 21 ) return '21 Year and above';

        return '';
    }

    public function register_rest_routes() {
        register_rest_route( 'workajos/v1', '/create-candidate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'api_create_candidate' ),
            'permission_callback' => array( $this, 'api_check_secret' ),
        ) );
    }

    public function api_check_secret( $request ) {
        $secret = $request->get_header( 'X-API-Secret' );
        if ( $secret !== self::API_SECRET ) {
            return new WP_Error( 'unauthorized', 'Invalid API secret', array( 'status' => 401 ) );
        }
        return true;
    }

    public function api_create_candidate( $request ) {
        $params = $request->get_json_params();

        $name  = sanitize_text_field( $params['full_name'] ?? $params['name'] ?? '' );
        $email = sanitize_email( $params['email'] ?? '' );

        if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Name and valid email are required.' ), 400 );
        }

        if ( email_exists( $email ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => "Email already exists: {$email}" ), 409 );
        }

        // Double-safety: check candidate post meta too
        $existing = get_posts( array(
            'post_type'      => 'candidate',
            'meta_key'       => '_candidate_email',
            'meta_value'     => $email,
            'posts_per_page' => 1,
        ) );
        if ( ! empty( $existing ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => "Candidate profile already exists for: {$email}" ), 409 );
        }

        $username = sanitize_user( strtolower( explode( '@', $email )[0] ) );
        if ( username_exists( $username ) ) {
            $username .= '_' . rand( 1000, 9999 );
        }

        $password   = wp_generate_password( 12 );
        $prefix     = '_candidate_';
        $name_parts = explode( ' ', $name, 2 );
        $first_name = $name_parts[0];
        $last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

        // --- Set flag BEFORE wp_insert_user() ---
        // user_register fires synchronously inside wp_insert_user(), so the flag
        // must be truthy before we call it — otherwise maybe_suppress_wjbp_email()
        // won't see it in time and WJBP will send its own welcome email.
        global $workojas_plugin_created_user_id;
        $workojas_plugin_created_user_id = true;

        $user_id = wp_insert_user( array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'role'         => 'wp_job_board_pro_candidate',
        ) );

        if ( is_wp_error( $user_id ) ) {
            $workojas_plugin_created_user_id = null; // clear flag on failure
            return new WP_REST_Response( array( 'success' => false, 'message' => $user_id->get_error_message() ), 500 );
        }

        // Update flag to real user_id (not strictly needed now, but good practice)
        $workojas_plugin_created_user_id = $user_id;

        // WJBP email was suppressed — send our own branded welcome email now
        $user = get_user_by( 'ID', $user_id );
        if ( $user ) {
            $this->send_welcome_credentials_email( $user, $password );
        }

        // Clear flag
        $workojas_plugin_created_user_id = null;

        // Build "About Candidate" content — comes from the 'description' field in the payload
        // (n8n maps Profile Summary EN + ES into this field with a blank line between them)
        $about_content = sanitize_textarea_field(
            $params['description'] ?? $params['about_yourself'] ?? ''
        );

        // Find the candidate post auto-created by WP Job Board Pro
        $candidate_id = get_user_meta( $user_id, 'candidate_id', true );

        // Edge case: WJBP didn't create one — create it ourselves
        if ( empty( $candidate_id ) ) {
            $candidate_id = wp_insert_post( array(
                'post_title'   => $name,
                'post_type'    => 'candidate',
                'post_content' => $about_content,
                'post_status'  => 'publish',
                'post_author'  => $user_id,
            ) );
            update_user_meta( $user_id, 'candidate_id', $candidate_id );
            update_post_meta( $candidate_id, $prefix . 'user_id',      $user_id );
            update_post_meta( $candidate_id, $prefix . 'email',        $email );
            update_post_meta( $candidate_id, $prefix . 'display_name', $name );
            update_post_meta( $candidate_id, $prefix . 'show_profile', 'show' );
        }

        wp_update_post( array(
            'ID'           => $candidate_id,
            'post_title'   => $name,
            'post_content' => $about_content,
        ) );

        // WJBP may overwrite post_content after wp_update_post via its own hooks.
        // Write directly to DB to guarantee our content is the final value.
        if ( ! empty( $about_content ) && ! empty( $candidate_id ) ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->posts,
                array( 'post_content' => $about_content ),
                array( 'ID'           => intval( $candidate_id ) ),
                array( '%s' ),
                array( '%d' )
            );
            clean_post_cache( $candidate_id );
        }

        update_user_meta( $user_id, 'user_account_status', 'approved' );

        // Optional standard fields
        if ( ! empty( $params['phone'] ) ) {
            update_post_meta( $candidate_id, $prefix . 'phone', sanitize_text_field( $params['phone'] ) );
        }
        // location_city or location
        $location = $params['location_city'] ?? $params['location'] ?? '';
        if ( ! empty( $location ) ) {
            update_post_meta( $candidate_id, $prefix . 'address', sanitize_text_field( $location ) );
        }
        // job_roles or job_title
        $job_title = $params['job_roles'] ?? $params['job_title'] ?? '';
        if ( ! empty( $job_title ) ) {
            update_post_meta( $candidate_id, $prefix . 'job_title', sanitize_text_field( $job_title ) );
        }
        // online_presence or website
        $website = $params['online_presence'] ?? $params['website'] ?? '';
        if ( ! empty( $website ) ) {
            update_post_meta( $candidate_id, $prefix . 'website', esc_url_raw( $website ) );
        }

        // Extra / custom fields
        $custom_prefix = '_candidate_cfield_';
        if ( ! empty( $params['age'] ) ) {
            $age_range = $this->map_age_to_range( $params['age'] );
            if ( $age_range ) {
                // Age uses WP Job Board Pro's standard prefix, not custom prefix
                update_post_meta( $candidate_id, '_candidate_age', $age_range );
            }
        }
        if ( ! empty( $params['experience_years'] ) ) {
            $exp_option = $this->map_experience_to_option( $params['experience_years'] );
            if ( $exp_option ) {
                update_post_meta( $candidate_id, '_candidate_experience_time', $exp_option );
            }
        }
        if ( ! empty( $params['languages'] ) ) {
            $languages = $this->parse_languages( $params['languages'] );
            if ( ! empty( $languages ) ) {
                // Multi Select — WP Job Board Pro stores as array
                update_post_meta( $candidate_id, '_candidate_languages', $languages );
            }
        }
        if ( ! empty( $params['contract_type'] ) ) {
            update_post_meta( $candidate_id, $custom_prefix . 'contract_type',    sanitize_text_field( $params['contract_type'] ) );
        }

        return new WP_REST_Response( array(
            'success'       => true,
            'message'       => "Candidate '{$name}' created successfully.",
            'candidate_id'  => $candidate_id,
            'user_id'       => $user_id,
            'username'      => $username,
            'about_content' => $about_content,
            'db_updated'    => ! empty( $about_content ) && ! empty( $candidate_id ),
        ), 201 );
    }

    // -------------------------------------------------------------------------
    // Admin UI — CSV bulk upload (admin-only, not used in production flow)
    // -------------------------------------------------------------------------

    public function add_admin_menu() {
        add_menu_page(
            'Import Candidates',
            'Import Candidates',
            'manage_options',
            'candidate-csv-importer',
            array( $this, 'render_admin_page' ),
            'dashicons-upload',
            30
        );
    }

    public function render_admin_page() {
        $results = get_transient( 'csv_import_results' );
        if ( $results ) {
            delete_transient( 'csv_import_results' );
        }
        ?>
        <div class="wrap">
            <h1>Import Candidates from CSV</h1>
            <p>Upload a CSV file with candidate details. The CSV should have these columns:</p>
            <table class="widefat" style="max-width: 600px; margin-bottom: 20px;">
                <thead>
                    <tr><th>Column</th><th>Required</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>name</code></td><td>Yes</td><td>Full name of the candidate</td></tr>
                    <tr><td><code>email</code></td><td>Yes</td><td>Email address (used for login)</td></tr>
                    <tr><td><code>phone</code></td><td>No</td><td>Phone number</td></tr>
                    <tr><td><code>location</code></td><td>No</td><td>City or address</td></tr>
                    <tr><td><code>job_title</code></td><td>No</td><td>Current/desired job title</td></tr>
                    <tr><td><code>description</code></td><td>No</td><td>About the candidate</td></tr>
                    <tr><td><code>category</code></td><td>No</td><td>Job category (e.g. Development)</td></tr>
                </tbody>
            </table>

            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'import_candidates_csv', 'csv_import_nonce' ); ?>
                <input type="hidden" name="action" value="import_candidates_csv" />
                <table class="form-table">
                    <tr>
                        <th><label for="csv_file">CSV File</label></th>
                        <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required /></td>
                    </tr>
                </table>
                <?php submit_button( 'Import Candidates' ); ?>
            </form>

            <?php if ( $results ) : ?>
                <div class="notice notice-<?php echo $results['errors'] ? 'warning' : 'success'; ?> is-dismissible">
                    <p><strong>Import Complete:</strong> <?php echo $results['created']; ?> created, <?php echo $results['skipped']; ?> skipped.</p>
                    <?php if ( ! empty( $results['messages'] ) ) : ?>
                        <ul style="list-style: disc; padding-left: 20px;">
                            <?php foreach ( $results['messages'] as $msg ) : ?>
                                <li><?php echo esc_html( $msg ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h3>Sample CSV Format</h3>
            <pre style="background: #f1f1f1; padding: 15px; max-width: 700px;">name,email,phone,location,job_title,description,category
John Doe,john@example.com,+1234567890,New York,Web Developer,Experienced developer,Development
Jane Smith,jane@example.com,+0987654321,London,Designer,Creative designer,Design</pre>
        </div>
        <?php
    }

    public function handle_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'import_candidates_csv', 'csv_import_nonce' );

        if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
            wp_redirect( admin_url( 'admin.php?page=candidate-csv-importer' ) );
            exit;
        }

        $file   = $_FILES['csv_file']['tmp_name'];
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            wp_die( 'Could not read the CSV file.' );
        }

        $headers = fgetcsv( $handle );
        if ( ! $headers ) {
            fclose( $handle );
            wp_die( 'CSV file is empty or invalid.' );
        }

        $headers = array_map( function( $h ) {
            return strtolower( trim( $h ) );
        }, $headers );

        if ( ! in_array( 'email', $headers ) || ! in_array( 'name', $headers ) ) {
            fclose( $handle );
            wp_die( 'CSV must have "name" and "email" columns.' );
        }

        $created  = 0;
        $skipped  = 0;
        $errors   = 0;
        $messages = array();
        $prefix   = '_candidate_';

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $data  = array_combine( $headers, $row );
            $name  = sanitize_text_field( $data['name']  ?? '' );
            $email = sanitize_email( $data['email'] ?? '' );

            if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
                $skipped++;
                $messages[] = "Skipped: invalid name or email — {$name} / {$email}";
                continue;
            }

            if ( email_exists( $email ) ) {
                $skipped++;
                $messages[] = "Skipped: email already exists — {$email}";
                continue;
            }

            $username = sanitize_user( strtolower( explode( '@', $email )[0] ) );
            if ( username_exists( $username ) ) {
                $username .= '_' . rand( 1000, 9999 );
            }

            $password   = wp_generate_password( 12 );
            $name_parts = explode( ' ', $name, 2 );
            $first_name = $name_parts[0];
            $last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

            // Set flag before wp_insert_user() — same pattern as REST API flow
            global $workojas_plugin_created_user_id;
            $workojas_plugin_created_user_id = true;

            $user_id = wp_insert_user( array(
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => $password,
                'display_name' => $name,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'role'         => 'wp_job_board_pro_candidate',
            ) );

            if ( is_wp_error( $user_id ) ) {
                $workojas_plugin_created_user_id = null;
                $errors++;
                $messages[] = "Error creating user {$email}: " . $user_id->get_error_message();
                continue;
            }

            $workojas_plugin_created_user_id = $user_id;

            // Send branded welcome email (WJBP email was suppressed by filter)
            $user = get_user_by( 'ID', $user_id );
            if ( $user ) {
                $this->send_welcome_credentials_email( $user, $password );
            }

            $workojas_plugin_created_user_id = null;

            $candidate_id = get_user_meta( $user_id, 'candidate_id', true );

            if ( empty( $candidate_id ) ) {
                $candidate_id = wp_insert_post( array(
                    'post_title'   => $name,
                    'post_type'    => 'candidate',
                    'post_content' => sanitize_textarea_field( $data['description'] ?? '' ),
                    'post_status'  => 'publish',
                    'post_author'  => $user_id,
                ) );

                if ( is_wp_error( $candidate_id ) ) {
                    $errors++;
                    $messages[] = "Error creating candidate profile for {$email}";
                    continue;
                }

                update_user_meta( $user_id, 'candidate_id', $candidate_id );
                update_post_meta( $candidate_id, $prefix . 'user_id',      $user_id );
                update_post_meta( $candidate_id, $prefix . 'email',        $email );
                update_post_meta( $candidate_id, $prefix . 'display_name', $name );
                update_post_meta( $candidate_id, $prefix . 'show_profile', 'show' );
            }

            wp_update_post( array(
                'ID'           => $candidate_id,
                'post_title'   => $name,
                'post_content' => sanitize_textarea_field( $data['description'] ?? '' ),
            ) );

            update_user_meta( $user_id, 'user_account_status', 'approved' );

            if ( ! empty( $data['phone'] ) ) {
                update_post_meta( $candidate_id, $prefix . 'phone',     sanitize_text_field( $data['phone'] ) );
            }
            if ( ! empty( $data['location'] ) ) {
                update_post_meta( $candidate_id, $prefix . 'address',   sanitize_text_field( $data['location'] ) );
            }
            if ( ! empty( $data['job_title'] ) ) {
                update_post_meta( $candidate_id, $prefix . 'job_title', sanitize_text_field( $data['job_title'] ) );
            }
            if ( ! empty( $data['category'] ) ) {
                $term = term_exists( $data['category'], 'candidate_category' );
                if ( $term ) {
                    wp_set_object_terms( $candidate_id, (int) $term['term_id'], 'candidate_category' );
                }
            }

            $created++;
            $messages[] = "Created: {$name} ({$email})";
        }

        fclose( $handle );

        set_transient( 'csv_import_results', array(
            'created'  => $created,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'messages' => $messages,
        ), 60 );

        wp_redirect( admin_url( 'admin.php?page=candidate-csv-importer' ) );
        exit;
    }
}

new Candidate_CSV_Importer();

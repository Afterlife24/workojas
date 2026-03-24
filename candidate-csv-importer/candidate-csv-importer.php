<?php
/**
 * Plugin Name: Candidate CSV Importer
 * Description: Import candidates from CSV/Excel file and auto-create their accounts.
 * Version: 1.0.0
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
    }

    public function register_rest_routes() {
        register_rest_route( 'workojas/v1', '/create-candidate', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'api_create_candidate' ),
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

        $name  = sanitize_text_field( $params['name'] ?? '' );
        $email = sanitize_email( $params['email'] ?? '' );

        if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Name and valid email are required.' ), 400 );
        }

        if ( email_exists( $email ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => "Email already exists: {$email}" ), 409 );
        }

        $username = sanitize_user( strtolower( explode( '@', $email )[0] ) );
        if ( username_exists( $username ) ) {
            $username .= '_' . rand( 1000, 9999 );
        }

        $password = wp_generate_password( 12 );
        $prefix   = '_candidate_';

        // Create WordPress user
        $user_id = wp_insert_user( array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'role'         => 'wp_job_board_pro_candidate',
        ) );

        if ( is_wp_error( $user_id ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => $user_id->get_error_message() ), 500 );
        }

        // Create candidate post
        $candidate_id = wp_insert_post( array(
            'post_title'   => $name,
            'post_type'    => 'candidate',
            'post_content' => sanitize_textarea_field( $params['description'] ?? '' ),
            'post_status'  => 'publish',
            'post_author'  => $user_id,
        ) );

        // Link user <-> candidate
        update_user_meta( $user_id, 'candidate_id', $candidate_id );
        update_post_meta( $candidate_id, $prefix . 'user_id', $user_id );
        update_post_meta( $candidate_id, $prefix . 'email', $email );
        update_post_meta( $candidate_id, $prefix . 'display_name', $name );
        update_post_meta( $candidate_id, $prefix . 'show_profile', 'show' );
        update_user_meta( $user_id, 'user_account_status', 'approved' );

        // Optional fields
        if ( ! empty( $params['phone'] ) ) {
            update_post_meta( $candidate_id, $prefix . 'phone', sanitize_text_field( $params['phone'] ) );
        }
        if ( ! empty( $params['location'] ) ) {
            update_post_meta( $candidate_id, $prefix . 'address', sanitize_text_field( $params['location'] ) );
        }
        if ( ! empty( $params['job_title'] ) ) {
            update_post_meta( $candidate_id, $prefix . 'job_title', sanitize_text_field( $params['job_title'] ) );
        }

        return new WP_REST_Response( array(
            'success'      => true,
            'message'      => "Candidate '{$name}' created successfully.",
            'candidate_id' => $candidate_id,
            'user_id'      => $user_id,
            'username'     => $username,
        ), 201 );
    }

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
                    <tr>
                        <th>Column</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
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
                    <tr>
                        <th><label for="default_password">Default Password</label></th>
                        <td>
                            <input type="text" name="default_password" id="default_password" value="" class="regular-text" />
                            <p class="description">Leave empty to auto-generate passwords. Candidates will receive an email to set their password.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="send_email">Send Welcome Email</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="send_email" id="send_email" value="1" checked />
                                Send login credentials to each candidate via email
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Import Candidates' ); ?>
            </form>

            <?php if ( $results ) : ?>
                <div class="notice notice-<?php echo $results['errors'] ? 'warning' : 'success'; ?> is-dismissible">
                    <p><strong>Import Complete:</strong> <?php echo $results['created']; ?> candidates created, <?php echo $results['skipped']; ?> skipped.</p>
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

        $file = $_FILES['csv_file']['tmp_name'];
        $default_password = ! empty( $_POST['default_password'] ) ? sanitize_text_field( $_POST['default_password'] ) : '';
        $send_email = ! empty( $_POST['send_email'] );

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            wp_die( 'Could not read the CSV file.' );
        }

        // Read header row
        $headers = fgetcsv( $handle );
        if ( ! $headers ) {
            fclose( $handle );
            wp_die( 'CSV file is empty or invalid.' );
        }

        // Normalize headers (trim whitespace, lowercase)
        $headers = array_map( function( $h ) {
            return strtolower( trim( $h ) );
        }, $headers );

        // Validate required columns
        if ( ! in_array( 'email', $headers ) || ! in_array( 'name', $headers ) ) {
            fclose( $handle );
            wp_die( 'CSV must have "name" and "email" columns.' );
        }

        $created  = 0;
        $skipped  = 0;
        $errors   = 0;
        $messages = array();
        $prefix   = '_candidate_'; // WP_JOB_BOARD_PRO_CANDIDATE_PREFIX

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $data = array_combine( $headers, $row );

            $name  = sanitize_text_field( $data['name'] ?? '' );
            $email = sanitize_email( $data['email'] ?? '' );

            if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
                $skipped++;
                $messages[] = "Skipped: invalid name or email — {$name} / {$email}";
                continue;
            }

            // Check if user already exists
            if ( email_exists( $email ) ) {
                $skipped++;
                $messages[] = "Skipped: email already exists — {$email}";
                continue;
            }

            // Generate username from email
            $username = sanitize_user( strtolower( explode( '@', $email )[0] ) );
            if ( username_exists( $username ) ) {
                $username .= '_' . rand( 1000, 9999 );
            }

            // Set password
            $password = ! empty( $default_password ) ? $default_password : wp_generate_password( 12 );

            // Step 1: Create WordPress user
            $user_id = wp_insert_user( array(
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => $password,
                'display_name' => $name,
                'role'         => 'wp_job_board_pro_candidate',
            ) );

            if ( is_wp_error( $user_id ) ) {
                $errors++;
                $messages[] = "Error creating user {$email}: " . $user_id->get_error_message();
                continue;
            }

            // Step 2: Create candidate post (profile)
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

            // Step 3: Link user <-> candidate
            update_user_meta( $user_id, 'candidate_id', $candidate_id );
            update_post_meta( $candidate_id, $prefix . 'user_id', $user_id );
            update_post_meta( $candidate_id, $prefix . 'email', $email );
            update_post_meta( $candidate_id, $prefix . 'display_name', $name );
            update_post_meta( $candidate_id, $prefix . 'show_profile', 'show' );
            update_user_meta( $user_id, 'user_account_status', 'approved' );

            // Step 4: Save optional fields
            if ( ! empty( $data['phone'] ) ) {
                update_post_meta( $candidate_id, $prefix . 'phone', sanitize_text_field( $data['phone'] ) );
            }
            if ( ! empty( $data['location'] ) ) {
                update_post_meta( $candidate_id, $prefix . 'address', sanitize_text_field( $data['location'] ) );
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

            // Step 5: Send welcome email (optional)
            if ( $send_email ) {
                $this->send_welcome_email( $email, $name, $username, $password );
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

    private function send_welcome_email( $email, $name, $username, $password ) {
        $site_name = get_bloginfo( 'name' );
        $login_url = wp_login_url();

        $subject = sprintf( 'Your account on %s is ready', $site_name );
        $message = sprintf(
            "Hi %s,\n\nYour account has been created on %s.\n\nUsername: %s\nPassword: %s\nLogin here: %s\n\nPlease change your password after logging in.\n\nBest regards,\n%s Team",
            $name,
            $site_name,
            $username,
            $password,
            $login_url,
            $site_name
        );

        wp_mail( $email, $subject, $message );
    }
}

new Candidate_CSV_Importer();

<?php
/**
 * Plugin Name: GF Advanced Feed Migrator
 * Description: Export and import a single Gravity Forms feed (e.g., Webhook feed) with dropdowns & enhanced error checking.
 * Author: D Kandekore
 * Version: 1.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GF_Advanced_Feed_Migrator {
    public function __construct() {
        // Hook into admin menu
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
    }

    /**
     * Add an item under "Tools" for our feed migrator.
     */
    public function register_admin_page() {
        add_management_page(
            'GF Advanced Feed Migrator',       // Page title
            'GF Feed Migrator',                // Menu title
            'manage_options',                  // Capability required
            'gf-advanced-feed-migrator',       // Menu slug
            array( $this, 'admin_page_html' )  // Callback
        );
    }

    /**
     * The HTML for our admin page.
     */
    public function admin_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // We need Gravity Forms active
        if ( ! class_exists( 'GFAPI' ) ) {
            echo '<div class="error"><p>Please install and activate Gravity Forms.</p></div>';
            return;
        }

        // Handle export form submission
        if ( isset( $_POST['gf_feed_migrator_export_submit'] ) ) {
            $this->handle_export();
        }

        // Handle import form submission
        if ( isset( $_POST['gf_feed_migrator_import_submit'] ) ) {
            $this->handle_import();
        }

        // Retrieve all forms for the dropdown
        $all_forms = GFAPI::get_forms();
        if ( is_wp_error( $all_forms ) ) {
            $all_forms = array();
        }

        // Check if user wants to filter by Webhook-only feeds
        $show_only_webhooks = isset( $_GET['gf_only_webhooks'] ) ? (bool) $_GET['gf_only_webhooks'] : false;

        // Determine which form is currently selected for the Export section
        $selected_form_id = isset( $_GET['gf_export_form_id'] ) ? absint( $_GET['gf_export_form_id'] ) : 0;

        // Get feeds for the selected form
        $feeds_for_form = array();
        if ( $selected_form_id ) {
            $feeds_for_form = GFAPI::get_feeds( $selected_form_id );
            if ( is_wp_error( $feeds_for_form ) ) {
                $feeds_for_form = array();
            }

            // Optionally filter only Webhook feeds
            if ( $show_only_webhooks ) {
                $feeds_for_form = array_filter( $feeds_for_form, function( $feed ) {
                    return isset( $feed['addon_slug'] ) && $feed['addon_slug'] === 'gravityformswebhooks';
                } );
            }
        }

        // Build query args for toggling Webhook-only mode
        $toggle_webhook_query_args = array(
            'page' => 'gf-advanced-feed-migrator',
            'gf_only_webhooks' => $show_only_webhooks ? '0' : '1',
        );
        if ( $selected_form_id ) {
            $toggle_webhook_query_args['gf_export_form_id'] = $selected_form_id;
        }

        ?>
        <div class="wrap">
            <h1>Gravity Forms - Advanced Feed Migrator</h1>
            <p>This tool lets you export and import a single Gravity Forms feed—such as a Webhooks feed—using dropdown selections and extra validation.</p>

            <hr />

            <!-- Export a Single Feed -->
            <h2>Export a Single Feed</h2>
            <form method="get" style="margin-bottom: 1rem;">
                <!-- We use GET so that changing the Form dropdown reloads the page showing feeds. -->
                <input type="hidden" name="page" value="gf-advanced-feed-migrator" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="gf_export_form_id">Select Form:</label></th>
                        <td>
                            <select name="gf_export_form_id" id="gf_export_form_id" onchange="this.form.submit()">
                                <option value="">-- Select Form --</option>
                                <?php foreach ( $all_forms as $form ) : ?>
                                    <option value="<?php echo esc_attr( $form['id'] ); ?>"
                                        <?php selected( $selected_form_id, $form['id'] ); ?>>
                                        <?php echo esc_html( $form['title'] . ' (ID: ' . $form['id'] . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Pick the form to see its available feeds.</p>
                        </td>
                    </tr>
                </table>
            </form>

            <?php if ( $selected_form_id ) : ?>
                <p>
                    <a class="button" href="<?php echo esc_url( add_query_arg( $toggle_webhook_query_args, admin_url( 'tools.php' ) ) ); ?>">
                        <?php echo $show_only_webhooks ? 'Show All Feeds' : 'Show Only Webhook Feeds'; ?>
                    </a>
                </p>

                <?php if ( empty( $feeds_for_form ) ) : ?>
                    <div class="notice notice-warning">
                        <p>No feeds found for Form ID <?php echo esc_html( $selected_form_id ); ?>.</p>
                    </div>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field( 'gf_feed_migrator_export', 'gf_feed_migrator_export_nonce' ); ?>
                        <input type="hidden" name="export_form_id" value="<?php echo esc_attr( $selected_form_id ); ?>" />

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="export_feed_id">Select Feed:</label></th>
                                <td>
                                    <select name="export_feed_id" id="export_feed_id" required>
                                        <option value="">-- Select Feed --</option>
                                        <?php foreach ( $feeds_for_form as $feed ) : ?>
                                            <option value="<?php echo esc_attr( $feed['id'] ); ?>">
                                                <?php
                                                    // For display: show feed ID and possibly its add-on slug.
                                                    $feed_type   = isset( $feed['addon_slug'] ) ? $feed['addon_slug'] : 'Unknown';
                                                    $feed_name   = isset( $feed['meta']['feedName'] ) ? $feed['meta']['feedName'] : 'Untitled';
                                                    echo esc_html( $feed_name . ' (ID: ' . $feed['id'] . ', ' . $feed_type . ')' );
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" name="gf_feed_migrator_export_submit" class="button button-primary">Export Feed</button>
                        </p>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <hr />

            <!-- Import a Single Feed -->
            <h2>Import a Single Feed</h2>
            <?php
                // For the import form, let’s also have a dropdown of all forms for the "destination" form.
            ?>
            <form method="post">
                <?php wp_nonce_field( 'gf_feed_migrator_import', 'gf_feed_migrator_import_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="import_form_id">Destination Form:</label></th>
                        <td>
                            <select name="import_form_id" id="import_form_id" required>
                                <option value="">-- Select Form --</option>
                                <?php foreach ( $all_forms as $form ) : ?>
                                    <option value="<?php echo esc_attr( $form['id'] ); ?>">
                                        <?php echo esc_html( $form['title'] . ' (ID: ' . $form['id'] . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the form where the feed should be imported.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="feed_json">Feed JSON:</label></th>
                        <td>
                            <textarea name="feed_json" id="feed_json" rows="8" cols="80" required></textarea>
                            <p class="description">
                                Paste the JSON that was exported from the "Export Feed" step.
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="gf_feed_migrator_import_submit" class="button button-primary">Import Feed</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle Export Form submission
     */
    private function handle_export() {
        // Verify nonce
        check_admin_referer( 'gf_feed_migrator_export', 'gf_feed_migrator_export_nonce' );

        $form_id = absint( $_POST['export_form_id'] );
        $feed_id = absint( $_POST['export_feed_id'] );

        // Retrieve the specified feed
        $feed = GFAPI::get_feed( $feed_id );
        if ( is_wp_error( $feed ) || empty( $feed ) ) {
            $this->render_admin_notice( 'error', 'Could not retrieve feed with ID ' . esc_html( $feed_id ) . '.' );
            return;
        }

        // Verify that the feed belongs to the specified form
        if ( (int) $feed['form_id'] !== $form_id ) {
            $this->render_admin_notice(
                'error',
                'The selected feed (ID ' . esc_html( $feed_id ) . ') does not belong to form ' . esc_html( $form_id ) . '.'
            );
            return;
        }

        // (Optional) Verify it is a Webhook feed if desired:
        // if ( isset( $feed['addon_slug'] ) && $feed['addon_slug'] !== 'gravityformswebhooks' ) {
        //     $this->render_admin_notice( 'error', 'The selected feed is not a Webhooks feed.' );
        //     return;
        // }

        // Convert feed to JSON
        $json_feed = wp_json_encode( $feed );

        // Force a download of the JSON file
        $filename = 'gf_feed_' . $feed_id . '_form_' . $form_id . '.json';
        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
        echo $json_feed;
        exit;
    }

    /**
     * Handle Import Form submission
     */
    private function handle_import() {
        // Verify nonce
        check_admin_referer( 'gf_feed_migrator_import', 'gf_feed_migrator_import_nonce' );

        $import_form_id = absint( $_POST['import_form_id'] );
        $json_feed      = stripslashes( $_POST['feed_json'] ); // in case slashes were added

        // Decode the JSON
        $feed = json_decode( $json_feed, true );
        if ( empty( $feed ) || ! is_array( $feed ) ) {
            $this->render_admin_notice( 'error', 'Invalid JSON. Could not decode the feed data.' );
            return;
        }

        // Remove old feed ID so GF creates a new feed
        if ( isset( $feed['id'] ) ) {
            unset( $feed['id'] );
        }

        // Set the form ID to the new form
        $feed['form_id'] = $import_form_id;

        // Optionally ensure feed is active
        $feed['is_active'] = true;

        // (Optional) verify that the feed is a Webhooks feed:
        // if ( isset( $feed['addon_slug'] ) && $feed['addon_slug'] !== 'gravityformswebhooks' ) {
        //     $this->render_admin_notice( 'error', 'You are attempting to import a feed that is not a Webhooks feed.' );
        //     return;
        // }

        // Insert feed
        $new_feed_id = GFAPI::add_feed( $feed );

        if ( is_wp_error( $new_feed_id ) ) {
            $this->render_admin_notice( 'error', 'Error importing feed: ' . esc_html( $new_feed_id->get_error_message() ) );
        } else {
            $this->render_admin_notice( 'updated', 'Successfully imported feed into Form ID ' . esc_html( $import_form_id ) . '. New feed ID: ' . esc_html( $new_feed_id ) );
        }
    }

    /**
     * Helper to render an admin notice (WP 5.x style).
     */
    private function render_admin_notice( $class, $message ) {
        ?>
        <div class="<?php echo esc_attr( $class ); ?> notice is-dismissible">
            <p><?php echo wp_kses_post( $message ); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
new GF_Advanced_Feed_Migrator();

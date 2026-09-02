<?php
/**
 * Dhali Plugin Updater.
 *
 * Makes a GitHub-hosted plugin participate in WordPress's native update
 * system — the same "Update available" notice, the same Update Now
 * button, the same WP Toolkit/WHM auto-update flow a WordPress.org
 * plugin gets — without depending on a third-party library or standing
 * up any server of our own.
 *
 * How it works, end to end:
 *
 *   1. The plugin's own header declares an "Update URI:" pointing at its
 *      GitHub repo. WordPress core (5.8+) sees that header and fires a
 *      filter named `update_plugins_{hostname}` — for us, always
 *      `update_plugins_github.com` — whenever it runs its periodic
 *      update check.
 *   2. This class hooks that filter, but only acts when the specific
 *      plugin file being checked is the one it was initialized for
 *      (multiple Dhali plugins share the same github.com hostname, so
 *      the same filter fires once per plugin — each instance ignores
 *      every check that isn't its own).
 *   3. It fetches the plugin's own main file straight from GitHub's raw
 *      content for the configured branch, and reads that file's own
 *      Version: header — the exact same header WordPress already reads
 *      locally. No separate metadata file, no build step.
 *   4. If the remote version is newer, it hands WordPress a standard
 *      update object pointing at GitHub's own branch-zip download.
 *      WordPress's built-in upgrader does the actual download, unzip,
 *      and file replacement — this class only ever answers the
 *      question "is there something newer, and where is it".
 *
 * Usage — add near the top of the plugin's main file, after the
 * ABSPATH guard:
 *
 *   require_once __DIR__ . '/includes/class-dhali-plugin-updater.php';
 *   Dhali_Plugin_Updater::init( __FILE__, 'dhali-breadcrumbs', 'master' );
 *
 * And add this header field alongside the plugin's existing ones:
 *
 *   * Update URI: https://github.com/dhali-web/Dhali-Breadcrumbs
 *
 * The repo owner/name is read from that header automatically — nothing
 * about the org name is hardcoded in this class, so the same file can be
 * copied unmodified into every Dhali plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This exact file is copied into every Dhali plugin that needs update
// checking, and several of those plugins run active on the same site at
// once. require_once only prevents re-including the same file *path*
// twice — it has no way to know two different files at two different
// paths declare the same class name. Without this guard, whichever
// plugin's copy loads first "wins" and every other plugin's copy fatals
// trying to redeclare the class. Wrapping the whole declaration means
// only the first copy loaded actually defines it; every other plugin's
// copy silently no-ops and just uses that same shared class.
if ( ! class_exists( 'Dhali_Plugin_Updater' ) ) :

class Dhali_Plugin_Updater {

	/** @var string Absolute path to the plugin's main file. */
	private $plugin_file;

	/** @var string "folder/main-file.php", as WordPress identifies the plugin internally. */
	private $plugin_basename;

	/** @var string The plugin's slug, e.g. "dhali-breadcrumbs". */
	private $slug;

	/** @var string Branch to check for updates on, e.g. "main" or "master". */
	private $branch;

	/** @var string The raw "Update URI:" header value. */
	private $update_uri;

	/** @var string "owner/repo", parsed from the Update URI. */
	private $repo;

	/**
	 * @param string $plugin_file Always pass __FILE__ from the plugin's main file.
	 * @param string $slug        The plugin's slug (its folder name in wp-content/plugins).
	 * @param string $branch      Branch to check for updates on. Defaults to "main" —
	 *                            pass "master" explicitly for repos still on that default.
	 */
	public static function init( $plugin_file, $slug, $branch = 'main' ) {
		new self( $plugin_file, $slug, $branch );
	}

	private function __construct( $plugin_file, $slug, $branch ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->slug            = $slug;
		$this->branch          = $branch;

		$headers          = get_file_data( $plugin_file, array( 'UpdateURI' => 'Update URI' ) );
		$this->update_uri = $headers['UpdateURI'];

		if ( empty( $this->update_uri ) ) {
			return; // Nothing to hook without an Update URI header.
		}

		$hostname = wp_parse_url( $this->update_uri, PHP_URL_HOST );

		if ( empty( $hostname ) ) {
			return;
		}

		$path       = trim( (string) wp_parse_url( $this->update_uri, PHP_URL_PATH ), '/' );
		$this->repo = $path;

		if ( empty( $this->repo ) ) {
			return;
		}

		add_filter( "update_plugins_{$hostname}", array( $this, 'check_for_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
	}

	/**
	 * WordPress core calls this during its periodic update check.
	 *
	 * @param array|false $update      Existing update data from an earlier filter, or false.
	 * @param array       $plugin_data Parsed header data for the plugin being checked.
	 * @param string      $plugin_file "folder/main-file.php" for the plugin being checked.
	 * @return array|false
	 */
	public function check_for_update( $update, $plugin_data, $plugin_file ) {
		// This filter fires once per plugin sharing this hostname — only
		// respond to the one this instance was set up for.
		if ( $plugin_file !== $this->plugin_basename ) {
			return $update;
		}

		$remote_version = $this->get_remote_version();

		if ( is_wp_error( $remote_version ) || empty( $remote_version ) ) {
			return $update;
		}

		$installed_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0';

		if ( ! version_compare( $remote_version, $installed_version, '>' ) ) {
			return $update; // Already current.
		}

		return array(
			'slug'    => $this->slug,
			'version' => $remote_version,
			'url'     => $this->update_uri,
			'package' => $this->get_download_url(),
		);
	}

	/**
	 * Fetch the plugin's own main file from GitHub and read its Version:
	 * header — the exact same header WordPress reads locally, just at
	 * the tip of the configured branch instead of what's installed.
	 *
	 * Cached for an hour so a burst of admin page loads doesn't turn
	 * into a burst of GitHub requests.
	 *
	 * @return string|WP_Error
	 */
	private function get_remote_version() {
		$cache_key = 'dhali_update_version_' . $this->slug;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$main_file_name = basename( $this->plugin_file );
		$raw_url        = sprintf(
			'https://raw.githubusercontent.com/%s/%s/%s',
			$this->repo,
			$this->branch,
			$main_file_name
		);

		$response = wp_remote_get( $raw_url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'dhali_update_fetch_failed',
				sprintf(
					/* translators: %s: HTTP response code */
					__( 'Unexpected response (%s) fetching update info.', 'dhali' ),
					wp_remote_retrieve_response_code( $response )
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $body, $matches ) ) {
			return new WP_Error( 'dhali_update_no_version_header', __( 'Could not find a Version header in the remote file.', 'dhali' ) );
		}

		$version = trim( $matches[1] );

		// Cache both successful and failed lookups (empty string signals
		// "checked, nothing found") so a bad response doesn't retry on
		// every single page load until the transient's own timeout.
		set_transient( $cache_key, $version, HOUR_IN_SECONDS );

		return $version;
	}

	/**
	 * The zip WordPress's own upgrader downloads when the update is
	 * actually applied. GitHub's branch-zip endpoint produces a single
	 * top-level folder inside the zip; WordPress's built-in upgrader
	 * already handles that shape correctly when replacing an existing
	 * plugin, so no extra unpacking logic is needed here.
	 *
	 * @return string
	 */
	private function get_download_url() {
		return sprintf(
			'https://github.com/%s/archive/refs/heads/%s.zip',
			$this->repo,
			$this->branch
		);
	}

	/**
	 * Minimal support for the "View version X.X.X details" popup
	 * WordPress shows from the Plugins screen. Optional — omitting this
	 * filter entirely still gives you a working Update Now button, just
	 * without the popup.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$remote_version = $this->get_remote_version();

		if ( is_wp_error( $remote_version ) || empty( $remote_version ) ) {
			return $result;
		}

		return (object) array(
			'name'          => $this->slug,
			'slug'          => $this->slug,
			'version'       => $remote_version,
			'author'        => 'Dhali',
			'homepage'      => $this->update_uri,
			'download_link' => $this->get_download_url(),
			'sections'      => array(
				'description' => sprintf(
					/* translators: %s: GitHub repo URL */
					__( 'See %s for source and changelog.', 'dhali' ),
					$this->update_uri
				),
			),
		);
	}
}

endif; // class_exists( 'Dhali_Plugin_Updater' ).

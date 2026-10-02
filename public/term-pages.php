<?php
/**
 * Plugin Name:       Term Pages
 * Plugin URI:        https://wordpress.org/plugins/term-pages/
 * Description:       Redirects the first page of a term archive to a page of your choice.
 * Version:           2.0.0
 * Requires at least: 6.2
 * Tested up to:      7.1.2
 * Requires PHP:      7.4
 * Author:            Palasthotel <webmaster@palasthotel.de>
 * Author URI:        https://palasthotel.de
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       term-pages
 * Domain Path:       /languages
 *
 * @copyright Copyright (c) 2019, Palasthotel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Connects taxonomy terms to a page and redirects the term archive there.
 */
class Term_Pages {

	/**
	 * Term meta key holding the page ID. Unchanged since 1.0 for compatibility.
	 */
	const META_KEY = 'or-page-id';

	/**
	 * Name of the form field submitted with the term.
	 */
	const FIELD_NAME = 'or-page-id';

	/**
	 * admin-ajax action of the page search.
	 */
	const AJAX_ACTION = 'tp_lookup';

	/**
	 * Nonce action guarding the page search.
	 */
	const NONCE_ACTION = 'term-pages-lookup';

	/**
	 * Maximum number of autocomplete suggestions.
	 */
	const MAX_RESULTS = 20;

	/**
	 * Register the hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_lookup_pages' ) );
		add_action( 'template_redirect', array( $this, 'redirect_term_archive' ) );
	}

	/**
	 * Load translations and add the page field to every taxonomy with a UI.
	 */
	public function init() {
		load_plugin_textdomain(
			'term-pages',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		foreach ( get_taxonomies( array( 'show_ui' => true ) ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( $this, 'render_add_field' ) );
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_edit_field' ), 10, 2 );
			add_action( 'created_' . $taxonomy, array( $this, 'save_field' ), 10, 2 );
			add_action( 'edited_' . $taxonomy, array( $this, 'save_field' ), 10, 2 );
		}
	}

	/* ---------------------------------------------------------------------
	 * Admin UI
	 * ------------------------------------------------------------------ */

	/**
	 * Load the autocomplete only on the term screens that show the field.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'term-pages-admin',
			plugins_url( 'admin.js', __FILE__ ),
			array( 'jquery-ui-autocomplete' ),
			$this->version(),
			true
		);

		wp_localize_script(
			'term-pages-admin',
			'termPagesAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'minChars' => 2,
			)
		);
	}

	/**
	 * Render the field on the "add new term" form.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function render_add_field( $taxonomy ) {
		?>
		<div class="form-field term-pages-field">
			<label for="term-pages-page"><?php esc_html_e( 'Overriding page', 'term-pages' ); ?></label>
			<?php $this->render_input( 0, $taxonomy ); ?>
		</div>
		<?php
	}

	/**
	 * Render the field on the "edit term" form.
	 *
	 * @param WP_Term $term     Term being edited.
	 * @param string  $taxonomy Taxonomy slug.
	 */
	public function render_edit_field( $term, $taxonomy ) {
		$page_id = $term instanceof WP_Term ? $this->get_page_id( $term->term_id ) : 0;
		?>
		<tr class="form-field term-pages-field">
			<th scope="row">
				<label for="term-pages-page"><?php esc_html_e( 'Overriding page', 'term-pages' ); ?></label>
			</th>
			<td>
				<?php $this->render_input( $page_id, $taxonomy ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the autocomplete input plus the hidden field carrying the page ID.
	 *
	 * @param int    $page_id  Currently selected page, 0 for none.
	 * @param string $taxonomy Taxonomy slug.
	 */
	private function render_input( $page_id, $taxonomy ) {
		$page_id = (int) $page_id;
		$title   = $page_id > 0 ? get_the_title( $page_id ) : '';
		?>
		<input
			type="text"
			id="term-pages-page"
			class="term-pages-page-search regular-text"
			value="<?php echo esc_attr( $title ); ?>"
			placeholder="<?php echo esc_attr__( 'Search for a published page', 'term-pages' ); ?>"
			data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
			autocomplete="off"
		>
		<input
			type="hidden"
			class="term-pages-page-id"
			name="<?php echo esc_attr( self::FIELD_NAME ); ?>"
			value="<?php echo esc_attr( $page_id > 0 ? (string) $page_id : '' ); ?>"
		>
		<p class="description">
			<?php esc_html_e( 'Start typing and pick a page from the list. Visitors of this term archive are then redirected to that page. Clear the field to remove the redirect.', 'term-pages' ); ?>
		</p>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Page search
	 * ------------------------------------------------------------------ */

	/**
	 * Return published pages matching the search term as autocomplete items.
	 *
	 * Requires a valid nonce and the capability to edit terms of the taxonomy
	 * the field was rendered for. There is deliberately no nopriv variant.
	 */
	public function ajax_lookup_pages() {
		check_ajax_referer( self::NONCE_ACTION );

		$taxonomy_name = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : '';
		$taxonomy      = $taxonomy_name ? get_taxonomy( $taxonomy_name ) : false;

		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to edit terms of this taxonomy.', 'term-pages' ) ),
				403
			);
		}

		$search = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '';

		if ( mb_strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				's'                      => $search,
				'sentence'               => true,
				'search_columns'         => array( 'post_title' ),
				'posts_per_page'         => self::MAX_RESULTS,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array();

		foreach ( $query->posts as $page ) {
			$title = get_the_title( $page );

			$items[] = array(
				'id'    => (int) $page->ID,
				/* translators: 1: page title, 2: page ID */
				'label' => sprintf( __( '%1$s (#%2$d)', 'term-pages' ), $title, (int) $page->ID ),
				'value' => $title,
			);
		}

		wp_send_json_success( $items );
	}

	/* ---------------------------------------------------------------------
	 * Saving
	 * ------------------------------------------------------------------ */

	/**
	 * Store the selected page for a created or edited term.
	 *
	 * Runs on created_{$taxonomy} and edited_{$taxonomy}, that is after
	 * WordPress verified its own nonce and the capability to manage the
	 * taxonomy.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id   Term taxonomy ID.
	 */
	public function save_field( $term_id, $tt_id ) {
		unset( $tt_id );

		if ( ! isset( $_POST[ self::FIELD_NAME ] ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$page_id = $this->parse_page_id( wp_unslash( $_POST[ self::FIELD_NAME ] ) );

		if ( $page_id > 0 ) {
			update_term_meta( $term_id, self::META_KEY, $page_id );
		} else {
			delete_term_meta( $term_id, self::META_KEY );
		}
	}

	/**
	 * Turn a submitted value into a valid page ID, or 0.
	 *
	 * Accepts a plain ID as sent by the current field, and the legacy
	 * "Page title : 123" format of earlier versions.
	 *
	 * @param mixed $value Raw, unslashed field value.
	 * @return int Page ID or 0.
	 */
	private function parse_page_id( $value ) {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}

		$value = trim( (string) $value );

		if ( '' === $value || ! preg_match( '/(?:^|\s:\s)(\d+)\s*$/', $value, $matches ) ) {
			return 0;
		}

		$page = get_post( (int) $matches[1] );

		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
			return 0;
		}

		return (int) $page->ID;
	}

	/* ---------------------------------------------------------------------
	 * Frontend redirect
	 * ------------------------------------------------------------------ */

	/**
	 * Redirect the unpaged term archive to the connected page.
	 */
	public function redirect_term_archive() {
		if ( is_paged() || is_feed() || is_embed() || is_robots() ) {
			return;
		}

		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return;
		}

		$term = get_queried_object();

		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$page_id = $this->get_page_id( $term->term_id );

		if ( $page_id < 1 ) {
			return;
		}

		$page = get_post( $page_id );

		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return;
		}

		$url = get_permalink( $page );

		if ( ! $url ) {
			return;
		}

		wp_safe_redirect( $url, 301 );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Page connected to a term.
	 *
	 * @param int $term_id Term ID.
	 * @return int Page ID or 0.
	 */
	private function get_page_id( $term_id ) {
		return (int) get_term_meta( (int) $term_id, self::META_KEY, true );
	}

	/**
	 * Plugin version, read from the plugin header so it is defined only once.
	 *
	 * @return string
	 */
	private function version() {
		static $version = null;

		if ( null === $version ) {
			$data    = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
			$version = empty( $data['Version'] ) ? '0' : $data['Version'];
		}

		return $version;
	}
}

new Term_Pages();

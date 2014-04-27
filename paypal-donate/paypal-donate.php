<?php
/**
 * @package jonls.dk-Paypal-Donate
 * @author Jon Lund Steffensen
 * @version 0.3
 */
/*
  Plugin Name: jonls.dk-Paypal-Donate
  Plugin URI: http://jonls.dk/
  Description: Shortcode for inserting a paypal donate button
  Author: Jon Lund Steffensen
  Version: 0.3
  Author URI: http://jonls.dk/
*/


class Paypal_Button {

	protected $db_version = '1';
	protected $table_name = null;

	protected $widgets = array();

	protected $options_page = null;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'paypal_donate_ipn';

		/* User visible section */
		if ( ! is_admin() ) {
			add_action( 'init' , array( $this, 'load_scripts_init_cb' ) );
			add_shortcode( 'paypal-donate' , array( $this, 'shortcode_handler' ) );

			add_action( 'template_redirect' , array( $this, 'generate_widget' ) );
			add_action( 'template_redirect' , array( $this, 'paypal_ipn_callback' ) );
		}

		/* Admin section */
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}

		register_activation_hook( __FILE__ , array( $this, 'plugin_install' ) );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_action_links') );

		$this->widgets = get_option( 'paypal_donate_widgets', array() );
	}

	public function load_scripts_init_cb() {
		global $wp;
		$wp->add_query_var( 'paypal_donate_widget' );
		$wp->add_query_var( 'paypal_donate_ipn_cb' );
	}


	/* Shortcode handler for "paypal-donate" */
	public function shortcode_handler( $atts ) {
		$widget_id = $atts['id'];

		if ( ! isset( $this->widgets[ $widget_id ] ) ) {
			return '<!-- paypal-donate shortcode: unknown id -->';
		}

		return '<iframe src="' . site_url() . '/?paypal_donate_widget=' . urlencode( $widget_id ) . '"' .
			' width="250" height="22" frameborder="0" scrolling="no" title="PayPal Donate"' .
			' border="0" marginheight="0" marginwidth="0" allowtransparency="true"></iframe>';
	}


	/* Generate widget */
	public function generate_widget() {
		global $wpdb;

		/* Generate widget when flag is set */
		if ( ! get_query_var( 'paypal_donate_widget' ) ) return;

		$widget_id = get_query_var( 'paypal_donate_widget' );

		if ( ! isset( $this->widgets[ $widget_id ] ) ) {
			status_header( 404 );
			exit;
		}

		$widget   = $this->widgets[ $widget_id ];
		$code     = $widget['code'];
		$info     = $widget['info'];
		$name     = $widget['name'];
		$currency = $widget['currency'];

		echo '<!doctype html>' .
			'<html><head>' .
			'<meta charset="utf-8"/>' .
			'<title>PayPal Donate Widget</title>' .
			'<link rel="stylesheet" href="' . plugins_url( 'style.css', __FILE__ ) . '"/>' .
			'</head><body marginwidth="0" marginheight="0">';

		echo '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">' .
			'<input type="hidden" name="cmd" value="_donations"/>' .
			'<input type="hidden" name="business" value="' . esc_attr( $code ) . '"/>' .
			'<input type="hidden" name="lc" value="GB"/>' .
			'<input type="hidden" name="item_name" value="' . esc_attr( $name ) . '"/>' .
			'<input type="hidden" name="currency_code" value="' . esc_attr( $currency ) . '"/>' .
			'<input type="hidden" name="bn" value="PP-DonationsBF:btn_donate_SM.gif:NonHosted"/>' .
			'<button id="button" name="submit" type="submit">PayPal</button>';

		/* Add count button inside form */
		if ( $info == 'count' ) {
			$count = $wpdb->get_var( $wpdb->prepare( 'SELECT IFNULL(COUNT(*), 0) FROM ' . $this->table_name .
								 ' WHERE code = %s AND' .
								 ' YEAR(ctime) = YEAR(NOW())' , $code ) );
			echo '<button id="counter" type="submit" name="submit">' . esc_html( $count ) . '</button>';
		}

		echo '</form>';
		echo '</body></html>';
		exit;
	}


	/* Callback handler for PayPal IPN */
	public function paypal_ipn_callback() {
		global $wpdb;

		/* Respond to callback when flag is set */
		if ( ! get_query_var( 'paypal_donate_ipn_cb' ) ) return;

		/* TODO Not implemented yet */
	}


	/* Create database on activation */
	public function plugin_install() {
		global $wpdb;

		$sql = '
CREATE TABLE ' . $this->table_name . ' (
  id VARCHAR(32) NOT NULL,
  ctime TIMESTAMP NOT NULL,
  amount DECIMAL(20) NOT NULL,
  currency VARCHAR(3) NOT NULL,
  code VARCHAR(50) NOT NULL,
  UNIQUE KEY id (id),
  KEY code (code, ctime)
);';

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		add_option('paypal_donate_db_version', $paypal_donate_db_version);
	}


	/* Install plugin action links */
	public function plugin_action_links( $links ) {
		$links[] = '<a href="' . get_admin_url( null, 'options-general.php?page=paypal-donate' ) . '">Settings</a>';
		return $links;
	}


	/* Setup admin page */
	public function create_options_page() {
		/* Create actual options page */
		echo '<div class="wrap">' .
			'<h2>Paypal Donate Shortcode</h2>' .
			'<form method="post">';

		/* These are required for sortable meta boxes but the form
		   containing the fields can be anywhere on the page. */
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false);
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false);

		echo '</form>';

		echo '<div id="poststuff">' .
			'<div id="post-body" class="metabox-holder columns-' .
			( ( get_current_screen()->get_columns() == 1 ) ? '1' : '2' ) .
			'">';

		echo '<div id="postbox-container-1" class="postbox-container">';
		do_meta_boxes( '', 'side', null );
		echo '</div>';

		echo '<div id="postbox-container-2" class="postbox-container">';
		do_meta_boxes( '', 'normal', null );
		echo '</div>';

		echo '</div></div></div>';
	}

	public function admin_menu() {
		/* Create options page */
		$this->options_page = add_options_page( 'Paypal Donate Shortcode' ,
							'Paypal Shortcode' ,
							'manage_options' ,
							'paypal-donate' ,
							array( $this, 'create_options_page' ) );

		/* Actions when loading options page */
		add_action( 'load-' . $this->options_page,
			    array( $this, 'add_options_meta_boxes' ) );
		add_action( 'admin_footer-' . $this->options_page,
			    array( $this, 'add_options_footer' ) );

		/* Actions for generating meta boxes */
		add_action( 'add_meta_boxes_' . $this->options_page,
			    array( $this, 'create_options_meta_boxes') );
	}

	public function add_options_meta_boxes() {
		global $wpdb;

		/* See if any options were posted */
		if ( ! empty( $_POST ) ||
		     isset( $_GET['action'] ) ) {

			if ( isset( $_REQUEST['action'] ) &&
			     $_REQUEST['action'] == 'add-widget' &&
			     check_admin_referer( 'add-widget', 'add-widget-nonce' ) &&
			     isset( $_REQUEST['widget-id'] ) &&
			     isset( $_REQUEST['widget-code'] ) &&
			     isset( $_REQUEST['widget-info'] ) &&
			     isset( $_REQUEST['widget-name'] ) &&
			     isset( $_REQUEST['widget-currency'] ) ) {

				/* Add new widget */
				$widget_id       = trim( $_REQUEST['widget-id'] );
				$widget_code     = trim( $_REQUEST['widget-code'] );
				$widget_info     = trim( $_REQUEST['widget-info'] );
				$widget_name     = trim( $_REQUEST['widget-name'] );
				$widget_currency = trim( $_REQUEST['widget-currency'] );

				if ( ! isset( $this->widgets[ $widget_id ] ) &&
				     strlen( $widget_id) > 0 &&
				     strlen( $widget_code) > 0 &&
				     strlen( $widget_info) > 0 &&
				     strlen( $widget_name) > 0 &&
				     strlen( $widget_currency) == 3 ) {
					$this->widgets[ $widget_id ] = array( 'code'     => $widget_code,
									      'info'     => $widget_info,
									      'name'     => $widget_name,
									      'currency' => $widget_currency );
					update_option( 'paypal_donate_widgets', $this->widgets );
				}
			} else if ( isset( $_REQUEST['action'] ) &&
				    $_REQUEST['action'] == 'delete-widget' &&
				    check_admin_referer( 'delete-widget', 'delete-widget-nonce' ) &&
				    isset( $_REQUEST['widget-id'] ) ) {

				/* Delete existing widget */
				$widget_id = $_REQUEST['widget-id'];

				if ( isset( $this->widgets[ $widget_id ] ) ) {
					unset( $this->widgets[ $widget_id ] );
					update_option( 'paypal_donate_widgets', $this->widgets );
				}
			} else if ( isset( $_REQUEST['action'] ) &&
				    $_REQUEST['action'] == 'add-transaction' &&
				    check_admin_referer( 'add-transaction', 'add-transaction-nonce' ) &&
				    isset( $_REQUEST['transaction-code'] ) &&
				    isset( $_REQUEST['transaction-id'] ) &&
				    isset( $_REQUEST['transaction-time'] ) &&
				    isset( $_REQUEST['transaction-amount'] ) &&
				    isset( $_REQUEST['transaction-currency'] ) ) {

				/* Add transaction manually */
				$code = trim( $_REQUEST['transaction-code'] );
				$id = trim( $_REQUEST['transaction-id'] );
				$ctime = trim( $_REQUEST['transaction-time'] );
				$amount = floatval( $_REQUEST['transaction-amount'] );
				$currency = trim( $_REQUEST['transaction-currency'] );

				if ( strlen( $code ) > 0 &&
				     strlen( $id ) > 0 &&
				     strlen( $ctime ) > 0 &&
				     $amount > 0 &&
				     strlen( $currency ) == 3 ) {
					$wpdb->insert( $this->table_name,
						       array( 'id'       => $id,
							      'ctime'    => $ctime,
							      'amount'   => $amount,
							      'currency' => $currency,
							      'code'     => $code ) );
				}
			} else if ( isset( $_REQUEST['action'] ) &&
				    $_REQUEST['action'] == 'delete-transaction' &&
				    check_admin_referer( 'delete-transaction', 'delete-transaction-nonce' ) &&
				    isset( $_REQUEST['transaction-id'] ) ) {

				/* Delete transaction */
				$id = trim( $_REQUEST['transaction-id'] );

				if ( strlen( $id ) > 0 ) {
					$wpdb->delete( $this->table_name,
						       array( 'id' => $id ) );
				}
			}

			wp_redirect( admin_url( 'options-general.php?page=paypal-donate' ) );
			exit;
		}

		/* Add the actual options page content */
		do_action( 'add_meta_boxes_' . $this->options_page, null );
		do_action( 'add_meta_boxes', $this->options_page, null );

		wp_enqueue_script( 'postbox' );

		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2) );
	}

	public function add_options_footer() {
		echo '<script>jQuery(document).ready(function(){postboxes.add_postbox_toggles(pagenow);});</script>';
	}

	public function create_options_meta_boxes() {
		/* Main */
		add_meta_box( 'widgets',
			      'Setup Widgets',
			      array( $this, 'widgets_meta_box' ),
			      $this->options_page,
			      'normal' );
		add_meta_box( 'transactions',
			      'Transactions',
			      array( $this, 'transactions_meta_box' ),
			      $this->options_page,
			      'normal' );
		add_meta_box( 'external-embed',
			      'Embed widgets externally',
			      array( $this, 'external_embed_meta_box' ),
			      $this->options_page,
			      'normal' );

		/* Side */
		add_meta_box( 'support-info',
			      'Support',
			      array( $this, 'support_info_meta_box' ),
			      $this->options_page,
			      'side' );
	}

	public function widgets_meta_box() {
		echo '<ol><li>Go to Paypal &gt; My Profile &gt; More Options &gt; My selling tools &gt;' .
			' PayPal buttons &gt; Get Started.</li>' .
			'<li>Choose <strong>Donations</strong> in <strong>button type</strong>.</li>' .
			'<li>Click <strong>Create Button</strong>. A button will be generated and a code snippet will appear.</li>' .
			'<li>Click <strong>Remove code protection</strong> and copy your business ID to' .
			' the <strong>code</strong> field below.</li>' .
			'<li>Enter a name for your settings in the <strong>id</strong> field below that will be used' .
			' when you add a shortcode (Example: Choose the ID <code>main</code> and use' .
			' <code>[paypal-donate id="main"]</code> to add the widget in a post).</li>' .
			'<li>Set <strong>name</strong> and <strong>currency</strong> as desired in the remaining' .
			' fields below.</ol>';

		echo '<form method="post"><input type="hidden" name="action" value="add-widget"/>';

		wp_nonce_field( 'add-widget', 'add-widget-nonce' );

		$info_options = array( 'count' => 'Count',
				       'off'   => 'Off' );

		echo '<table style="width:100%;"><tbody>' .
			'<tr><th scope="col">Id</th>' .
			'<th scope="col">Code</th>' .
			'<th scope="col">Name</th>' .
			'<th scope="col">Currency</th>' .
			'<th scope="col">Info</th>' .
			'<th scope="col"></th></tr>';
		foreach ( $this->widgets as $key => $widget ) {
			$delete_args = array( 'page'      => 'paypal-donate',
					      'action'    => 'delete-widget',
					      'widget-id' => $key );
			$delete_url  = wp_nonce_url( admin_url( 'options-general.php?' . build_query( $delete_args ) ),
						     'delete-widget',
						     'delete-widget-nonce' );
			$widget_info = array_key_exists( $widget['info'], $info_options ) ? $widget['info'] : 'off';
			echo '<tr><td>' . esc_html( $key ) . '</td>' .
				'<td>' . esc_html( $widget['code'] ) . '</td>' .
				'<td>' . esc_html( $widget['name'] ) . '</td>' .
				'<td>' . esc_html( $widget['currency'] ) . '</td>' .
				'<td>' . esc_html( $info_options[ $widget_info ] ) . '</td>' .
				'<td><a class="button delete" href="' . $delete_url . '">Delete</a></td></tr>';
		}

		echo '<tr><td><input style="width:100%;" type="text" name="widget-id"/></td>' .
			'<td><input style="width:100%;" type="text" name="widget-code"/></td>' .
			'<td><input style="width:100%;" type="text" name="widget-name"/></td>' .
			'<td><input style="width:100%;" type="text" name="widget-currency"/></td>' .
			'<td><select style="width:100%;" name="widget-info">';

		foreach ( $info_options as $key => $value ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
		}

		echo '</select></td>' .
			'<td><input class="button button-primary" type="submit" value="Add"/></td></tr>' .
			'</tbody></table>';
		echo '</form>';
	}

	public function transactions_meta_box() {
		global $wpdb;

		echo '<form method="post"><input type="hidden" name="action" value="add-transaction"/>';

		wp_nonce_field( 'add-transaction', 'add-transaction-nonce' );

		echo '<table style="width:100%;"><tbody>' .
			'<tr><th scope="col">Code</th>' .
			'<th scope="col">Id</th>' .
			'<th scope="col">Timestamp</th>' .
			'<th scope="col">Currency</th>' .
			'<th scope="col">Amount</th>' .
			'<th scope="col"></th></tr>';
		$txs = $wpdb->get_results( 'SELECT id, ctime, amount, currency, code FROM ' . $this->table_name .
					   ' ORDER BY ctime DESC');
		foreach ( $txs as $tx ) {
			$delete_args = array( 'page'           => 'paypal-donate',
					      'action'         => 'delete-transaction',
					      'transaction-id' => $tx->id );
			$delete_url  = wp_nonce_url( admin_url( 'options-general.php?' . build_query( $delete_args ) ),
						     'delete-transaction',
						     'delete-transaction-nonce' );
			echo '<tr><td>' . esc_html( $tx->code ) . '</td>' .
				'<td>' . esc_html( $tx->id ) . '</td>' .
				'<td>' . esc_html( $tx->ctime ) . '</td>' .
				'<td>' . esc_html( $tx->currency ) . '</td>' .
				'<td style="text-align:right;">' .
				esc_html( number_format( $tx->amount / 100, 2, '.', '' ) ) . '</td>' .
				'<td><a class="button delete" href="' . $delete_url . '">Delete</a></td>' .
				'</tr>';
		}

		echo '<tr><td><input style="width:100%;" type="text" name="transaction-code"/></td>' .
			'<td><input style="width:100%;" type="text" name="transaction-id"/></td>' .
			'<td><input style="width:100%;" type="text" name="transaction-time"/></td>' .
			'<td><input style="width:100%;" type="text" name="transaction-currency"/></td>' .
			'<td><input style="width:100%;" type="text" name="transaction-amount"/></td>' .
			'<td><input class="button button-primary" type="submit" value="Add"/></td></tr>' .
			'</tbody></table>';
	}

	public function support_info_meta_box() {
		echo '<p>Please consider making a donation if you find this plugin useful.</p>'.
			'<p><iframe src="http://jonls.dk/?paypal_donate_widget=main"' .
			' width="250" height="22" frameborder="0" scrolling="no" title="PayPal Donate"' .
			' border="0" marginheight="0" marginwidth="0" allowtransparency="true"></iframe></p>';
	}

	public function external_embed_meta_box() {
		echo '<p>Your widgets can be embedded in any web page by adding' .
			' the code snippet that is generated after selecting a widget in the list.</p>';

		echo '<table class="form-table"></tbody>' .
			'<tr><th scope="row"><label for="external-widget-select">Widget</label></th>' .
			'<td>';

		if ( count( $this->widgets ) > 0 ) {
			echo '<select id="external-widget-select">';
			foreach ( $this->widgets as $key => $widget ) {
				echo '<option name="' . esc_attr( $key ) . '">' . esc_html( $key ) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<select id="external-widget-select" disabled="disabled"><option>Add a widget first</option></select>';
		}

		echo '<tr><th scope="row"><label for="external-widget-snippet">Code snippet</label></th>' .
			'<td><textarea class="large-text code" id="external-widget-snippet" readonly="readonly"' .
			' rows="5" style="width:100%;"></textarea></td></tr>';

		echo '</td></tr></tbody></table>';

		/* Generate snippet for external embedding */
		echo '<script>jQuery(document).ready(function(){' .
			'function update_snippet(widget) {' .
			'jQuery("#external-widget-snippet").val("' .
			'<iframe src=\"http://jonls.dk/?paypal_donate_widget="+encodeURIComponent(widget)+"\"' .
			' width=\"250\" height=\"22\" frameborder=\"0\" scrolling=\"no\"' .
			' title=\"PayPal Donate\" border=\"0\" marginheight=\"0\" marginwidth=\"0\"' .
			' allowtransparency=\"true\"></iframe>");}' .
			'jQuery("#external-widget-select").change(function(){' .
			'update_snippet(jQuery(this).val());});' .
			'if (jQuery("#external-widget-select").is(":enabled")) {' .
			'update_snippet(jQuery("#external-widget-select").val());}});</script>';
	}
}

$paypal_button = new Paypal_Button();

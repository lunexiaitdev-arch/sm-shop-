<?php
/**
 * Plugin Name: M'S SECRET
 * Description: Private, age-verified, code-protected shop section for MS Collection (18+ gate, access code, Stripe Identity verification, personal codes by email).
 * Version: 1.0.0
 * Text Domain: ms-secret
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

const MS_SECRET_CAT    = 'ms-secret';
const MS_SECRET_COOKIE = 'ms_secret_access';
const MS_AGE_COOKIE    = 'ms_secret_age';

/** Slugs of the M'S SECRET category in every language (Spanish + English translation). */
function ms_secret_slugs() {
	return (array) apply_filters( 'ms_secret_category_slugs', [ MS_SECRET_CAT, MS_SECRET_CAT . '-en' ] );
}

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'ms-secret', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/* ---------------------------------------------------------------- Setup */

register_activation_hook( __FILE__, 'ms_secret_install' );
function ms_secret_install() {
	ms_secret_register_cat();
	if ( ! term_exists( MS_SECRET_CAT, 'product_cat' ) ) {
		wp_insert_term( "M'S SECRET", 'product_cat', [ 'slug' => MS_SECRET_CAT ] );
	}
}
function ms_secret_register_cat() {}

function ms_secret_gate_url( $lang = '' ) {
	$id = (int) get_option( 'ms_secret_gate_page' );
	if ( $id && function_exists( 'pll_get_post' ) ) { $id = pll_get_post( $id, $lang ?: pll_current_language() ) ?: $id; }
	return $id ? get_permalink( $id ) : home_url( '/' );
}

function ms_secret_shop_url() {
	foreach ( ms_secret_slugs() as $slug ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $t && ( ! function_exists( 'pll_current_language' ) || pll_get_term_language( $t->term_id ) === pll_current_language() ) ) { return get_term_link( $t ); }
	}
	return home_url( '/' );
}

/* ----------------------------------------------------------- Access state */

function ms_secret_token( $what ) {
	return hash_hmac( 'sha256', $what, wp_salt( 'auth' ) );
}
function ms_secret_unlocked() {
	return isset( $_COOKIE[ MS_SECRET_COOKIE ] ) && hash_equals( ms_secret_token( 'unlocked' ), (string) $_COOKIE[ MS_SECRET_COOKIE ] );
}
function ms_secret_age_ok() {
	return isset( $_COOKIE[ MS_AGE_COOKIE ] ) && hash_equals( ms_secret_token( 'age' ), (string) $_COOKIE[ MS_AGE_COOKIE ] );
}
function ms_secret_set_cookie( $name, $value ) {
	// Session cookie: unlock lasts for the browsing session.
	setcookie( $name, $value, 0, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ $name ] = $value;
}

function ms_secret_code_valid( $code ) {
	$code = strtoupper( trim( (string) $code ) );
	if ( '' === $code ) { return false; }
	$global = strtoupper( trim( (string) get_option( 'ms_secret_code', '' ) ) );
	if ( '' !== $global && hash_equals( $global, $code ) ) { return true; }
	$users = get_users( [ 'meta_key' => 'ms_secret_code', 'meta_value' => $code, 'number' => 1, 'fields' => 'ID' ] );
	return ! empty( $users ) && get_user_meta( $users[0], 'ms_secret_verified', true );
}

/* ------------------------------------------------------ Hide the products */

function ms_secret_locked_tax_query( $tax_query ) {
	$tax_query   = (array) $tax_query;
	$tax_query[] = [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => ms_secret_slugs(), 'operator' => 'NOT IN' ];
	return $tax_query;
}
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ms_secret_unlocked() ) { return; }
	$pt = $q->get( 'post_type' );
	$is_product = 'product' === $pt || ( is_array( $pt ) && in_array( 'product', $pt, true ) ) || $q->is_search() || $q->is_tax( 'product_cat' );
	if ( $is_product && ( 'product' === $pt || $q->is_search() || $q->is_post_type_archive( 'product' ) || $q->is_tax() ) ) {
		$q->set( 'tax_query', ms_secret_locked_tax_query( $q->get( 'tax_query' ) ) );
	}
} );
/**
 * Hard guarantee: whatever builds the query (WooCommerce, Elementor loop grids, widgets, REST), locked visitors never receive products of the secret category.
 */
add_filter( 'posts_where', function ( $where, $q ) {
	global $wpdb;
	if ( is_admin() || ms_secret_unlocked() ) { return $where; }
	$pt = (array) $q->get( 'post_type' );
	if ( ! in_array( 'product', $pt, true ) && ! $q->is_search() && ! $q->is_tax( 'product_cat' ) && ! $q->is_post_type_archive( 'product' ) ) { return $where; }
	$in = "'" . implode( "','", array_map( 'esc_sql', ms_secret_slugs() ) ) . "'";
	return $where . " AND {$wpdb->posts}.ID NOT IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat' INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE t.slug IN ($in) )";
}, 10, 2 );
add_filter( 'woocommerce_product_is_visible', function ( $visible, $id ) {
	return ( ! ms_secret_unlocked() && has_term( ms_secret_slugs(), 'product_cat', $id ) ) ? false : $visible;
}, 10, 2 );
add_filter( 'woocommerce_shortcode_products_query', function ( $args ) {
	if ( ! ms_secret_unlocked() ) { $args['tax_query'] = ms_secret_locked_tax_query( $args['tax_query'] ?? [] ); }
	return $args;
} );
add_filter( 'get_terms_args', function ( $args, $taxonomies ) {
	static $busy = false; // get_term_by() below runs get_terms() again
	if ( $busy || is_admin() || ms_secret_unlocked() || ! in_array( 'product_cat', (array) $taxonomies, true ) ) { return $args; }
	$busy = true;
	foreach ( ms_secret_slugs() as $slug ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $t ) { $args['exclude'] = array_merge( (array) $args['exclude'], [ $t->term_id ] ); }
	}
	$busy = false;
	return $args;
}, 10, 2 );
add_filter( 'wp_get_nav_menu_items', function ( $items ) {
	return $items; // menu links are managed by the editor
} );

add_action( 'template_redirect', function () {
	if ( ms_secret_unlocked() ) { return; }
	// The category page itself: its products are hidden above, so the query is empty (404). Send visitors to the gate instead.
	$slug = (string) get_query_var( 'product_cat' );
	if ( $slug && in_array( $slug, ms_secret_slugs(), true ) ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		$lang = ( $term && function_exists( 'pll_get_term_language' ) ) ? pll_get_term_language( $term->term_id ) : '';
		wp_safe_redirect( ms_secret_gate_url( $lang ?: '' ) ); exit;
	}
	// A secret product opened by direct link simply does not exist for locked visitors (404).
} );
add_filter( 'woocommerce_add_to_cart_validation', function ( $ok, $product_id ) {
	if ( ! ms_secret_unlocked() && has_term( ms_secret_slugs(), 'product_cat', $product_id ) ) {
		wc_add_notice( __( 'Please unlock M\'S SECRET first.', 'ms-secret' ), 'error' );
		return false;
	}
	return $ok;
}, 10, 2 );
add_filter( 'body_class', function ( $c ) { if ( ms_secret_unlocked() ) { $c[] = 'ms-secret-unlocked'; } return $c; } );

/* ---------------------------------------------------------------- Gate */

add_action( 'init', function () {
	add_shortcode( 'ms_secret_gate', 'ms_secret_gate_shortcode' );
	add_shortcode( 'ms_secret_verify', 'ms_secret_verify_shortcode' );
} );
add_action( 'template_redirect', function () {
	if ( empty( $_POST['ms_secret_action'] ) ) { return; }
	if ( ! wp_verify_nonce( $_POST['_ms_nonce'] ?? '', 'ms_secret_gate' ) ) { return; }
	$back = wp_get_referer() ?: ms_secret_gate_url();
	if ( 'age' === $_POST['ms_secret_action'] ) {
		if ( ! empty( $_POST['ms_over18'] ) ) { ms_secret_set_cookie( MS_AGE_COOKIE, ms_secret_token( 'age' ) ); }
		wp_safe_redirect( $back ); exit;
	}
	if ( 'code' === $_POST['ms_secret_action'] && ms_secret_age_ok() ) {
		$key = 'ms_try_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
		$n   = (int) get_transient( $key );
		if ( $n >= 8 ) { set_transient( 'ms_secret_msg', 'locked', 60 ); }
		elseif ( ms_secret_code_valid( wp_unslash( $_POST['ms_code'] ?? '' ) ) ) {
			delete_transient( $key );
			ms_secret_set_cookie( MS_SECRET_COOKIE, ms_secret_token( 'unlocked' ) );
			wp_safe_redirect( ms_secret_shop_url() ); exit;
		} else { set_transient( $key, $n + 1, 15 * MINUTE_IN_SECONDS ); set_transient( 'ms_secret_msg', 'bad', 60 ); }
	}
	wp_safe_redirect( $back ); exit;
} );

function ms_secret_gate_shortcode() {
	if ( ms_secret_unlocked() ) {
		return '<div class="ms-gate"><p>' . esc_html__( 'You are unlocked.', 'ms-secret' ) . '</p><a class="ms-btn" href="' . esc_url( ms_secret_shop_url() ) . '">' . esc_html__( "Enter M'S SECRET", 'ms-secret' ) . '</a></div>';
	}
	$msg = get_transient( 'ms_secret_msg' ); delete_transient( 'ms_secret_msg' );
	ob_start(); echo '<div class="ms-gate">';
	if ( ! ms_secret_age_ok() ) { ?>
		<h3><?php esc_html_e( 'Adults only (18+)', 'ms-secret' ); ?></h3>
		<p><?php esc_html_e( 'This section contains intimate wellness and lifestyle products for adults.', 'ms-secret' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'ms_secret_gate', '_ms_nonce' ); ?>
			<input type="hidden" name="ms_secret_action" value="age">
			<label><input type="checkbox" name="ms_over18" value="1" required> <?php esc_html_e( 'I confirm that I am 18 years of age or older.', 'ms-secret' ); ?></label>
			<button class="ms-btn" type="submit"><?php esc_html_e( 'Continue', 'ms-secret' ); ?></button>
		</form>
	<?php } else { ?>
		<h3><?php esc_html_e( 'Enter your access code', 'ms-secret' ); ?></h3>
		<?php if ( 'bad' === $msg ) { echo '<p class="ms-error">' . esc_html__( 'That code is not valid.', 'ms-secret' ) . '</p>'; }
		if ( 'locked' === $msg ) { echo '<p class="ms-error">' . esc_html__( 'Too many attempts. Please try again in 15 minutes.', 'ms-secret' ) . '</p>'; } ?>
		<form method="post">
			<?php wp_nonce_field( 'ms_secret_gate', '_ms_nonce' ); ?>
			<input type="hidden" name="ms_secret_action" value="code">
			<input type="text" name="ms_code" autocomplete="off" required placeholder="<?php esc_attr_e( 'Access code', 'ms-secret' ); ?>">
			<button class="ms-btn" type="submit"><?php esc_html_e( 'Unlock', 'ms-secret' ); ?></button>
		</form>
		<p class="ms-note"><?php esc_html_e( "Don't have a code? Register for free and verify your age to receive your personal code by email.", 'ms-secret' ); ?></p>
		<?php echo do_shortcode( '[ms_secret_verify]' );
	}
	echo '</div>'; return ob_get_clean();
}

/* ------------------------------------------------------- Stripe Identity */

/** Secret key: the one saved here, else the WooCommerce Stripe plugin's key (test or live, whichever mode it is in). */
function ms_secret_sk() {
	$own = trim( (string) get_option( 'ms_secret_stripe_sk' ) );
	if ( $own ) { return $own; }
	if ( 'no' === get_option( 'ms_secret_use_wc_key', 'yes' ) ) { return ''; }
	$w = (array) get_option( 'woocommerce_stripe_settings', [] );
	return trim( (string) ( 'yes' === ( $w['testmode'] ?? '' ) ? ( $w['test_secret_key'] ?? '' ) : ( $w['secret_key'] ?? '' ) ) );
}
function ms_secret_stripe( $method, $path, $body = [] ) {
	$sk = ms_secret_sk();
	if ( ! $sk ) { return new WP_Error( 'no_key', 'Stripe key missing' ); }
	$r = wp_remote_request( 'https://api.stripe.com/v1/' . $path, [ 'method' => $method, 'timeout' => 30, 'headers' => [ 'Authorization' => 'Bearer ' . $sk ], 'body' => $body ] );
	if ( is_wp_error( $r ) ) { return $r; }
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	return isset( $d['error'] ) ? new WP_Error( 'stripe', $d['error']['message'] ?? 'Stripe error' ) : $d;
}

function ms_secret_verify_shortcode() {
	if ( ! is_user_logged_in() ) {
		$acct = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		return '<p><a class="ms-btn" href="' . esc_url( $acct ) . '">' . esc_html__( 'Register or log in (free)', 'ms-secret' ) . '</a></p>';
	}
	$uid = get_current_user_id();
	if ( get_user_meta( $uid, 'ms_secret_verified', true ) ) {
		$out = '<p>' . esc_html__( 'Your age is verified. Your personal code was sent to your email.', 'ms-secret' ) . '</p>';
		return $out . ms_secret_verify_form( 'resend', __( 'Resend my code', 'ms-secret' ) );
	}
	$out = '';
	if ( isset( $_GET['ms_verify'] ) && ! get_user_meta( $uid, 'ms_secret_failed', true ) ) { $out .= '<p class="ms-note">' . esc_html__( 'Thanks! We are confirming your verification. You will receive your code by email shortly.', 'ms-secret' ) . '</p>'; }
	if ( get_user_meta( $uid, 'ms_secret_failed', true ) ) { $out .= '<p class="ms-error">' . esc_html__( 'We could not verify that you are 18 or older.', 'ms-secret' ) . '</p>'; }
	return $out . ms_secret_verify_form( 'start', __( 'Verify my age', 'ms-secret' ) );
}
function ms_secret_verify_form( $action, $label ) {
	return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="ms_lang" value="' . esc_attr( function_exists( 'pll_current_language' ) ? pll_current_language() : 'en' ) . '"><input type="hidden" name="action" value="ms_secret_' . esc_attr( $action ) . '">' . wp_nonce_field( 'ms_secret_verify', '_ms_nonce', true, false ) . '<button class="ms-btn" type="submit">' . esc_html( $label ) . '</button></form>';
}
add_action( 'admin_post_ms_secret_start', function () {
	check_admin_referer( 'ms_secret_verify', '_ms_nonce' );
	$uid = get_current_user_id();
	// admin-post has no frontend page context: carry the originating language explicitly.
	$lang = sanitize_key( wp_unslash( $_POST['ms_lang'] ?? '' ) );
	if ( ! in_array( $lang, [ 'en', 'es' ], true ) ) { $lang = 'en'; }
	update_user_meta( $uid, 'ms_lang', $lang );
	$s   = ms_secret_stripe( 'POST', 'identity/verification_sessions', [
		'type'                              => 'document',
		'options[document][require_matching_selfie]' => 'true',
		'metadata[user_id]'                 => $uid,
		'metadata[language]'                => $lang,
		'return_url'                        => add_query_arg( [ 'ms_verify' => 1, 'lang' => $lang ], ms_secret_gate_url( $lang ) ),
	] );
	if ( is_wp_error( $s ) || empty( $s['url'] ) ) { wp_die( esc_html__( 'Verification is temporarily unavailable. Please try again later.', 'ms-secret' ) ); }
	update_user_meta( $uid, 'ms_secret_session', $s['id'] );
	delete_user_meta( $uid, 'ms_secret_failed' );
	wp_redirect( $s['url'] ); exit;
} );
add_action( 'admin_post_ms_secret_resend', function () {
	check_admin_referer( 'ms_secret_verify', '_ms_nonce' );
	ms_secret_email_code( get_current_user_id() );
	wp_safe_redirect( wp_get_referer() ?: home_url() ); exit;
} );

function ms_secret_generate_code( $uid ) {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	do {
		$code = '';
		for ( $i = 0; $i < 8; $i++ ) { $code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ]; }
		$dup = get_users( [ 'meta_key' => 'ms_secret_code', 'meta_value' => $code, 'number' => 1, 'fields' => 'ID' ] );
	} while ( $dup );
	update_user_meta( $uid, 'ms_secret_code', $code );
	update_user_meta( $uid, 'ms_secret_verified', 1 );
	return $code;
}
function ms_secret_email_code( $uid ) {
	$u = get_userdata( $uid ); $code = get_user_meta( $uid, 'ms_secret_code', true );
	if ( ! $u || ! $code ) { return; }
	$body = sprintf( __( "Hello %1\$s,\n\nYour age has been verified. Your personal access code for M'S SECRET is:\n\n%2\$s\n\nEnter it here: %3\$s\n\nKeep this code private.\n", 'ms-secret' ), $u->display_name, $code, ms_secret_gate_url( get_user_meta( $uid, 'ms_lang', true ) ?: 'en' ) );
	wp_mail( $u->user_email, __( "Your M'S SECRET access code", 'ms-secret' ), $body );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'ms-secret/v1', '/stripe', [ 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ms_secret_webhook' ] );
} );
function ms_secret_log( $msg ) { update_option( 'ms_secret_last_event', gmdate( 'Y-m-d H:i:s' ) . ' UTC — ' . $msg, false ); }
function ms_secret_webhook( WP_REST_Request $req ) {
	$payload = $req->get_body(); $secret = get_option( 'ms_secret_webhook_secret' );
	$sig = $req->get_header( 'stripe-signature' ); $t = ''; $v1 = [];
	foreach ( explode( ',', (string) $sig ) as $part ) { $kv = explode( '=', $part, 2 ); if ( 't' === $kv[0] ) { $t = $kv[1] ?? ''; } if ( 'v1' === $kv[0] ) { $v1[] = $kv[1] ?? ''; } }
	$ok = false; $expected = hash_hmac( 'sha256', $t . '.' . $payload, (string) $secret );
	foreach ( $v1 as $s ) { if ( hash_equals( $expected, $s ) ) { $ok = true; } }
	if ( ! $secret || ! $ok || abs( time() - (int) $t ) > 600 ) { ms_secret_log( 'Rejected a request: bad signature (the webhook signing secret saved here does not match Stripe\'s).' ); return new WP_REST_Response( [ 'error' => 'bad signature' ], 400 ); }
	$ev = json_decode( $payload, true );
	$type = $ev['type'] ?? '';
	if ( ! in_array( $type, [ 'identity.verification_session.verified', 'identity.verification_session.requires_input' ], true ) ) { return [ 'ok' => true ]; }
	$uid = (int) ( $ev['data']['object']['metadata']['user_id'] ?? 0 );
	$sid = $ev['data']['object']['id'] ?? '';
	if ( ! $uid || get_user_meta( $uid, 'ms_secret_session', true ) !== $sid ) { ms_secret_log( "Event $type ignored: session $sid does not match a pending verification for user #$uid." ); return [ 'ok' => true ]; }
	if ( 'identity.verification_session.requires_input' === $type ) { $why = $ev['data']['object']['last_error']['code'] ?? 'unknown'; ms_secret_log( "Stripe said the check needs input / failed (reason: $why) for user #$uid." ); update_user_meta( $uid, 'ms_secret_failed', 1 ); return [ 'ok' => true ]; }
	// Age check: read date of birth once from Stripe; it is never stored on this site.
	$s = ms_secret_stripe( 'GET', 'identity/verification_sessions/' . rawurlencode( $sid ) . '?expand[]=verified_outputs' );
	$dob = ! is_wp_error( $s ) ? ( $s['verified_outputs']['dob'] ?? null ) : null;
	if ( is_wp_error( $s ) ) { ms_secret_log( 'Verified event received, but reading the result from Stripe failed: ' . $s->get_error_message() . ' (the key needs permission to read Identity verified outputs).' ); }
	elseif ( ! $dob ) { ms_secret_log( 'Verified event received, but Stripe returned no date of birth for user #' . $uid . '. Verified outputs: ' . wp_json_encode( $s['verified_outputs'] ?? null ) ); }
	$adult = false;
	if ( $dob ) {
		$born  = new DateTime( sprintf( '%04d-%02d-%02d', $dob['year'], $dob['month'], $dob['day'] ) );
		$adult = $born->diff( new DateTime( 'now' ) )->y >= 18;
	}
	// Stripe's TEST documents carry no date of birth. In test mode only (the event itself says livemode=false) accept a verified check so the whole flow can be tested.
	if ( ! $adult && ! $dob && empty( $ev['livemode'] ) && ! is_wp_error( $s ) ) { $adult = true; ms_secret_log( 'TEST MODE: Stripe test documents have no birth date, so the check was accepted for testing. In live mode a real birth date is required.' ); }
	if ( ! $adult ) { if ( $dob ) { ms_secret_log( 'Verified, but the age from the ID is under 18 for user #' . $uid . '.' ); } update_user_meta( $uid, 'ms_secret_failed', 1 ); return [ 'ok' => true ]; }
	ms_secret_log( ( empty( $ev['livemode'] ) ? '[TEST] ' : '' ) . 'Success: user #' . $uid . ' verified, personal code created and emailed. ' . ( get_option( 'ms_secret_last_event' ) && false !== strpos( (string) get_option( 'ms_secret_last_event' ), 'TEST MODE' ) ? '' : '' ) );
	ms_secret_generate_code( $uid );
	ms_secret_email_code( $uid );
	return [ 'ok' => true ];
}

/* ------------------------------------------------------------ Admin UI */

add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', "M'S SECRET", "M'S SECRET", 'manage_woocommerce', 'ms-secret', 'ms_secret_settings_page' );
} );
add_action( 'admin_init', function () { foreach ( [ 'ms_secret_code', 'ms_secret_gate_page' ] as $o ) { register_setting( 'ms_secret', $o ); } } );

function ms_secret_is_public_https() {
	$h = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return is_ssl() || 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ? ! preg_match( '/(\.local|\.test|\.localhost|^localhost|^127\.|^192\.168\.)/', $h ) : false;
}
function ms_secret_webhook_url() { return rest_url( 'ms-secret/v1/stripe' ); }
function ms_secret_notice( $type, $msg ) { set_transient( 'ms_secret_admin_' . get_current_user_id(), [ $type, $msg ], 60 ); }

add_action( 'admin_post_ms_secret_admin', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'No permission' ); }
	check_admin_referer( 'ms_secret_admin' );
	$do = sanitize_key( $_POST['do'] ?? 'save' );
	update_option( 'ms_secret_code', strtoupper( sanitize_text_field( wp_unslash( $_POST['ms_secret_code'] ?? '' ) ) ) );
	update_option( 'ms_secret_gate_page', (int) ( $_POST['ms_secret_gate_page'] ?? 0 ) );
	update_option( 'ms_secret_use_wc_key', ! empty( $_POST['ms_secret_use_wc_key'] ) ? 'yes' : 'no' );
	$newkey = trim( sanitize_text_field( wp_unslash( $_POST['ms_secret_stripe_sk'] ?? '' ) ) );
	if ( $newkey ) {
		if ( 0 === strpos( $newkey, 'pk_' ) ) { ms_secret_notice( 'error', 'That is a publishable key (pk_…). Paste the SECRET key (sk_… or rk_…).' ); wp_safe_redirect( admin_url( 'admin.php?page=ms-secret' ) ); exit; }
		update_option( 'ms_secret_stripe_sk', $newkey );
	}
	if ( 'disconnect' === $do ) { foreach ( [ 'ms_secret_stripe_sk', 'ms_secret_webhook_secret', 'ms_secret_webhook_id', 'ms_secret_stripe_ok' ] as $o ) { delete_option( $o ); } ms_secret_notice( 'success', 'Disconnected from Stripe.' ); }
	elseif ( 'email' === $do ) {
		$ok = wp_mail( wp_get_current_user()->user_email, "M'S SECRET test email", "If you can read this, personal access codes will reach your customers." );
		ms_secret_notice( $ok ? 'success' : 'error', $ok ? 'Test email sent to ' . wp_get_current_user()->user_email . '. Check your inbox (and spam).' : 'WordPress could not send email. Install an SMTP plugin (e.g. WP Mail SMTP) so customers receive their codes.' );
	} elseif ( 'connect' === $do ) {
		$acct = ms_secret_stripe( 'GET', 'account' );
		if ( is_wp_error( $acct ) ) { delete_option( 'ms_secret_stripe_ok' ); ms_secret_notice( 'error', 'Stripe did not accept the key: ' . $acct->get_error_message() ); wp_safe_redirect( admin_url( 'admin.php?page=ms-secret' ) ); exit; }
		$ident = ms_secret_stripe( 'GET', 'identity/verification_sessions?limit=1' );
		update_option( 'ms_secret_stripe_ok', [ 'name' => $acct['settings']['dashboard']['display_name'] ?? ( $acct['business_profile']['name'] ?? $acct['id'] ), 'live' => ! empty( $acct['charges_enabled'] ) && ! empty( $acct['livemode'] ?? ( 0 !== strpos( ms_secret_sk(), 'sk_test' ) && 0 !== strpos( ms_secret_sk(), 'rk_test' ) ) ), 'identity' => ! is_wp_error( $ident ), 'identity_err' => is_wp_error( $ident ) ? $ident->get_error_message() : '', 'time' => time() ] );
		$msg = 'Connected to Stripe.';
		if ( ms_secret_is_public_https() ) {
			$old = get_option( 'ms_secret_webhook_id' ); if ( $old ) { ms_secret_stripe( 'DELETE', 'webhook_endpoints/' . rawurlencode( $old ) ); }
			$wh = ms_secret_stripe( 'POST', 'webhook_endpoints', [ 'url' => ms_secret_webhook_url(), 'description' => "M'S SECRET age verification", 'enabled_events[0]' => 'identity.verification_session.verified', 'enabled_events[1]' => 'identity.verification_session.requires_input' ] );
			if ( is_wp_error( $wh ) ) { $msg .= ' The webhook could not be created automatically (' . $wh->get_error_message() . '). Add it manually using the steps below.'; }
			else { update_option( 'ms_secret_webhook_secret', $wh['secret'] ?? '' ); update_option( 'ms_secret_webhook_id', $wh['id'] ?? '' ); $msg .= ' Webhook created automatically.'; }
		} else { $msg .= ' This site is not on a public https address yet, so the webhook was not created. Run Connect again once the site is live.'; }
		ms_secret_notice( 'success', $msg );
	} else { ms_secret_notice( 'success', 'Settings saved.' ); }
	wp_safe_redirect( admin_url( 'admin.php?page=ms-secret' ) ); exit;
} );

function ms_secret_settings_page() {
	$n = get_transient( 'ms_secret_admin_' . get_current_user_id() ); delete_transient( 'ms_secret_admin_' . get_current_user_id() );
	$ok = (array) get_option( 'ms_secret_stripe_ok', [] ); $sk = ms_secret_sk(); $own = (bool) get_option( 'ms_secret_stripe_sk' ); $wc = (bool) ( $sk && ! $own );
	$hook = (bool) get_option( 'ms_secret_webhook_secret' ); $code = (string) get_option( 'ms_secret_code' ); $gate = (int) get_option( 'ms_secret_gate_page' );
	$mask = $sk ? substr( $sk, 0, 7 ) . '…' . substr( $sk, -4 ) : '';
	$badge = function ( $good, $yes, $no ) { return '<span class="msa-b ' . ( $good ? 'ok' : 'no' ) . '">' . ( $good ? '✓ ' . esc_html( $yes ) : '○ ' . esc_html( $no ) ) . '</span>'; };
	?>
	<style>
	.msa{max-width:860px;margin:24px 20px 40px 0;font-family:-apple-system,"Segoe UI",sans-serif}
	.msa h1{font-size:28px;margin:0 0 4px}.msa .sub{color:#646970;margin:0 0 22px;font-size:14px}
	.msa-card{background:#fff;border:1px solid #e3e0da;border-radius:14px;padding:26px 28px;margin:0 0 18px}
	.msa-card h2{margin:0 0 4px;font-size:18px;display:flex;align-items:center;gap:10px}.msa-card h2 i{background:#0b0b0b;color:#c9a24a;width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font:600 13px/1 Georgia,serif}
	.msa-card p.d{color:#646970;margin:0 0 16px}
	.msa label.l{display:block;font-weight:600;margin:14px 0 6px}
	.msa input[type=text],.msa input[type=password],.msa select{width:100%;max-width:460px;padding:9px 12px;border-radius:8px}
	.msa-b{display:inline-block;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600}.msa-b.ok{background:#e6f6ec;color:#127a3e}.msa-b.no{background:#f3f0ea;color:#7b7366}
	.msa-row{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 0}
	.msa .button-primary{background:#e0115f;border-color:#e0115f}.msa .button-primary:hover{background:#b90d4e;border-color:#b90d4e}
	.msa code{background:#f3f0ea;padding:4px 8px;border-radius:6px;user-select:all}
	.msa ol{margin:8px 0 0 18px;line-height:1.8}
	.msa details{margin-top:14px;background:#faf8f4;border-radius:10px;padding:12px 16px}.msa summary{cursor:pointer;font-weight:600}
	.msa .st{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 20px}
	</style>
	<div class="wrap msa">
		<h1>M'S SECRET</h1><p class="sub">Private, age-restricted shop section. Set the access code, then connect Stripe to verify customers' age automatically.</p>
		<?php if ( $n ) { echo '<div class="notice notice-' . esc_attr( $n[0] ) . ' is-dismissible"><p>' . esc_html( $n[1] ) . '</p></div>'; } ?>
		<div class="st"><?php echo $badge( '' !== $code, 'Access code set', 'No access code' ); echo $badge( $gate > 0, 'Gate page chosen', 'No gate page' ); echo $badge( ! empty( $ok ), 'Stripe connected', 'Stripe not connected' ); echo $badge( ! empty( $ok['identity'] ), 'Identity ready', 'Identity not confirmed' ); echo $badge( $hook, 'Webhook active', 'Webhook not set' ); ?></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ms_secret_admin"><?php wp_nonce_field( 'ms_secret_admin' ); ?>
			<div class="msa-card"><h2><i>1</i> Access code</h2><p class="d">Anyone who passes the 18+ screen and types this code can browse M'S SECRET. Change it whenever you like. Age-verified customers also get their own personal code by email.</p>
				<label class="l" for="c">Shared code</label><input id="c" type="text" name="ms_secret_code" value="<?php echo esc_attr( $code ); ?>" placeholder="e.g. VELVET25" autocomplete="off">
				<label class="l" for="g">Gate page</label><?php wp_dropdown_pages( [ 'name' => 'ms_secret_gate_page', 'id' => 'g', 'selected' => $gate, 'show_option_none' => '— choose —' ] ); ?><p class="description">The page with the unlock form (already set up; edit its design with Elementor).</p></div>
			<div class="msa-card"><h2><i>2</i> Connect Stripe (age verification)</h2><p class="d">Customers verify their ID and a selfie on Stripe's secure page. This site never stores the ID or birth date, only "verified" and the personal code.</p>
				<?php if ( $wc ) : ?><p><?php echo $badge( true, 'Using the key from the WooCommerce Stripe plugin', '' ); ?> <code><?php echo esc_html( $mask ); ?></code></p><?php endif; ?>
				<label class="l"><input type="checkbox" name="ms_secret_use_wc_key" value="1" <?php checked( 'no' !== get_option( 'ms_secret_use_wc_key', 'yes' ) ); ?>> Use the same Stripe key as WooCommerce → Settings → Payments → Stripe (recommended, no need to paste anything)</label>
				<label class="l" for="k">Or paste a different secret key <?php echo $own ? '(saved: ' . esc_html( $mask ) . ' – leave blank to keep)' : ''; ?></label><input id="k" type="password" name="ms_secret_stripe_sk" value="" placeholder="sk_live_… or rk_live_…" autocomplete="off">
				<?php if ( ! empty( $ok ) ) { echo '<p style="margin:14px 0 0">' . $badge( true, 'Connected to ' . ( $ok['name'] ?? 'Stripe' ), '' ) . ( ! empty( $ok['identity'] ) ? '' : ' <span class="msa-b no">Identity: ' . esc_html( $ok['identity_err'] ?: 'not available' ) . '</span>' ) . '</p>'; } ?>
				<div class="msa-row"><button class="button button-primary button-large" name="do" value="connect">Save &amp; connect to Stripe</button><button class="button button-large" name="do" value="save">Save only</button><?php if ( $own || ! empty( $ok ) ) { echo '<button class="button button-large" name="do" value="disconnect" onclick="return confirm(\'Disconnect Stripe?\')">Disconnect</button>'; } ?></div>
				<details><summary>Before connecting: 2-minute checklist</summary><ol>
					<li>In Stripe, open <b>Identity</b> and activate it (Stripe asks a few business questions).</li>
					<li>Use a key that can use Identity: the normal secret key works. A restricted key needs <i>Identity → Write</i> and <i>Webhook endpoints → Write</i>.</li>
					<li>The site must be live on <b>https</b>. Then <b>Save &amp; connect</b> creates the webhook for you.</li></ol></details>
				<details><summary>Manual webhook (only if automatic creation fails)</summary><p>In Stripe → Developers → Webhooks → Add endpoint. URL: <code><?php echo esc_html( ms_secret_webhook_url() ); ?></code>. Events: <code>identity.verification_session.verified</code> and <code>identity.verification_session.requires_input</code>. Paste its signing secret here:</p>
					<input type="password" name="ms_secret_webhook_secret_manual" form="msa-manual" style="max-width:460px;width:100%" placeholder="whsec_…"> </details></div>
			<div class="msa-card"><h2><i>3</i> Email</h2><p class="d">Personal codes are sent by email. Send yourself a test to confirm your site can deliver mail.</p><div class="msa-row"><button class="button button-large" name="do" value="email">Send me a test email</button></div></div>
			<div class="msa-card"><h2><i>4</i> Last Stripe message</h2><p class="d">What the site last received from Stripe. Useful when a customer's verification does not go through.</p><p><code style="user-select:text"><?php echo esc_html( get_option( 'ms_secret_last_event', 'Nothing received yet.' ) ); ?></code></p></div>
			<?php submit_button( 'Save settings', 'primary', 'do', false, [ 'value' => 'save' ] ); ?>
		</form>
		<form id="msa-manual" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ms_secret_manual_hook"><?php wp_nonce_field( 'ms_secret_manual' ); ?></form>
		<script>document.querySelectorAll('input[name=ms_secret_webhook_secret_manual]').forEach(function(i){i.addEventListener('change',function(){var f=document.getElementById('msa-manual');var h=document.createElement('input');h.type='hidden';h.name='v';h.value=i.value;f.appendChild(h);f.submit();});});</script>
	</div>
<?php }
add_action( 'admin_post_ms_secret_manual_hook', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'No permission' ); } check_admin_referer( 'ms_secret_manual' );
	update_option( 'ms_secret_webhook_secret', trim( sanitize_text_field( wp_unslash( $_POST['v'] ?? '' ) ) ) ); ms_secret_notice( 'success', 'Webhook secret saved.' ); wp_safe_redirect( admin_url( 'admin.php?page=ms-secret' ) ); exit;
} );

add_action( 'show_user_profile', 'ms_secret_profile' ); add_action( 'edit_user_profile', 'ms_secret_profile' );
function ms_secret_profile( $u ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; } ?>
	<h2>M'S SECRET</h2><table class="form-table">
	<tr><th>Age verified</th><td><label><input type="checkbox" name="ms_secret_verified" value="1" <?php checked( get_user_meta( $u->ID, 'ms_secret_verified', true ) ); ?>> Verified (uncheck to revoke access)</label></td></tr>
	<tr><th>Personal code</th><td><input type="text" name="ms_secret_code" value="<?php echo esc_attr( get_user_meta( $u->ID, 'ms_secret_code', true ) ); ?>" class="regular-text"> <label><input type="checkbox" name="ms_secret_regen" value="1"> Generate a new code and email it</label></td></tr></table>
<?php }
add_action( 'personal_options_update', 'ms_secret_profile_save' ); add_action( 'edit_user_profile_update', 'ms_secret_profile_save' );
function ms_secret_profile_save( $uid ) {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['_wpnonce'] ) ) { return; }
	if ( empty( $_POST['ms_secret_verified'] ) ) { delete_user_meta( $uid, 'ms_secret_verified' ); return; }
	update_user_meta( $uid, 'ms_secret_verified', 1 );
	if ( ! empty( $_POST['ms_secret_regen'] ) ) { ms_secret_generate_code( $uid ); ms_secret_email_code( $uid ); }
	else { update_user_meta( $uid, 'ms_secret_code', strtoupper( sanitize_text_field( $_POST['ms_secret_code'] ?? '' ) ) ); }
}

add_action( 'wp_head', function () { ?>
<style>.ms-gate{max-width:520px;margin:0 auto;padding:40px 32px;text-align:center;background:#0d0d0d;color:#fff;border:1px solid #c9a24a;border-radius:14px}
.ms-gate h3{color:#fff;font-family:inherit;margin-top:0}.ms-gate input[type=text]{width:100%;padding:14px;margin:12px 0;border-radius:8px;border:1px solid #444;background:#1a1a1a;color:#fff;text-align:center;letter-spacing:.2em;text-transform:uppercase}
.ms-gate label{display:block;margin:16px 0;font-size:.95em;color:#fff}.ms-gate input[type=checkbox]{accent-color:#e0115f}.ms-btn{display:inline-block;background:#e0115f;color:#fff!important;border:0;border-radius:999px;padding:14px 34px;font-weight:600;cursor:pointer;text-decoration:none}
.ms-btn:hover{background:#c9a24a}.ms-error{color:#ff6b8a}.ms-note{font-size:.85em;opacity:.8;margin-top:22px}</style>
<?php } );

/** The gate and secret pages depend on who is looking (cookies), so no cache (host CDN, LiteSpeed, browser) may store them. */
add_action( 'template_redirect', function () {
	$gate = (int) get_option( 'ms_secret_gate_page' ); $ids = [];
	if ( $gate ) { $ids[] = $gate; if ( function_exists( 'pll_get_post_translations' ) ) { $ids = array_merge( $ids, array_values( pll_get_post_translations( $gate ) ) ); } }
	$secret = is_page( $ids ) || is_tax( 'product_cat', ms_secret_slugs() ) || ( is_singular( 'product' ) && has_term( ms_secret_slugs(), 'product_cat' ) );
	if ( ! $secret ) { return; }
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	do_action( 'litespeed_control_set_nocache', 'M\'S SECRET page' );
	nocache_headers(); header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private', true ); header( 'Pragma: no-cache' ); header( 'CDN-Cache-Control: no-store' );
}, 1 );

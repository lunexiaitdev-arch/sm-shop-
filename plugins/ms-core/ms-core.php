<?php
/**
 * Plugin Name: MS Collection Core
 * Description: Small glue between Polylang (EN/ES) and WooCommerce/Elementor. All design lives in Astra + Elementor.
 * Version: 1.0.0
 */
defined( 'ABSPATH' ) || exit;

// Polylang (free): make products and their categories/tags translatable (a Spanish and an English version of each).
add_filter( 'pll_get_post_types', function ( $types ) { $types['product'] = 'product'; return $types; } );
add_filter( 'pll_get_taxonomies', function ( $tax ) { $tax['product_cat'] = 'product_cat'; $tax['product_tag'] = 'product_tag'; return $tax; } );

// Shop / Cart / Checkout / My account follow the visitor's language (each has a Spanish and an English page).
foreach ( [ 'shop', 'cart', 'checkout', 'myaccount', 'terms' ] as $ms_page ) {
	add_filter( 'woocommerce_get_' . $ms_page . '_page_id', function ( $id ) {
		return ( $id > 0 && function_exists( 'pll_get_post' ) && pll_get_post( $id ) ) ? pll_get_post( $id ) : $id;
	} );
}

// One Elementor header/footer, two menus: the English menus ("main-menu", "footer-menu") are served on English pages. Edit both in Appearance → Menus.
add_filter( 'wp_nav_menu_args', function ( $args ) {
	$menu = $args['menu'] ?? '';
	$slug = is_object( $menu ) ? $menu->slug : ( is_numeric( $menu ) ? (string) ( wp_get_nav_menu_object( (int) $menu )->slug ?? '' ) : (string) $menu );
	$map = [ 'menu-principal' => 'main-menu', 'menu-pie' => 'footer-menu' ];
	if ( isset( $map[ $slug ] ) && function_exists( 'pll_current_language' ) && 'en' === pll_current_language() ) { $args['menu'] = $map[ $slug ]; }
	return $args;
} );

// Polylang (free) cannot translate the shop archive slug, so the English "Shop" page is a normal page. Turn its main query into the
// product archive (like WooCommerce does for a front-page shop) so the Theme Builder shop template and loop grid receive the products.
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! function_exists( 'wc_get_page_id' ) || ! function_exists( 'pll_get_post' ) ) { return; }
	$path = (string) $q->get( 'pagename' );
	if ( '' === $path ) { return; }
	foreach ( [ 'es', 'en' ] as $lang ) {
		$shop = pll_get_post( wc_get_page_id( 'shop' ), $lang );
		if ( $shop && get_page_uri( $shop ) === $path && 'product' !== $q->get( 'post_type' ) && $shop !== (int) get_option( 'woocommerce_shop_page_id' ) ) {
			$q->set( 'post_type', 'product' ); $q->set( 'pagename', '' ); $q->set( 'page_id', '' );
			$q->is_page = false; $q->is_singular = false; $q->is_archive = true; $q->is_post_type_archive = true; $q->is_home = false;
			$q->queried_object = get_post_type_object( 'product' ); $q->queried_object_id = 0;
			add_action( 'wp', function () use ( $shop ) { $GLOBALS['ms_shop_page_id'] = $shop; } );
			return;
		}
	}
}, 5 );

/* ---------------------------------------------------------------- Product page helpers */

// [ms_reviews] prints the WooCommerce reviews list + form, so the Elementor product template can place it in its own section.
add_shortcode( 'ms_reviews', function () {
	global $product, $post;
	if ( ! $product || ! comments_open() ) { return ''; }
	ob_start(); comments_template(); return '<div class="ms-reviews">' . ob_get_clean() . '</div>';
} );
// The template shows reviews in their own section, so drop the duplicate tab.
add_filter( 'woocommerce_product_tabs', function ( $tabs ) { unset( $tabs['reviews'] ); return $tabs; }, 98 );

// Colour swatches for the variation dropdown (the dropdown stays in the page for WooCommerce; swatches just drive it).
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	?>
<script>
(function(){
	var map={negro:'#111111',black:'#111111',azul:'#7DB4EC',blue:'#7DB4EC',rojo:'#E0161C',red:'#E0161C',rosa:'#F0147D',pink:'#F0147D',crema:'#EDE5D6',cream:'#EDE5D6'};
	function build(form){
		var sel=form.querySelector('select[name^="attribute_"]'); if(!sel||form.querySelector('.ms-swatches')) return;
		var wrap=document.createElement('div'); wrap.className='ms-swatches';
		var label=document.createElement('div'); label.className='ms-sw-label'; label.innerHTML='<span></span><b></b>'; wrap.appendChild(label);
		var list=document.createElement('div'); list.className='ms-sw-list'; wrap.appendChild(list);
		Array.prototype.forEach.call(sel.options,function(o){
			if(!o.value) return;
			var b=document.createElement('button'); b.type='button'; b.className='ms-sw'; b.dataset.v=o.value; b.title=o.text;
			var c=map[o.text.toLowerCase()]; if(c){b.style.setProperty('--c',c);b.innerHTML='<i></i>';} else {b.className+=' ms-sw-text';b.textContent=o.text;}
			b.addEventListener('click',function(){ sel.value=o.value; window.jQuery&&window.jQuery(sel).trigger('change'); sync(); });
			list.appendChild(b);
		});
		sel.parentNode.insertBefore(wrap,sel); sel.classList.add('ms-hidden-select');
		var th=form.querySelector('th.label label, .label label'); label.querySelector('span').textContent=th?th.textContent:'';
		function sync(){
			var v=sel.value; list.querySelectorAll('.ms-sw').forEach(function(x){x.classList.toggle('is-active',x.dataset.v===v);});
			var cur=sel.options[sel.selectedIndex]; label.querySelector('b').textContent=v&&cur?cur.text:'';
		}
		sel.addEventListener('change',sync); window.jQuery&&window.jQuery(form).on('reset_data',function(){setTimeout(sync,0)}); sync();
	}
	function init(){document.querySelectorAll('form.variations_form').forEach(build);}
	if(document.readyState!=='loading') init(); else document.addEventListener('DOMContentLoaded',init);
	window.addEventListener('load',init);
})();
</script>
	<?php
} );

// Product pages are designed fully in Elementor (Theme Builder): let Elementor own the full width instead of Astra's boxed container.
add_filter( 'astra_get_content_layout', function ( $layout ) { return ( function_exists( 'is_product' ) && is_product() ) ? 'page-builder' : $layout; }, 20 );
add_filter( 'astra_page_layout', function ( $layout ) { return ( function_exists( 'is_product' ) && is_product() ) ? 'no-sidebar' : $layout; }, 20 );

// Theme Builder condition "Product (English visitors)". Elementor Pro remaps category conditions between Polylang languages,
// so a language check is the reliable way to give English product pages their own (English) template.
add_action( 'elementor/theme/register_conditions', function ( $manager ) {
	if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base' ) ) { return; }
	if ( ! class_exists( 'MS_Condition_Product_EN' ) ) {
		class MS_Condition_Product_EN extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {
			public static function get_type() { return 'singular'; }
			public static function get_priority() { return 30; }
			public function get_name() { return 'ms_product_en'; }
			public function get_label() { return 'Product page (English visitors)'; }
			public function get_all_label() { return 'All product pages (English visitors)'; }
			public function check( $args ) { return is_singular( 'product' ) && function_exists( 'pll_current_language' ) && 'en' === pll_current_language(); }
		}
	}
	$singular = $manager->get_condition( 'singular' );
	if ( $singular ) { $singular->register_sub_condition( new MS_Condition_Product_EN() ); }
} );

// [ms_rating_summary]: big average + distribution bars for the current product.
add_shortcode( 'ms_rating_summary', function () {
	global $product; if ( ! $product ) { return ''; }
	$n = (int) $product->get_review_count(); if ( ! $n ) { return ''; }
	$avg = (float) $product->get_average_rating(); $counts = $product->get_rating_counts(); $bars = '';
	for ( $s = 5; $s >= 1; $s-- ) { $c = (int) ( $counts[ $s ] ?? 0 ); $bars .= '<div class="ms-bar"><span>' . $s . '★</span><i><b style="width:' . round( $c / $n * 100 ) . '%"></b></i><em>' . $c . '</em></div>'; }
	return '<div class="ms-rsum"><div class="ms-rsum-n"><strong>' . number_format_i18n( $avg, 1 ) . '</strong>' . wc_get_rating_html( $avg ) . '<small>' . ( ( function_exists( 'pll_current_language' ) && 'en' === pll_current_language() ) ? sprintf( _n( '%d review', '%d reviews', $n ), $n ) : sprintf( _n( '%d valoración', '%d valoraciones', $n ), $n ) ) . '</small></div><div class="ms-rsum-b">' . $bars . '</div></div>';
} );

/* ---------------------------------------------------------------- Shop toolbar: [ms_shop_toolbar mode="shop|secret"] */
add_shortcode( 'ms_shop_toolbar', function ( $atts ) {
	$a    = shortcode_atts( [ 'mode' => 'shop' ], $atts );
	$en   = function_exists( 'pll_current_language' ) && 'en' === pll_current_language();
	$t    = $en ? [ 'all' => 'All', 'showing' => 'Showing %1$d–%2$d of %3$d results', 'one' => '1 result', 'none' => 'No products found', 'sort' => 'Sort by', 'min' => 'Min', 'max' => 'Max', 'price' => 'Price', 'apply' => 'Filter', 'clear' => 'Clear filters',
		'o' => [ 'menu_order' => 'Featured', 'popularity' => 'Popularity', 'rating' => 'Best rated', 'date' => 'Newest', 'price' => 'Price: low to high', 'price-desc' => 'Price: high to low' ] ]
		: [ 'all' => 'Todos', 'showing' => 'Mostrando %1$d–%2$d de %3$d resultados', 'one' => '1 resultado', 'none' => 'No se encontraron productos', 'sort' => 'Ordenar por', 'min' => 'Mín', 'max' => 'Máx', 'price' => 'Precio', 'apply' => 'Filtrar', 'clear' => 'Quitar filtros',
		'o' => [ 'menu_order' => 'Destacados', 'popularity' => 'Popularidad', 'rating' => 'Mejor valorados', 'date' => 'Más recientes', 'price' => 'Precio: de menor a mayor', 'price-desc' => 'Precio: de mayor a menor' ] ];
	global $wp_query;
	$shop_id = function_exists( 'pll_get_post' ) ? pll_get_post( wc_get_page_id( 'shop' ) ) : wc_get_page_id( 'shop' );
	$shop    = $shop_id ? get_permalink( $shop_id ) : home_url( '/' );
	$base    = is_tax( 'product_cat' ) ? get_term_link( get_queried_object() ) : $shop;
	$cur     = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'menu_order';
	$min = isset( $_GET['min_price'] ) ? (float) $_GET['min_price'] : ''; $max = isset( $_GET['max_price'] ) ? (float) $_GET['max_price'] : '';
	$total = (int) $wp_query->found_posts; $per = max( 1, (int) $wp_query->get( 'posts_per_page' ) ); $paged = max( 1, (int) $wp_query->get( 'paged' ) );
	$from = $total ? ( $paged - 1 ) * $per + 1 : 0; $to = min( $total, $paged * $per );
	$count = ! $total ? $t['none'] : ( 1 === $total ? $t['one'] : sprintf( $t['showing'], $from, $to, $total ) );
	$pills = '';
	if ( 'shop' === $a['mode'] ) {
		$pills = '<nav class="ms-pills"><a class="' . ( is_tax( 'product_cat' ) ? '' : 'is-on' ) . '" href="' . esc_url( $shop ) . '">' . esc_html( $t['all'] ) . '</a>';
		foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'exclude' => [ (int) get_option( 'default_product_cat' ) ] ] ) as $term ) {
			if ( is_wp_error( $term ) || in_array( $term->slug, ms_secret_slugs(), true ) ) { continue; }
			$pills .= '<a class="' . ( is_tax( 'product_cat', $term->term_id ) ? 'is-on' : '' ) . '" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . ' <small>' . (int) $term->count . '</small></a>';
		}
		$pills .= '</nav>';
	}
	$opts = ''; foreach ( $t['o'] as $k => $l ) { $opts .= '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>'; }
	$active = ( '' !== $min || '' !== $max );
	return '<div class="ms-toolbar ms-toolbar--' . esc_attr( $a['mode'] ) . '">' . $pills .
		'<form class="ms-tools" method="get" action="' . esc_url( $base ) . '"><span class="ms-count">' . esc_html( $count ) . '</span>' .
		'<label class="ms-price"><b>' . esc_html( $t['price'] ) . '</b><input type="number" min="0" step="1" name="min_price" placeholder="' . esc_attr( $t['min'] ) . '" value="' . esc_attr( $min ) . '"><i>–</i><input type="number" min="0" step="1" name="max_price" placeholder="' . esc_attr( $t['max'] ) . '" value="' . esc_attr( $max ) . '"><button type="submit">' . esc_html( $t['apply'] ) . '</button></label>' .
		'<label class="ms-sort"><b>' . esc_html( $t['sort'] ) . '</b><select name="orderby" onchange="this.form.submit()">' . $opts . '</select></label>' .
		( $active || 'menu_order' !== $cur ? '<a class="ms-clear" href="' . esc_url( $base ) . '">' . esc_html( $t['clear'] ) . '</a>' : '' ) . '</form></div>';
} );

// The Elementor loop grid (current query) re-runs the main query, so hand it WooCommerce's sorting and price filter.
add_filter( 'elementor/query/get_query_args/current_query', function ( $vars ) {
	if ( ! function_exists( 'WC' ) || ! ( is_shop() || is_product_taxonomy() || ( isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query']->is_post_type_archive( 'product' ) ) ) ) { return $vars; }
	$o = WC()->query->get_catalog_ordering_args();
	$vars['orderby'] = $o['orderby']; $vars['order'] = $o['order'];
	if ( ! empty( $o['meta_key'] ) ) { $vars['meta_key'] = $o['meta_key']; }
	$min = isset( $_GET['min_price'] ) && '' !== $_GET['min_price'] ? (float) $_GET['min_price'] : null;
	$max = isset( $_GET['max_price'] ) && '' !== $_GET['max_price'] ? (float) $_GET['max_price'] : null;
	if ( null !== $min || null !== $max ) {
		$vars['meta_query']   = (array) ( $vars['meta_query'] ?? [] );
		$vars['meta_query'][] = [ 'key' => '_price', 'value' => [ $min ?? 0, $max ?? 999999 ], 'type' => 'NUMERIC', 'compare' => 'BETWEEN' ];
	}
	return $vars;
} );

// Shop, category and tag pages are Elementor Theme Builder templates too: full width, no sidebar.
$ms_wc_archive = function () { return function_exists( 'is_woocommerce' ) && ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) ); };
add_filter( 'astra_get_content_layout', function ( $layout ) use ( $ms_wc_archive ) { return $ms_wc_archive() ? 'page-builder' : $layout; }, 20 );
add_filter( 'astra_page_layout', function ( $layout ) use ( $ms_wc_archive ) { return $ms_wc_archive() ? 'no-sidebar' : $layout; }, 20 );

// Header account icon → My account (language-aware via the Polylang page mapping above).
add_shortcode( 'ms_account_link', function () {
	if ( ! function_exists( 'wc_get_page_permalink' ) ) { return ''; }
	$en = function_exists( 'pll_current_language' ) && 'en' === pll_current_language();
	$label = is_user_logged_in() ? ( $en ? 'My account' : 'Mi cuenta' ) : ( $en ? 'Log in / Register' : 'Acceder / Registrarse' );
	return '<a class="ms-acct' . ( is_user_logged_in() ? ' is-in' : '' ) . '" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></a>';
} );

/* ---------------------------------------------------------------- Account: premium dashboard + login/register tabs */
add_action( 'woocommerce_account_dashboard', function () {
	$u = wp_get_current_user(); $en = function_exists( 'pll_current_language' ) && 'en' === pll_current_language();
	$name = $u->first_name ?: $u->display_name;
	$n = (int) wc_get_customer_order_count( $u->ID );
	$verified = (bool) get_user_meta( $u->ID, 'ms_secret_verified', true ); $code = (string) get_user_meta( $u->ID, 'ms_secret_code', true );
	$t = $en ? [ 'hi' => 'Welcome back', 'sub' => 'Manage your orders, addresses and details from one place.', 'orders' => 'Orders', 'o_d' => $n ? sprintf( _n( '%d order placed', '%d orders placed', $n ), $n ) : 'No orders yet', 'addr' => 'Addresses', 'a_d' => 'Shipping and billing', 'acc' => 'Account details', 'c_d' => 'Name, email and password', 'sec' => "M'S SECRET", 's_ok' => 'Age verified', 's_no' => 'Verify your age to get your code', 'code' => 'Your code', 'out' => 'Log out', 'shop' => 'Continue shopping' ]
		: [ 'hi' => 'Bienvenida de nuevo', 'sub' => 'Gestiona tus pedidos, direcciones y datos desde un solo lugar.', 'orders' => 'Pedidos', 'o_d' => $n ? sprintf( _n( '%d pedido realizado', '%d pedidos realizados', $n ), $n ) : 'Aún no tienes pedidos', 'addr' => 'Direcciones', 'a_d' => 'Envío y facturación', 'acc' => 'Detalles de la cuenta', 'c_d' => 'Nombre, correo y contraseña', 'sec' => "M'S SECRET", 's_ok' => 'Edad verificada', 's_no' => 'Verifica tu edad para recibir tu código', 'code' => 'Tu código', 'out' => 'Cerrar sesión', 'shop' => 'Seguir comprando' ];
	$ic = [ 'orders' => '<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>', 'addr' => '<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>', 'acc' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>', 'sec' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>' ];
	$svg = function ( $k ) use ( $ic ) { return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $ic[ $k ] . '</svg>'; };
	$gate = (int) get_option( 'ms_secret_gate_page' ); if ( $gate && function_exists( 'pll_get_post' ) ) { $gate = pll_get_post( $gate ) ?: $gate; }
	$tile = function ( $k, $url, $title, $desc, $extra = '' ) use ( $svg ) { return '<a class="ms-tile2' . ( 'sec' === $k ? ' ms-tile2--secret' : '' ) . '" href="' . esc_url( $url ) . '"><span class="ms-tile2-i">' . $svg( $k ) . '</span><b>' . esc_html( $title ) . '</b><small>' . esc_html( $desc ) . '</small>' . $extra . '</a>'; };
	echo '<div class="ms-dash"><div class="ms-dash-hero"><span class="ms-dash-av">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span><div><small>' . esc_html( $t['hi'] ) . '</small><h3>' . esc_html( $name ) . '</h3><p>' . esc_html( $t['sub'] ) . '</p></div><a class="ms-dash-shop" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html( $t['shop'] ) . ' →</a></div><div class="ms-tiles2">' .
		$tile( 'orders', wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ), $t['orders'], $t['o_d'] ) .
		$tile( 'addr', wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) ), $t['addr'], $t['a_d'] ) .
		$tile( 'acc', wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) ), $t['acc'], $t['c_d'] ) .
		$tile( 'sec', $gate ? get_permalink( $gate ) : '#', $t['sec'], $verified ? $t['s_ok'] : $t['s_no'], $verified && $code ? '<em>' . esc_html( $t['code'] ) . ': <strong>' . esc_html( $code ) . '</strong></em>' : '' ) .
		'</div></div>';
}, 5 );

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || is_user_logged_in() ) { return; }
	$en = function_exists( 'pll_current_language' ) && 'en' === pll_current_language();
	?>
<script>
(function(){
	var cols=document.querySelector('.u-columns'); if(!cols||cols.dataset.tabs) return; cols.dataset.tabs=1;
	var login=cols.querySelector('.u-column1'), reg=cols.querySelector('.u-column2'); if(!login||!reg) return;
	var tabs=document.createElement('div'); tabs.className='ms-auth-tabs';
	tabs.innerHTML='<button type="button" data-t="login"><?php echo $en ? 'Log in' : 'Acceder'; ?></button><button type="button" data-t="reg"><?php echo $en ? 'Create account' : 'Crear cuenta'; ?></button>';
	cols.parentNode.insertBefore(tabs,cols);
	function show(t){ login.style.display=t==='login'?'block':'none'; reg.style.display=t==='reg'?'block':'none'; tabs.querySelectorAll('button').forEach(function(b){b.classList.toggle('is-on',b.dataset.t===t)}); }
	tabs.addEventListener('click',function(e){var b=e.target.closest('button'); if(b) show(b.dataset.t);});
	var errReg=reg.querySelector('.woocommerce-error')||document.querySelector('.woocommerce-error')&&/register|registr|email/i.test(document.querySelector('.woocommerce-error').textContent)&&!login.querySelector('input[name=username]').value;
	show(location.hash==='#register'||/[?&]register/.test(location.search)||errReg?'reg':'login');
})();
</script>
	<?php
} );

// Spanish fallback for the few Elementor Pro labels shown to shoppers, in case its language pack is not installed (it needs a licensed site).
add_filter( 'gettext', function ( $t, $orig, $domain ) {
	if ( 'elementor-pro' !== $domain || ! function_exists( 'pll_current_language' ) || 'es' !== pll_current_language() ) { return $t; }
	static $m = [ 'Category' => 'Categoría', 'Categories' => 'Categorías', 'Tag' => 'Etiqueta', 'Tags' => 'Etiquetas', 'SKU' => 'SKU', 'Unknown' => 'N/D' ];
	return ( $t === $orig && isset( $m[ $orig ] ) ) ? $m[ $orig ] : $t;
}, 10, 3 );

// Login / registration page layout (logged-out visitors): single column inside the account widget. Kept here so it always loads after the other styles.
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || is_user_logged_in() ) { return; }
	echo '<style id="ms-login-fix">
	body:not(.logged-in) .ms-shop-body .elementor-widget-woocommerce-my-account .woocommerce{display:block!important;gap:0!important}
	body:not(.logged-in) .ms-acct-main{min-width:0}
	body:not(.logged-in) .ms-acct-main .ms-auth-tabs{display:flex;width:100%;max-width:100%;box-sizing:border-box}
	body:not(.logged-in) .ms-acct-main .u-columns{display:block!important;width:100%!important}
	body:not(.logged-in) .ms-acct-main .u-columns .u-column1,body:not(.logged-in) .ms-acct-main .u-columns .u-column2{width:100%!important;max-width:100%!important;box-sizing:border-box;float:none!important}
	body:not(.logged-in) .ms-acct-row{align-items:stretch}
	body:not(.logged-in) .ms-acct-side{align-self:stretch;position:relative;top:auto}
	@media(min-width:1025px){body:not(.logged-in) .ms-acct-row{flex-wrap:nowrap!important}body:not(.logged-in) .ms-acct-side{flex:0 0 38%;max-width:38%}body:not(.logged-in) .ms-acct-main{flex:1 1 0}}
	@media(max-width:1024px){body:not(.logged-in) .ms-acct-side{min-height:300px;width:100%!important}body:not(.logged-in) .ms-acct-main{width:100%!important}}
	@media(max-width:600px){.ms-auth-tabs button{padding:13px 8px;letter-spacing:.1em;font-size:11px}}
	body:not(.logged-in) .ms-acct-main .woocommerce-ResetPassword,body:not(.logged-in) .ms-acct-main form.lost_reset_password{display:block;width:100%!important;max-width:100%;box-sizing:border-box;background:#fff;border-radius:26px;box-shadow:0 22px 60px rgba(0,0,0,.08);padding:46px 48px!important;margin:0!important;float:none!important}
	body:not(.logged-in) .ms-acct-main .lost_reset_password::before{content:attr(data-ms-title);display:block;font:600 32px \'Playfair Display\',serif;color:#0B0B0B;margin-bottom:12px}
	body:not(.logged-in) .ms-acct-main .lost_reset_password>p:first-of-type{font:500 15px/1.7 Montserrat,sans-serif;color:#6B6256;margin:0 0 24px}
	body:not(.logged-in) .ms-acct-main .lost_reset_password .form-row{width:100%!important;float:none!important;padding:0;margin:0 0 20px}
	body:not(.logged-in) .ms-acct-main .lost_reset_password .woocommerce-Button,body:not(.logged-in) .ms-acct-main .lost_reset_password button[type=submit]{width:100%;background:#E0115F!important;color:#fff!important}
	body:not(.logged-in) .ms-acct-main .lost_reset_password input.input-text{width:100%}
	body:not(.logged-in) .ms-acct-main .woocommerce-message,body:not(.logged-in) .ms-acct-main .woocommerce-error{margin:0 0 20px}
	@media(max-width:600px){body:not(.logged-in) .ms-acct-main form.lost_reset_password{padding:30px 22px!important}}
	</style>';
	$en = function_exists( 'pll_current_language' ) && 'en' === pll_current_language();
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var f=document.querySelector("form.lost_reset_password");if(f)f.setAttribute("data-ms-title",' . wp_json_encode( $en ? 'Forgot your password?' : '¿Olvidaste tu contraseña?' ) . ');});</script>';
}, 99 );

// Spanish for Elementor Pro (cart / checkout / account widgets). Its pack only downloads for licensed sites, so ship a copy and use it when the official one is missing.
add_filter( 'load_textdomain_mofile', function ( $mofile, $domain ) {
	if ( 'elementor-pro' === $domain && 0 === strpos( determine_locale(), 'es' ) && ! file_exists( $mofile ) ) { return plugin_dir_path( __FILE__ ) . 'languages/elementor-pro-es_ES.mo'; }
	return $mofile;
}, 10, 2 );

// Checkout: give the order summary room (product names were wrapping over 4 lines).
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
	echo '<style id="ms-checkout-fix">
	@media(min-width:1025px){.ms-shop-body .e-checkout__container{display:grid!important;grid-template-columns:minmax(0,1.15fr) minmax(380px,.85fr);gap:32px;align-items:start}.ms-shop-body .e-checkout__container .e-checkout__column{width:auto!important;max-width:none!important}}
	.ms-shop-body .woocommerce-checkout-review-order-table .product-name{width:60%}
	.ms-shop-body .woocommerce-checkout-review-order-table td,.ms-shop-body .woocommerce-checkout-review-order-table th{vertical-align:top}
	.ms-shop-body .woocommerce-checkout-review-order-table #shipping_method{list-style:none;margin:0;padding:0;text-align:right}
	.ms-shop-body .woocommerce-checkout-review-order-table #shipping_method li{margin:0 0 6px}
	</style>';
}, 99 );

/* ---------------------------------------------------------------- Emails: customer's language + brand */

// Remember the language the customer used (orders and new accounts).
add_action( 'woocommerce_checkout_create_order', function ( $order ) { if ( function_exists( 'pll_current_language' ) ) { $order->update_meta_data( '_ms_lang', pll_current_language() ?: 'es' ); } } );
add_action( 'user_register', function ( $uid ) { if ( function_exists( 'pll_current_language' ) && pll_current_language() ) { update_user_meta( $uid, 'ms_lang', pll_current_language() ); } } );

// WooCommerce builds each email's default subject/heading once at start-up; rebuild them after a language switch.
function ms_email_reinit() {
	if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) { return; }
	foreach ( WC()->mailer()->get_emails() as $e ) { if ( method_exists( $e, 'init_form_fields' ) ) { $e->init_form_fields(); $e->init_settings(); if ( method_exists( $e, 'get_default_subject' ) ) { $e->subject = $e->get_default_subject(); $e->heading = $e->get_default_heading(); } } }
}
function ms_email_lang_for( $arg ) {
	if ( is_array( $arg ) ) { $arg = $arg['order_id'] ?? 0; }
	$arg = (int) $arg; if ( ! $arg ) { return ''; }
	$o = function_exists( 'wc_get_order' ) ? wc_get_order( $arg ) : null;
	if ( $o ) { return (string) $o->get_meta( '_ms_lang' ); }
	return (string) get_user_meta( $arg, 'ms_lang', true );
}
// Wrap every transactional email so it is built in that language, then restore.
add_action( 'init', function () {
	$hooks = [ 'woocommerce_order_status_pending_to_processing_notification', 'woocommerce_order_status_pending_to_completed_notification', 'woocommerce_order_status_pending_to_on-hold_notification', 'woocommerce_order_status_failed_to_processing_notification', 'woocommerce_order_status_failed_to_completed_notification', 'woocommerce_order_status_failed_to_on-hold_notification', 'woocommerce_order_status_cancelled_to_processing_notification', 'woocommerce_order_status_cancelled_to_completed_notification', 'woocommerce_order_status_cancelled_to_on-hold_notification', 'woocommerce_order_status_on-hold_to_processing_notification', 'woocommerce_order_status_processing_to_cancelled_notification', 'woocommerce_order_status_on-hold_to_cancelled_notification', 'woocommerce_order_status_completed_notification', 'woocommerce_order_status_failed_notification', 'woocommerce_order_fully_refunded_notification', 'woocommerce_order_partially_refunded_notification', 'woocommerce_new_customer_note_notification', 'woocommerce_created_customer_notification' ];
	foreach ( $hooks as $h ) {
		add_action( $h, function ( $arg = 0 ) { $l = ms_email_lang_for( $arg ); if ( $l ) { $t = 'en' === $l ? 'en_US' : 'es_ES'; $GLOBALS['ms_email_switched'] = switch_to_locale( $t ); $GLOBALS['ms_force_locale'] = $t; ms_email_reinit(); } }, 1 );
		add_action( $h, function () { if ( ! empty( $GLOBALS['ms_email_switched'] ) ) { restore_previous_locale(); $GLOBALS['ms_email_switched'] = false; unset( $GLOBALS['ms_force_locale'] ); ms_email_reinit(); } unset( $GLOBALS['ms_force_locale'] ); }, 9999 );
	}
} );
// Admin preview in a chosen language: add &ms_lang=en|es to WooCommerce's email preview URL.
add_action( 'init', function () { if ( isset( $_GET['preview_woocommerce_mail'], $_GET['ms_lang'] ) && current_user_can( 'manage_woocommerce' ) ) { $t = 'en' === $_GET['ms_lang'] ? 'en_US' : 'es_ES'; switch_to_locale( $t ); $GLOBALS['ms_force_locale'] = $t; } }, 0 );
// Polylang filters the locale for the page language; while an email is built, the customer's language wins.
add_filter( 'locale', function ( $l ) { return ! empty( $GLOBALS['ms_force_locale'] ) ? $GLOBALS['ms_force_locale'] : $l; }, 99999 );

// Send from the shop's own domain (Gmail/Outlook reject a Gmail "from" sent by another server) but let customers reply to the shop owner.
add_filter( 'woocommerce_email_headers', function ( $h, $id, $object, $email = null ) { return $h . 'Reply-To: ' . get_option( 'admin_email' ) . "\r\n"; }, 10, 4 );

// Brand styling for all WooCommerce emails.
add_filter( 'woocommerce_email_styles', function ( $css ) {
	return $css . '
	#wrapper{background:#F6F1EA}
	#template_header_image{background:#0B0B0B!important;padding:30px 20px 0!important;text-align:center!important;border-radius:18px 18px 0 0}
	#template_header_image p{margin:0!important}
	#template_header_image a,#template_header_image p a{color:#C9A24A!important;font-family:Georgia,"Times New Roman",serif!important;font-size:13px!important;letter-spacing:.34em!important;text-transform:uppercase;text-decoration:none!important}
	#template_header{background:#0B0B0B!important;border-bottom:3px solid #C9A24A;border-radius:0!important}
	#template_header h1{padding-top:4px!important}
	#template_header,#header_wrapper,#template_header td,#template_header table{background:#0B0B0B!important;border-radius:0!important}
	#template_header_image{text-align:left!important;padding:28px 32px 0!important}
	#header_wrapper{padding:4px 32px 24px!important}
	#template_header h1{font-family:Georgia,"Times New Roman",serif!important;letter-spacing:.04em;color:#fff!important;text-shadow:none!important}
	#template_container{border:0!important;box-shadow:0 14px 40px rgba(0,0,0,.08)!important;border-radius:18px!important;overflow:hidden}
	#body_content{background:#fff}
	#body_content_inner h2{font-family:Georgia,serif!important;color:#0B0B0B!important}
	.td{border-color:#EDE6DA!important}
	a{color:#E0115F!important}
	#template_footer #credit{color:#9A8F80!important;font-size:12px}
	';
} );

// Product page: the photo scrolls normally (the sticky gallery slid under the header at the end of the page), and the add-to-cart button keeps the brand colour in every state.
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	echo '<style id="ms-pdp-final">
	.ms-pdp-gallery,.ms-pdp-gallery.elementor-sticky,.ms-pdp-gallery.elementor-sticky--active,.ms-pdp-gallery.elementor-sticky--effects{position:relative!important;top:auto!important;bottom:auto!important;left:auto!important;width:auto!important;transform:none!important;margin-top:0!important}
	.elementor-sticky__spacer:has(.ms-pdp-gallery){display:contents!important;width:auto!important;height:auto!important}
	.single_add_to_cart_button,.single_add_to_cart_button.added,.single_add_to_cart_button.loading,.single_add_to_cart_button:focus{background:#E0115F!important;color:#fff!important;border-color:#E0115F!important}
	.single_add_to_cart_button.added::after,.single_add_to_cart_button.loading::after{display:none!important}
	.single_add_to_cart_button.disabled,.single_add_to_cart_button:disabled{background:#F08BB0!important}
	.woocommerce-product-gallery{overflow:hidden!important;max-width:100%}
	.woocommerce-product-gallery .flex-viewport{overflow:hidden!important;max-width:100%}
	html,body{overflow-x:clip}
	.added_to_cart{display:inline-block;margin-top:10px;font:600 12px Montserrat,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#E0115F!important}
	</style>';
}, 99 );

// Mobile polish (site-wide). Printed late so it wins over Elementor/Astra defaults.
add_action( 'wp_head', function () {
	echo '<style id="ms-mobile-polish">
	/* notices: no overlapping icon, no browser focus box */
	.woocommerce-message,.woocommerce-info,.woocommerce-error{outline:0!important}
	.woocommerce-message::before,.woocommerce-info::before,.woocommerce-error::before{display:none!important;content:none!important}
	.woocommerce-notices-wrapper .woocommerce-message,.woocommerce-notices-wrapper .woocommerce-info,.woocommerce-notices-wrapper .woocommerce-error{padding:18px 22px!important;margin:0 0 18px}
	@media(max-width:767px){
	/* shop toolbar */
	.ms-price{flex-wrap:wrap;gap:8px}
	.ms-price b{width:100%;margin:0 0 2px}
	.ms-price input{flex:1 1 0;min-width:0;width:auto!important;-moz-appearance:textfield;appearance:textfield}
	.ms-price input::-webkit-outer-spin-button,.ms-price input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
	.ms-price i{margin:0 2px}
	.ms-price button{flex:0 0 auto}
	.ms-sort{flex-wrap:wrap;gap:6px}.ms-sort b{width:100%;margin:0}.ms-sort select{width:100%!important}
	/* eyebrow labels: no wrapping rules */
	.ms-eyebrow .elementor-heading-title{flex-wrap:wrap;justify-content:center;gap:0;letter-spacing:.2em!important;line-height:1.7}
	.ms-eyebrow .elementor-heading-title::before,.ms-eyebrow .elementor-heading-title::after{display:none}
	/* product page */
	.single-product .e-parent:has(.ms-pdp-gallery){padding-left:18px!important;padding-right:18px!important}
	.single-product .elementor-widget-woocommerce-product-title .elementor-heading-title{font-size:32px!important;line-height:1.12!important}
	.single-product .e-con:has(>.elementor-widget-woocommerce-product-title){gap:14px!important}
	.elementor-widget-woocommerce-product-add-to-cart .single_add_to_cart_button{letter-spacing:.12em;padding:16px 14px!important;font-size:12px!important}
	/* cart: tidy responsive rows */
	.ms-shop-body .shop_table_responsive tr{position:relative;display:block}
	.ms-shop-body .shop_table_responsive td.product-remove{position:absolute;top:14px;right:6px;width:auto;border:0!important;padding:0!important;z-index:2}
	.ms-shop-body .shop_table_responsive td.product-thumbnail{display:block;text-align:left!important;border:0!important}
	.ms-shop-body .shop_table_responsive td.product-thumbnail img{width:84px;height:84px;object-fit:cover}
	.ms-shop-body .shop_table_responsive td{padding:12px 6px!important}
	.ms-shop-body .e-cart__column-end,.ms-shop-body .e-cart__column-inner,.ms-shop-body .e-cart-totals{min-height:0!important;height:auto!important}
	.ms-shop-body .e-cart__column-end{padding-bottom:0!important}
	/* checkout: payment boxes use the full width */
	.ms-shop-body .woocommerce-checkout-payment{padding:20px 0 0!important}
	.ms-shop-body .wc_payment_methods li{padding:14px!important}
	.ms-shop-body .payment_box,.ms-shop-body #payment .payment_box fieldset{padding:0!important;margin:10px 0 0!important}
	.ms-shop-body .wc-stripe-elements-field,.ms-shop-body .wc-stripe-upe-element,.ms-shop-body #wc-stripe-upe-form{padding:0!important;margin:0!important;max-width:100%}
	.ms-shop-body .e-checkout__container .e-checkout__column.e-checkout__column-start,.ms-shop-body .e-checkout__container .e-checkout__column.e-checkout__column-end{padding:22px 16px!important}
	.ms-shop-body .form-row-first,.ms-shop-body .form-row-last{width:100%!important;float:none!important}
	.ms-shop-body{padding-left:14px!important;padding-right:14px!important}
	}
	</style>';
}, 100 );

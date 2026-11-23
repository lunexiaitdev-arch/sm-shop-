<?php
/** Client revision: MS branding, tumbler-only catalog, private promotion and bilingual merchandising. */
defined('ABSPATH') || exit;
const MSREV_VERSION = '20261029-5';
function msrev_url($es,$en=null){$id=smrf_en()&&$en?$en:$es;return get_permalink($id);}
function msrev_image($color){return plugins_url('assets/product-'.$color.'-ms.webp',__FILE__);}
add_filter('wp_get_attachment_url',function($url,$id){$color=get_post_meta($id,'_msrev_color',true);return $color?msrev_image($color):$url;},30,2);
function msrev_lifestyle(){
    $en=smrf_en();$colors=['black','cream','pink','blue'];
    $titles=$en?['YOUR STYLE','YOUR EVERYDAY','YOUR VIBE','UNFILTERED']:['TU ESTILO','TU DÍA A DÍA','TU VIBRA','SIN FILTROS'];
    $captions=$en?['A color for your style.','Your everyday companion.','Make it your own.','Express yourself freely.']:['Un color para tu estilo.','Tu compañía de cada día.','Hazlo parte de ti.','Exprésate con libertad.'];
    $h='<div class="sm-lifestyle-grid ms-tumbler-lifestyle">';foreach($colors as $i=>$color){$h.='<figure class="sm-lifestyle-card"><img class="ms-lifestyle-image" src="'.esc_url(msrev_image($color)).'" alt="'.esc_attr(($en?'MS Collection tumbler — ':'Vaso térmico MS Collection — ').($en?['black','cream','pink','blue'][$i]:['negro','crema','rosa','azul'][$i])).'" width="1024" height="1536" loading="lazy"><figcaption><h3>'.esc_html($titles[$i]).'</h3><p>'.esc_html($captions[$i]).'</p></figcaption></figure>';}
    return $h.'</div>';
}
add_shortcode('ms_revision_discover',function(){
    $en=smrf_en();$shop=msrev_url(6,20);
    return '<section class="ms-discover"><a class="ms-discover-tumbler" href="'.esc_url($shop).'"><img src="'.esc_url(msrev_image('pink')).'" width="1024" height="1536" alt="'.($en?'Pink MS Collection tumbler':'Vaso térmico rosa MS Collection').'" loading="eager"><div><span>MS COLLECTION</span><h2>'.($en?'Tumblers, your way':'Vasos térmicos con tu estilo').'</h2><p>'.($en?'Five colors. One unmistakable vibe.':'Cinco colores. Una vibra inconfundible.').'</p><b>'.($en?'SHOP TUMBLERS':'VER VASOS TÉRMICOS').' →</b></div></a><div class="ms-mystery" id="ms-coming-soon"><div><span>'.($en?'THE NEXT CHAPTER':'EL PRÓXIMO CAPÍTULO').'</span><h2>'.($en?'Something extraordinary is coming':'Algo extraordinario está por llegar').'</h2><p>'.($en?'New surprises. The same fearless spirit. Some things are worth the wait.':'Nuevas sorpresas. El mismo espíritu libre. Hay cosas que merecen la espera.').'</p><small>'.($en?'COMING SOON · STAY CURIOUS':'PRÓXIMAMENTE · DEJA VOLAR TU CURIOSIDAD').'</small></div></div></section>';
});
add_shortcode('ms_revision_footer_links',function(){
    $en=smrf_en();$labels=$en?['Home','Shop','MS SECRET','About us']:['Inicio','Tienda','MS SECRET','Sobre nosotros'];$ids=$en?[28,20,32,30]:[27,6,31,29];$h='<nav class="ms-footer-clean" aria-label="'.($en?'Explore':'Explorar').'"><ul>';foreach($ids as $i=>$id)$h.='<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html($labels[$i]).'</a></li>';return $h.'</ul></nav>';
});
// Shared templates use the current storefront language, including cart drawer labels.
add_filter('elementor/widget/render_content',function($html){
    if(!smrf_en())return strtr($html,['Tu carrito'=>'Tu carrito']);
    return strtr($html,['SOLO ADULTOS'=>'ADULTS ONLY','Tu carrito'=>'Your cart','FINALIZAR COMPRA'=>'CHECKOUT','VER CARRITO'=>'VIEW CART']);
},80);
function msrev_replace_strings($v){if(is_string($v))return smrf_brand($v);if(is_array($v))foreach($v as &$x)$x=msrev_replace_strings($x);return $v;}
function msrev_install(){
    if(get_option('msrev_version')===MSREV_VERSION)return;
    if(!function_exists('wc_get_product')||!function_exists('pll_set_post_language'))return;
    global $wpdb;
    // A reversible snapshot of only the records changed by this revision; no users or credentials.
    $backup=[];$posts=$wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type IN ('page','product','product_variation','elementor_library','nav_menu_item') AND post_status NOT IN ('trash','auto-draft','inherit')");
    foreach($posts as $p)$backup[$p->ID]=['post'=>(array)$p,'meta'=>get_post_meta($p->ID)];
    if(!get_option('msrev_backup_20261029'))add_option('msrev_backup_20261029',$backup,'',false);
    foreach($posts as $p){
        $patch=['ID'=>$p->ID];foreach(['post_title','post_content','post_excerpt'] as $key)$patch[$key]=smrf_brand($p->$key);wp_update_post(wp_slash($patch));
        $raw=get_post_meta($p->ID,'_elementor_data',true);$d=json_decode($raw,true);if(is_array($d))update_post_meta($p->ID,'_elementor_data',wp_slash(wp_json_encode(msrev_replace_strings($d))));
    }
    update_option('blogname','MS Collection');update_option('woocommerce_email_from_name','MS Collection');
    // All previous non-tumbler/demo products are recoverably unpublished, including old size variants.
    $keep=[65,66,67,68];foreach($posts as $p)if($p->post_type==='product'&&!in_array((int)$p->ID,$keep,true))wp_update_post(['ID'=>$p->ID,'post_status'=>'draft']);
    $definition=[
        'pink'=>['es'=>65,'en'=>66,'es_name'=>'Vaso térmico Rosa','en_name'=>'Pink Tumbler','es_color'=>'rosa','en_color'=>'pink'],
        'cream'=>['es'=>67,'en'=>68,'es_name'=>'Vaso térmico Crema','en_name'=>'Cream Tumbler','es_color'=>'crema','en_color'=>'cream'],
        'red'=>['es'=>0,'en'=>0,'es_name'=>'Vaso térmico Rojo','en_name'=>'Red Tumbler','es_color'=>'rojo','en_color'=>'red'],
        'black'=>['es'=>0,'en'=>0,'es_name'=>'Vaso térmico Negro','en_name'=>'Black Tumbler','es_color'=>'negro','en_color'=>'black'],
        'blue'=>['es'=>0,'en'=>0,'es_name'=>'Vaso térmico Azul','en_name'=>'Blue Tumbler','es_color'=>'azul','en_color'=>'blue'],
    ];
    $catalog=[];$images=[];$order=0;
    foreach($definition as $color=>$info){
        $asset=__DIR__.'/assets/product-'.$color.'-ms.webp';if(!file_exists($asset))throw new RuntimeException('Missing client product image: '.$color);
        $img_id=(int)get_option('msrev_image_'.$color);if(!$img_id||!get_post($img_id)){
            $img_id=wp_insert_attachment(['post_title'=>'MS Collection — '.$color,'post_mime_type'=>'image/webp','guid'=>msrev_image($color),'post_status'=>'inherit']);
            update_post_meta($img_id,'_wp_attached_file',$asset);$dims=getimagesize($asset);update_post_meta($img_id,'_wp_attachment_metadata',['width'=>$dims[0],'height'=>$dims[1],'file'=>$asset,'sizes'=>[]]);update_option('msrev_image_'.$color,$img_id,false);
        }
        update_post_meta($img_id,'_wp_attached_file',$asset);$dims=getimagesize($asset);update_post_meta($img_id,'_wp_attachment_metadata',['width'=>$dims[0],'height'=>$dims[1],'file'=>$asset,'sizes'=>[]]);update_post_meta($img_id,'_msrev_color',$color);update_post_meta($img_id,'_wp_attachment_image_alt','MS Collection tumbler — '.$color);$images[$color]=$img_id;$pair=[];
        foreach(['es','en'] as $lang){
            $id=(int)($info[$lang]?:get_option('msrev_product_'.$color.'_'.$lang));$product=$id?wc_get_product($id):new WC_Product_Simple();if(!$product)$product=new WC_Product_Simple();
            $product->set_name($info[$lang.'_name']);$product->set_status('publish');$product->set_regular_price('25.99');$product->set_sale_price('');$product->set_price('25.99');$product->set_image_id($img_id);$product->set_gallery_image_ids([]);$product->set_tag_ids([]);$product->set_catalog_visibility('visible');$product->set_menu_order($order);$product->set_category_ids([$lang==='es'?22:24]);$product->set_sku('MS-TUMBLER-'.strtoupper($color).'-'.strtoupper($lang));
            $name=$info[$lang.'_color'];$product->set_short_description($lang==='es'?'<p>Tu estilo, sin filtros. Vaso térmico '.$name.' con asa y tapa, con el diseño distintivo de MS Collection.</p>':'<p>Your style, unfiltered. A '.$name.' handled tumbler with a lid and the signature MS Collection design.</p>');
            $product->set_description($lang==='es'?'<h2>Hecho para acompañar tu día</h2><p>Un toque de color y personalidad para tu rutina. Elige el acabado '.$name.' y hazlo parte de tu estilo.</p><ul><li>Color: '.ucfirst($name).'</li><li>Asa integrada y tapa</li><li>Diseño MS Collection</li></ul><p>Consulta las instrucciones de cuidado incluidas con tu producto.</p>':'<h2>Made to accompany your day</h2><p>A touch of color and personality for your everyday routine. Choose the '.$name.' finish and make it part of your style.</p><ul><li>Color: '.ucfirst($name).'</li><li>Integrated handle and lid</li><li>MS Collection design</li></ul><p>Follow the care instructions included with your product.</p>');
            $id=$product->save();pll_set_post_language($id,$lang);update_option('msrev_product_'.$color.'_'.$lang,$id,false);$pair[$lang]=$id;$catalog[]=$id;wc_delete_product_transients($id);
        }
        pll_save_post_translations($pair);$order++;
    }
    update_option('msrev_catalog_ids',$catalog,false);
    // Private, product-only $6 discount: $25.99 -> $19.99 per eligible unit before tax/shipping.
    $code='MSVIP-7Q29';$coupon_id=wc_get_coupon_id_by_code($code);$c=new WC_Coupon($coupon_id?:0);$c->set_code($code);$c->set_description('Private client promotion: $6 off each MS Collection tumbler. Not displayed on the storefront.');$c->set_discount_type('fixed_product');$c->set_amount('6.00');$c->set_product_ids($catalog);$c->set_individual_use(true);$c->set_exclude_sale_items(true);
    $expires=get_option('msrev_promo_expires');if(!$expires){$expires=time()+30*DAY_IN_SECONDS;update_option('msrev_promo_expires',$expires,false);}$c->set_date_expires((int)$expires);$c->save();update_option('msrev_promo_id',$c->get_id(),false);
    foreach([27,28] as $id){
        $d=json_decode(get_post_meta($id,'_elementor_data',true),true)?:[];$new=[];$replaced=false;
        foreach($d as $node){$classes=$node['settings']['css_classes']??'';
            if(strpos($classes,'sm-reference-hero')!==false)smrf_walk($node['elements'],function(&$w)use($id){if(($w['widgetType']??'')==='button')$w['settings']['text']=$id===27?'COMPRAR AHORA →':'SHOP NOW →';});
            if(strpos($classes,'sm-category-row')!==false){$node=['id'=>'msrevdis','elType'=>'container','settings'=>['content_width'=>'full','css_classes'=>'ms-revision-discover','padding'=>['unit'=>'px','top'=>'0','right'=>'0','bottom'=>'0','left'=>'0']],'elements'=>[smrf_widget('msrevdc','shortcode',['shortcode'=>'[ms_revision_discover]'])]];$replaced=true;}
            smrf_walk($node['elements'],function(&$w)use($id){if(($w['widgetType']??'')==='heading'&&in_array($w['settings']['title']??'',['Sin Filtros','Unfiltered'],true))$w['settings']['title']=$id===27?'Sin Filtros':'Unfiltered';});
            $new[]=$node;
        }
        $has_secret=false;foreach($new as $node)if(strpos($node['settings']['css_classes']??'','sm-reference-secret')!==false)$has_secret=true;
        if(!$has_secret){
            $saved=get_option('msrev_backup_20261029',[]);$meta=$saved[$id]['meta']['_elementor_data'][0]??'';
            $original=json_decode($meta,true)?:[];
            foreach($original as $node)if(strpos($node['settings']['css_classes']??'','sm-reference-secret')!==false){
                $node=msrev_replace_strings($node);$position=count($new);
                foreach($new as $i=>$candidate)if(strpos($candidate['settings']['css_classes']??'','sm-community-band')!==false){$position=$i;break;}
                array_splice($new,$position,0,[$node]);break;
            }
        }
        update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($new)));delete_post_meta($id,'_elementor_element_cache');
    }
    // Footer has one Contact link, in the customer-service column.
    $footer=json_decode(get_post_meta(34,'_elementor_data',true),true)?:[];smrf_walk($footer,function(&$w){if(($w['widgetType']??'')==='nav-menu'&&($w['settings']['menu']??'')==='menu-principal'){$w['widgetType']='shortcode';$w['settings']=['shortcode'=>'[ms_revision_footer_links]'];}});update_post_meta(34,'_elementor_data',wp_slash(wp_json_encode($footer)));
    // Remove remaining mock product/review copy and avoid unsupported size claims.
    foreach([29,30,39,116,104,106] as $id){$d=json_decode(get_post_meta($id,'_elementor_data',true),true)?:[];
    if(in_array($id,[39,116],true))$d=array_values(array_filter($d,function($n){$s=wp_json_encode($n);return !str_contains($s,'Sample reviews')&&!str_contains($s,'Reseñas de ejemplo')&&!str_contains($s,'Rese\\u00f1as de ejemplo');}));
    smrf_walk($d,function(&$n)use($id,$images){
        $map=['40 oz'=>'5','CAPACIDAD'=>'ACABADOS','CAPACITY'=>'FINISHES','Materiales y acabados pensados para el uso diario. (Texto de ejemplo)'=>'Un acabado con personalidad para acompañar tu rutina.','Materials and finishes made for everyday use. (Sample text)'=>'A finish with personality for your everyday routine.'];
        foreach($n['settings']??[] as $k=>$v){if(is_string($v))$n['settings'][$k]=strtr(str_replace([' (Sample text)',' (Texto de ejemplo)'],'',$v),$map);elseif(is_array($v))$n['settings'][$k]=msrev_clean_sample_copy($v);}
        if(in_array($id,[29,30],true)&&isset($n['settings']['image']['url'])){foreach($images as $color=>$image_id)if(str_contains($n['settings']['image']['url'],'tumbler-'.$color))$n['settings']['image']=['id'=>$image_id,'url'=>msrev_image($color)];}
        if(($n['widgetType']??'')==='shortcode'&&str_contains($n['settings']['shortcode']??'','ms_reviews'))$n['settings']['shortcode']='';
    });update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($d)));}
    foreach([31,32] as $id)wp_update_post(['ID'=>$id,'post_title'=>'MS SECRET']);
    // Unused sample content is not a public storefront page.
    if(get_post(2)&&get_post_field('post_name',2)==='sample-page')wp_update_post(['ID'=>2,'post_status'=>'draft']);
    foreach([65,66,67,68] as $id){foreach(get_comments(['post_id'=>$id,'type'=>'review','status'=>'approve']) as $comment){if((int)$comment->comment_ID>=38&&(int)$comment->comment_ID<=49)wp_set_comment_status($comment->comment_ID,'hold');}if(class_exists('WC_Comments'))WC_Comments::clear_transients($id);}
    update_option('msrev_version',MSREV_VERSION,false);wp_cache_flush();if(class_exists('\Elementor\Plugin'))\Elementor\Plugin::$instance->files_manager->clear_cache();do_action('litespeed_purge_all');
}
function msrev_clean_sample_copy($v){if(is_string($v))return str_replace([' (Sample text)',' (Texto de ejemplo)'],'',$v);if(is_array($v))foreach($v as &$x)$x=msrev_clean_sample_copy($x);return $v;}
add_action('admin_init',function(){if(current_user_can('manage_woocommerce'))msrev_install();});
// Only the approved tumbler catalog may be purchased, including stale cart sessions.
add_filter('woocommerce_is_purchasable',function($ok,$p){$ids=get_option('msrev_catalog_ids',[]);return $ids&&!in_array($p->get_id(),array_map('intval',$ids),true)?false:$ok;},100,2);
add_action('admin_notices',function(){if(current_user_can('manage_woocommerce')&&get_option('msrev_version')===MSREV_VERSION)echo '<div id="msrev-status" class="notice notice-success" data-version="'.esc_attr(MSREV_VERSION).'" data-products="'.count(get_option('msrev_catalog_ids',[])).'"><p>MS Collection revision applied. Five tumbler colors are available in Spanish and English. Private promotion configured.</p></div>';});
function msrev_layout_polish(){
 if(get_option('msrev_layout_polish')==='2')return;
 foreach([39,116] as $id){$d=json_decode(get_post_meta($id,'_elementor_data',true),true)?:[];
  foreach($d as $i=>&$n){$n['settings']['css_classes']=trim(($n['settings']['css_classes']??'').' ms-pdp-section ms-pdp-section-'.$i);}
  unset($n);
  smrf_walk($d,function(&$n){$s=&$n['settings'];if(!is_array($s))return;
   foreach(['sticky','sticky_offset','sticky_effects_offset','sticky_on','motion_fx_motion_fx_scrolling','motion_fx_translateY_effect'] as $key)unset($s[$key]);
   if(str_contains($s['css_classes']??'','ms-pdp-gallery')){$s['css_classes'].=' ms-pdp-gallery-static';$s['width']=['unit'=>'%','size'=>48];}
   if(($n['widgetType']??'')==='woocommerce-product-rating'){$n['widgetType']='html';$s=['html'=>''];}
   if(($n['elType']??'')==='container'&&count($n['elements']??[])===1&&($n['elements'][0]['widgetType']??'')==='html'&&($n['elements'][0]['settings']['html']??null)==='')$s['css_classes']=trim(($s['css_classes']??'').' ms-empty-rating');
  });update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($d)));delete_post_meta($id,'_elementor_element_cache');
 }
 if(class_exists('\Elementor\Plugin'))\Elementor\Plugin::$instance->files_manager->clear_cache();update_option('msrev_layout_polish','2',false);do_action('litespeed_purge_all');
}
add_action('admin_init',function(){if(current_user_can('manage_woocommerce'))msrev_layout_polish();},30);
// Resolve legacy editor links against the current installation on local and live.
add_filter('elementor/widget/render_content',function($html){return str_replace(['http://mariansophia.local','https://mariansophia.local'],untrailingslashit(home_url('/')),$html);},120);
add_action('admin_init',function(){
 if(!current_user_can('manage_options')||get_option('msrev_header_scroll')==='1')return;
 $d=json_decode(get_post_meta(33,'_elementor_data',true),true)?:[];
 smrf_walk($d,function(&$n){foreach(['sticky','sticky_offset','sticky_effects_offset','sticky_on','sticky_parent'] as $k)unset($n['settings'][$k]);});
 update_post_meta(33,'_elementor_data',wp_slash(wp_json_encode($d)));delete_post_meta(33,'_elementor_element_cache');
 if(class_exists('\Elementor\Plugin'))\Elementor\Plugin::$instance->files_manager->clear_cache();update_option('msrev_header_scroll','1',false);do_action('litespeed_purge_all');
},40);
/** US-only standard delivery; owner authorized a provisional flat rate. */
function msrev_setup_us_shipping(){
 if(get_option('msrev_us_shipping_v1'))return;
 if(!class_exists('WC_Shipping_Zone'))return;
 $backup=['ship_to'=>get_option('woocommerce_ship_to_countries'),'specific'=>get_option('woocommerce_specific_ship_to_countries'),'zones'=>WC_Shipping_Zones::get_zones()];add_option('msrev_shipping_backup_v1',$backup,'',false);
 $zone_id=0;foreach(WC_Shipping_Zones::get_zones() as $z){foreach($z['zone_locations'] as $loc)if($loc->type==='country'&&$loc->code==='US'&&count($z['zone_locations'])===1){$zone_id=$z['zone_id'];break 2;}}
 $zone=new WC_Shipping_Zone($zone_id);$zone->set_zone_name('United States');$zone->set_zone_order(0);$zone->save();$zone->set_zone_locations([(object)['code'=>'US','type'=>'country']]);$zone->save();
 $flat=0;foreach($zone->get_shipping_methods() as $method){if($method->id==='flat_rate'&&!$flat)$flat=$method->instance_id;elseif($method->id==='free_shipping'){$method->enabled='no';$settings=$method->instance_settings;$settings['enabled']='no';update_option($method->get_instance_option_key(),$settings);global $wpdb;$wpdb->update($wpdb->prefix.'woocommerce_shipping_zone_methods',['is_enabled'=>0],['instance_id'=>$method->instance_id]);}}
 if(!$flat)$flat=$zone->add_shipping_method('flat_rate');
 update_option('woocommerce_flat_rate_'.$flat.'_settings',['title'=>'Standard US Shipping','tax_status'=>'none','cost'=>'5.99','type'=>'order','class_costs'=>'','no_class_cost'=>'']);
 global $wpdb;$wpdb->update($wpdb->prefix.'woocommerce_shipping_zone_methods',['is_enabled'=>1],['instance_id'=>$flat]);
 update_option('woocommerce_ship_to_countries','specific');update_option('woocommerce_specific_ship_to_countries',['US']);
 WC_Cache_Helper::get_transient_version('shipping',true);update_option('msrev_us_shipping_v1',['zone'=>$zone->get_id(),'instance'=>$flat,'cost'=>'5.99','created'=>gmdate('c')],false);
}
add_action('admin_init',function(){if(current_user_can('manage_woocommerce'))msrev_setup_us_shipping();},50);
add_filter('woocommerce_shipping_rate_label',function($label,$rate){if($rate->get_method_id()==='flat_rate'&&$rate->get_instance_id()===(int)(get_option('msrev_us_shipping_v1')['instance']??0))return smrf_en()?'Standard US Shipping':'Envío estándar a Estados Unidos';return $label;},20,2);
/** Shared branded shell for plain-text WordPress and private-access notifications. */
function msrev_email_shell($subject,$body,$en=true){
 $logo=plugins_url('assets/logo-ms-transparent.webp',__FILE__);$title=esc_html(smrf_brand($subject));
 $text=esc_html(smrf_brand($body));$text=make_clickable($text);$text=wpautop($text);
 $text=preg_replace('/<p>([A-Z0-9]{6,12})<\/p>/','<p style="background:#faf7f2;color:#29131e;font-size:28px;font-weight:bold;letter-spacing:5px;text-align:center;padding:22px;border:1px dashed #ec0055;border-radius:6px">$1</p>',$text);
 $footer=$en?'Thoughtfully designed. Unapologetically you.':'Diseño con personalidad. Sin filtros.';
 return '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="UTF-8"></head><body style="margin:0;background:#faf7f2;padding:28px 12px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center"><table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;border:1px solid #e7dcd4;background:#fff;border-radius:10px;overflow:hidden"><tr><td style="background:#10080e;text-align:center;padding:30px 24px;border-bottom:3px solid #ec0055"><img src="'.esc_url($logo).'" width="66" height="55" alt="MS Collection" style="display:block;margin:0 auto 12px;object-fit:contain"><div style="font:28px Georgia,serif;color:#fff;letter-spacing:3px">MS COLLECTION</div><div style="font:10px Arial,sans-serif;color:#efa1bf;letter-spacing:3px;margin-top:10px">EST. 2025</div></td></tr><tr><td style="padding:32px 28px 12px"><h1 style="font:normal 30px/1.2 Georgia,serif;color:#26131d;margin:0 0 22px">'.$title.'</h1><div style="font:15px/1.8 Arial,sans-serif;color:#51434a">'.$text.'</div></td></tr><tr><td align="center" style="padding:12px 28px 32px"><a href="'.esc_url(home_url('/')).'" style="display:inline-block;background:#ec0055;color:#fff;padding:15px 28px;text-decoration:none;font:bold 12px Arial,sans-serif;letter-spacing:1px;border-radius:4px">'.($en?'EXPLORE MS COLLECTION':'DESCUBRE MS COLLECTION').'</a></td></tr><tr><td style="background:#faf7f2;padding:22px;text-align:center;font:12px/1.6 Arial,sans-serif;color:#77666b">MS Collection<br>'.esc_html($footer).'</td></tr></table></td></tr></table></body></html>';
}
add_filter('wp_mail',function($mail){
 $body=$mail['message']??'';if(preg_match('/<(?:html|body|table)\b/i',$body))return $mail;
 $mail['message']=msrev_email_shell($mail['subject'],$body,!str_starts_with(determine_locale(),'es'));
 $headers=$mail['headers']??[];if(!is_array($headers))$headers=explode("\n",str_replace("\r",'',(string)$headers));
 $headers=array_values(array_filter($headers,function($h){return stripos($h,'Content-Type:')!==0;}));$headers[]='Content-Type: text/html; charset=UTF-8';$mail['headers']=$headers;return $mail;
},90);
add_filter('wp_mail_from_name',function($name){return 'MS Collection';},90);
add_filter('woocommerce_email_styles',function($css){return $css.'
#wrapper{background:#faf7f2!important;padding:28px 12px!important}
#template_container{border:1px solid #e7dcd4!important;border-radius:10px!important;max-width:600px!important}
#template_header_image,#template_header,#header_wrapper{background:#10080e!important}
#template_header{border-bottom:3px solid #ec0055!important}
#template_header h1{font:normal 30px/1.25 Georgia,serif!important;color:#fff!important}
#body_content{background:#fff!important}#body_content_inner{color:#51434a!important;font:15px/1.7 Arial,sans-serif!important}
#body_content_inner h2{font:normal 23px Georgia,serif!important;color:#26131d!important}
#body_content_inner a{color:#d9004d!important}.td,td.td,th.td{border-color:#e7dcd4!important;padding:12px!important}
#template_footer{background:#faf7f2!important}#credit{color:#77666b!important}
@media(max-width:600px){#template_container{width:100%!important}#body_content>table>tbody>tr>td{padding:24px 18px!important}#template_header h1{font-size:25px!important}}';},100);
add_action('admin_init',function(){if(!current_user_can('manage_woocommerce')||get_option('msrev_email_design_v1'))return;update_option('woocommerce_email_header_image',plugins_url('assets/logo-ms-transparent.webp',__FILE__));update_option('woocommerce_email_base_color','#ec0055');update_option('woocommerce_email_background_color','#faf7f2');update_option('woocommerce_email_body_background_color','#ffffff');update_option('woocommerce_email_text_color','#51434a');update_option('woocommerce_email_footer_text','MS Collection · Est. 2025');update_option('msrev_email_design_v1',1,false);},60);

/** Prepare owner-supplied Florida origin; carrier/tax connections stay pending. */
add_action('admin_init',function(){
 if(!current_user_can('manage_woocommerce')||get_option('msrev_florida_origin_v1')||!class_exists('WC_Shipping_Zone'))return;
 $keys=['woocommerce_default_country','woocommerce_store_postcode','woocommerce_tax_based_on','woocommerce_tax_display_shop','woocommerce_tax_display_cart','woocommerce_tax_total_display','woocommerce_prices_include_tax'];
 $backup=[];foreach($keys as $key)$backup[$key]=get_option($key);$backup['zones']=WC_Shipping_Zones::get_zones();add_option('msrev_florida_origin_backup',$backup,'',false);
 update_option('woocommerce_default_country','US:FL');update_option('woocommerce_store_postcode','33015');
 update_option('woocommerce_ship_to_countries','specific');update_option('woocommerce_specific_ship_to_countries',['US']);
 update_option('woocommerce_tax_based_on','shipping');update_option('woocommerce_tax_display_shop','excl');update_option('woocommerce_tax_display_cart','excl');update_option('woocommerce_tax_total_display','itemized');
 // Product prices remain $25.99 before tax. No guessed jurisdiction rates or parcel sizes.
 update_option('woocommerce_prices_include_tax','no');
 $zone=null;foreach(WC_Shipping_Zones::get_zones() as $z){foreach($z['zone_locations'] as $loc)if($loc->type==='country'&&$loc->code==='US'&&count($z['zone_locations'])===1){$zone=new WC_Shipping_Zone($z['zone_id']);break 2;}}
 if(!$zone){$zone=new WC_Shipping_Zone();$zone->set_zone_name('United States');$zone->set_zone_order(0);$zone->save();$zone->set_zone_locations([(object)['code'=>'US','type'=>'country']]);$zone->save();}
 $old=get_option('msrev_us_shipping_v1',[]);$method=0;foreach($zone->get_shipping_methods() as $m)if($m->id==='flat_rate'){$method=$m->instance_id;break;}
 if(!$method)$method=$zone->add_shipping_method('flat_rate');
 update_option('woocommerce_flat_rate_'.$method.'_settings',['title'=>'Standard US Shipping','tax_status'=>'none','cost'=>'5.99','type'=>'order','class_costs'=>'','no_class_cost'=>'']);
 global $wpdb;$wpdb->update($wpdb->prefix.'woocommerce_shipping_zone_methods',['is_enabled'=>1],['instance_id'=>$method]);
 if(!empty($old['instance'])&&(int)$old['instance']!==$method)$wpdb->update($wpdb->prefix.'woocommerce_shipping_zone_methods',['is_enabled'=>0],['instance_id'=>(int)$old['instance']]);
 update_option('msrev_us_shipping_v1',['zone'=>$zone->get_id(),'instance'=>$method,'cost'=>'5.99','created'=>gmdate('c')],false);
 WC_Cache_Helper::get_transient_version('shipping',true);update_option('msrev_florida_origin_v1',1,false);
},70);

/** Owner-requested provisional Florida state rate; county surtaxes not included. */
add_action('admin_init',function(){
 if(!current_user_can('manage_woocommerce')||get_option('msrev_fl_state_tax_v1')||!class_exists('WC_Tax'))return;
 global $wpdb;
 $rows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates",ARRAY_A);
 add_option('msrev_fl_state_tax_backup',['rates'=>$rows,'enabled'=>get_option('woocommerce_calc_taxes')],'',false);
 // Do not stack another state rate onto an existing matching Florida standard rate.
 $id=0;foreach($rows as $row)if($row['tax_rate_country']==='US'&&$row['tax_rate_state']==='FL'&&$row['tax_rate_class']===''&&(int)$row['tax_rate_priority']===1){$id=(int)$row['tax_rate_id'];break;}
 $rate=['tax_rate_country'=>'US','tax_rate_state'=>'FL','tax_rate'=>'6.0000','tax_rate_name'=>'Florida State Tax','tax_rate_priority'=>1,'tax_rate_compound'=>0,'tax_rate_shipping'=>0,'tax_rate_order'=>0,'tax_rate_class'=>''];
 if($id)WC_Tax::_update_tax_rate($id,$rate);else $id=WC_Tax::_insert_tax_rate($rate);
 update_option('woocommerce_calc_taxes','yes');update_option('woocommerce_tax_based_on','shipping');update_option('woocommerce_prices_include_tax','no');update_option('woocommerce_tax_display_shop','excl');update_option('woocommerce_tax_display_cart','excl');update_option('woocommerce_tax_total_display','itemized');
 WC_Cache_Helper::invalidate_cache_group('taxes');update_option('msrev_fl_state_tax_v1',['id'=>$id,'rate'=>6,'county_surtax_pending'=>true],false);
},80);

/** Explicit Miami city supplement. Other Miami-Dade cities await address-based service. */
add_action('admin_init',function(){
 if(!current_user_can('manage_woocommerce')||get_option('msrev_miami_tax_v1')||!class_exists('WC_Tax'))return;
 global $wpdb;
 $id=WC_Tax::_insert_tax_rate(['tax_rate_country'=>'US','tax_rate_state'=>'FL','tax_rate'=>'1.0000','tax_rate_name'=>'Miami County Surtax','tax_rate_priority'=>2,'tax_rate_compound'=>0,'tax_rate_shipping'=>0,'tax_rate_order'=>1,'tax_rate_class'=>'']);
 WC_Tax::_update_tax_rate_cities($id,'MIAMI');WC_Cache_Helper::invalidate_cache_group('taxes');update_option('msrev_miami_tax_v1',['id'=>$id,'city'=>'Miami','rate'=>1],false);
},81);

/** Owner-approved dummy USPS service choices, pending real carrier integration. */
add_action('admin_init',function(){
 if(!current_user_can('manage_woocommerce')||get_option('msrev_dummy_usps_v1')||!class_exists('WC_Shipping_Zone'))return;
 $base=get_option('msrev_us_shipping_v1',[]);$zone=new WC_Shipping_Zone((int)($base['zone']??0));
 $ground=(int)($base['instance']??0);if(!$ground)$ground=$zone->add_shipping_method('flat_rate');
 $priority=$zone->add_shipping_method('flat_rate');
 foreach([$ground=>['USPS Ground Advantage — provisional rate','5.99'],$priority=>['USPS Priority Mail — provisional rate','9.99']] as $id=>$data){update_option('woocommerce_flat_rate_'.$id.'_settings',['title'=>$data[0],'tax_status'=>'none','cost'=>$data[1],'type'=>'order','class_costs'=>'','no_class_cost'=>'']);global $wpdb;$wpdb->update($wpdb->prefix.'woocommerce_shipping_zone_methods',['is_enabled'=>1],['instance_id'=>$id]);}
 update_option('msrev_dummy_usps_v1',['ground'=>$ground,'priority'=>$priority,'zone'=>$zone->get_id()],false);WC_Cache_Helper::get_transient_version('shipping',true);
},82);
add_filter('woocommerce_shipping_rate_label',function($label,$rate){$ids=get_option('msrev_dummy_usps_v1',[]);$id=$rate->get_instance_id();if($rate->get_method_id()!=='flat_rate')return $label;if($id===(int)($ids['ground']??-1))return smrf_en()?'USPS Ground Advantage — provisional rate':'USPS Ground Advantage — tarifa provisional';if($id===(int)($ids['priority']??-1))return smrf_en()?'USPS Priority Mail — provisional rate':'USPS Priority Mail — tarifa provisional';return $label;},30,2);
add_action('woocommerce_review_order_before_shipping',function(){if(get_option('msrev_dummy_usps_v1'))echo '<tr><td colspan="2" style="white-space:normal;font-size:12px">'.esc_html(smrf_en()?'Shipping rates shown are temporary estimates, not live USPS quotes.':'Las tarifas de envío son estimaciones provisionales, no cotizaciones de USPS en tiempo real.').'</td></tr>';});

// Keep a valid two-column table while giving shipping choices the entire row.
add_action('wp_footer',function(){if(!function_exists('is_checkout')||!is_checkout())return;?>
<script>jQuery(function($){function msShippingRow(){ $('#order_review tr.woocommerce-shipping-totals').each(function(){var row=$(this),heading=row.children('th'),cell=row.children('td');if(heading.length&&cell.length){var title=heading.text();heading.remove();cell.attr('colspan',2).prepend($('<strong class="ms-shipping-heading">').text(title));}});}msShippingRow();$(document.body).on('updated_checkout',msShippingRow);});</script>
<?php },100);

// Cart shipping spans the totals panel and survives WooCommerce cart refreshes.
add_action('wp_footer',function(){
 if(!function_exists('is_cart')||!is_cart())return;
 echo <<<'HTML'
<script>jQuery(function($){function msCartShipping(){ $('.cart_totals tr.woocommerce-shipping-totals').each(function(){var row=$(this),heading=row.children('th'),cell=row.children('td');if(heading.length&&cell.length){var title=heading.text();heading.remove();cell.attr('colspan',2).prepend($('<strong class="ms-cart-shipping-heading">').text(title));}});}msCartShipping();$(document.body).on('updated_cart_totals updated_wc_div',msCartShipping);});</script>
HTML;
},100);

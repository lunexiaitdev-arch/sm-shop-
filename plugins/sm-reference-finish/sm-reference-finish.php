<?php
/**
 * Plugin Name: MS Collection Reference Finish
 * Description: Reference-matched responsive refinements for the existing Elementor storefront. Includes reversible layout backups.
 * Version: 1.1.0
 */
defined('ABSPATH') || exit;
add_action('wp',function(){show_admin_bar(false);});

function smrf_icon($name) {
    $paths = [
        'truck'=>'<path d="M1 4h13v12H1zM14 8h4l4 5v3h-8M17 8v5h5"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'bag'=>'<path d="M4 8h16l1 14H3L4 8Z"/><path d="M8 9V6a4 4 0 0 1 8 0v3M10 15h4M12 13v4"/>',
        'star'=>'<path d="m12 2 3.1 6.3 7 1-5 4.9 1.2 6.9-6.3-3.3-6.3 3.3 1.2-6.9-5-4.9 7-1L12 2Z"/>',
        'heart'=>'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 22l8.8-9.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'gem'=>'<path d="m1 8 5-6h12l5 6-11 14L1 8ZM1 8h22M6 2l3 6 3 14 3-14 3-6"/>',
        'lips'=>'<path d="M1 12s5-2 8-6l3 2 3-2c3 4 8 6 8 6s-4 7-11 7S1 12 1 12Z"/><path d="M1 12c4 0 7 3 11 3s7-3 11-3M6 11l6-1 6 1"/>',
        'flame'=>'<path d="M13 2c1 6-4 6-4 11-2-1-2-3-2-3s-4 5-2 9c3 6 13 4 14-2 1-5-2-8-3-9 0 4-2 5-3 4 2-5 0-8 0-10Z"/>',
        'search'=>'<circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5 5"/>',
        'lock'=>'<rect x="5" y="10" width="14" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v4"/>',
    ];
    return '<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round">'.($paths[$name]??$paths['star']).'</svg>';
}
function smrf_en(){ return function_exists('pll_current_language') && pll_current_language()==='en'; }
function smrf_language_shop_url($url,$lang){
    if(function_exists('is_shop')&&is_shop()&&function_exists('pll_get_post')){
        $id=pll_get_post((int)get_option('woocommerce_shop_page_id'),$lang);
        if($id)return get_permalink($id);
    }
    return $url;
}
add_filter('pll_translation_url','smrf_language_shop_url',30,2);
add_filter('pll_the_language_link','smrf_language_shop_url',30,2);
add_shortcode('sm_reference_logo',function(){return '<a class="sm-reference-logo" aria-label="MS Collection — '.(smrf_en()?'Home':'Inicio').'" href="'.esc_url(function_exists('pll_home_url')?pll_home_url():home_url('/')).'"><span aria-hidden="true" class="sm-logo-lips"></span><span class="sm-logo-type">MS<span>COLLECTION</span></span></a>';});
add_shortcode('sm_reference_languages',function(){
    $langs=function_exists('pll_the_languages')?pll_the_languages(['raw'=>1]):[];
    $es=$langs['es']['url']??home_url('/');$en=$langs['en']['url']??home_url('/en/home/');
    return '<div class="sm-reference-top"><span>'.smrf_icon('truck').' '.(smrf_en()?'FAST SHIPPING ACROSS THE USA':'ENVÍOS RÁPIDOS A TODO USA').'</span><nav aria-label="Language"><a href="'.esc_url($es).'" lang="es">ES</a><span> | </span><a href="'.esc_url($en).'" lang="en">EN</a></nav></div>';
});
add_shortcode('sm_reference_search',function(){return '<details class="sm-reference-search"><summary aria-label="'.(smrf_en()?'Search products':'Buscar productos').'">'.smrf_icon('search').'</summary><form role="search" action="'.esc_url(home_url('/')).'" method="get"><label class="screen-reader-text" for="sm-product-search">'.(smrf_en()?'Search products':'Buscar productos').'</label><input id="sm-product-search" type="search" name="s" placeholder="'.(smrf_en()?'Search products…':'Buscar productos…').'" required><input type="hidden" name="post_type" value="product"><button type="submit">'.smrf_icon('search').'</button></form></details>';});
add_action('wp_enqueue_scripts',function(){
    wp_register_style('sm-reference-finish',plugins_url('reference.css',__FILE__),[],filemtime(__DIR__.'/reference.css'));
    $asset='product-pink-ms.webp';
    if(function_exists('is_product_category')&&is_product_category()){
        $term=get_queried_object();$slug=strtolower(($term->slug??'').' '.($term->name??''));
        if(strpos($slug,'secret')!==false)$asset='secret-ms.webp';
    }
    wp_add_inline_style('sm-reference-finish',':root{--sm-catalog-image:url("'.esc_url_raw(plugins_url('assets/'.$asset,__FILE__)).'")}');
},100);
add_action('wp_head',function(){wp_print_styles(['sm-reference-finish']);},999);

function smrf_brand($text) {
    if(!is_string($text))return $text;
    $text=preg_replace('/(?<![A-Za-z])SM(\s+COLLECTION)\b/i','MS$1',$text);
    return preg_replace('/(?<![A-Za-z])(?:S*M|MS)(?:’|\x27|&#8217;|&#039;)S\s+SECRET\b/iu','MS SECRET',$text);
}
foreach(['the_title','the_content','widget_text','bloginfo','woocommerce_email_from_name','document_title'] as $hook)add_filter($hook,'smrf_brand',30);
add_filter('nav_menu_item_title','smrf_brand',30);
add_filter('elementor/widget/render_content','smrf_brand',30);
add_filter('elementor/widget/render_content',function($content){
    if(strpos($content,'class="sm-community-reference"')===false)return $content;
    if(function_exists('msrev_lifestyle'))return msrev_lifestyle();
    $en=smrf_en();
    $titles=$en?['YOUR STYLE','YOUR EVERYDAY','YOUR VIBE','UNFILTERED']:['TU ESTILO','TU DÍA A DÍA','TU VIBRA','SIN FILTROS'];
    $captions=$en?['Products that adapt to you.','Functionality with personality.','Take it wherever you go.','A real community.']:['Productos que se adaptan a ti.','Funcionalidad con personalidad.','Llévala contigo a donde vayas.','Una comunidad real.'];
    $html='<div class="sm-lifestyle-grid">';
    foreach($titles as $i=>$title)$html.='<figure class="sm-lifestyle-card"><div class="sm-lifestyle-photo sm-lifestyle-photo-'.(int)$i.'" role="img" aria-label="'.esc_attr($title).'"></div><figcaption><h3>'.esc_html($title).'</h3><p>'.esc_html($captions[$i]).'</p></figcaption></figure>';
    return $html.'</div>';
},40);
add_filter('wp_get_attachment_image_attributes',function($attributes){if(isset($attributes['alt']))$attributes['alt']=smrf_brand($attributes['alt']);return $attributes;},30);
// Translate the remaining legacy name in customer-facing strings without changing plugin identifiers or URLs.
add_filter('gettext',function($translated){return smrf_brand($translated);},30);

add_shortcode('sm_featured_products',function(){
    if(!function_exists('wc_get_products'))return '';
    $en=smrf_en();
    $q=new WP_Query(['post_type'=>'product','posts_per_page'=>4,'post_status'=>'publish','lang'=>$en?'en':'es','tax_query'=>[['taxonomy'=>'product_visibility','field'=>'name','terms'=>['exclude-from-catalog'],'operator'=>'NOT IN']],'orderby'=>'menu_order date','order'=>'ASC']);
    $out='<section class="sm-featured-section"><div class="sm-featured-heading"><span>'.($en?'CHOSEN FOR YOU':'SELECCIONADOS PARA TI').'</span><h2>'.($en?'Featured products':'Nuestros favoritos').'</h2><p>'.($en?'Your style. Your vibe. Without filters.':'Tu estilo. Tu vibra. Sin filtros.').'</p></div><div class="sm-product-grid">';
    foreach($q->posts as $post){$p=wc_get_product($post->ID);if(!$p)continue;
        $out.='<article class="sm-product-card"><a class="sm-product-photo" href="'.esc_url(get_permalink($p->get_id())).'">'.wp_get_attachment_image($p->get_image_id(),'large',false,['loading'=>'lazy','sizes'=>'(max-width: 767px) 46vw, 23vw','alt'=>smrf_brand($p->get_name())]).'<span>'.($en?'VIEW PRODUCT':'VER PRODUCTO').' ↗</span></a><div class="sm-product-info"><h3><a href="'.esc_url(get_permalink($p->get_id())).'">'.esc_html(smrf_brand($p->get_name())).'</a></h3><div class="sm-product-bottom"><span class="price">'.$p->get_price_html().'</span><a class="sm-product-more" href="'.esc_url(get_permalink($p->get_id())).'" aria-label="'.esc_attr(($en?'View ':'Ver ').$p->get_name()).'">→</a></div></div></article>';
    }
    wp_reset_postdata();
    return $out.'</div><a class="sm-featured-all" href="'.esc_url(function_exists('wc_get_page_permalink')?wc_get_page_permalink('shop'):home_url('/tienda/')).'">'.($en?'EXPLORE THE COLLECTION':'EXPLORA LA COLECCIÓN').' →</a></section>';
});
function smrf_featured_install(){foreach([27,28] as $id){$data=json_decode(get_post_meta($id,'_elementor_data',true),true)?:[];
    if(strpos(wp_json_encode($data),'sm_featured_products')!==false)continue;
    $data[]=['id'=>'smrfeat','elType'=>'container','settings'=>['content_width'=>'full','css_classes'=>'sm-featured-container','padding'=>['unit'=>'px','top'=>'0','right'=>'0','bottom'=>'0','left'=>'0','isLinked'=>true]],'elements'=>[smrf_widget('smrfprd','shortcode',['shortcode'=>'[sm_featured_products]'])]];
    update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
}}
function smrf_polish_all_data(){
    global $wpdb;
    $ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s",'_elementor_data'));
    $backup=get_option('smrf_backup_v1',[]);
    foreach($ids as $id){$raw=get_post_meta($id,'_elementor_data',true);$d=json_decode($raw,true);if(!is_array($d))continue;
        if(!isset($backup['posts'][$id]))$backup['posts'][$id]=['data'=>$raw,'title'=>get_the_title($id)];
        smrf_walk($d,function(&$w)use($id){foreach($w['settings']??[] as $k=>$v){
            if(is_string($v))$w['settings'][$k]=smrf_brand($v);
            if(in_array((int)$id,[27,28],true)||!is_string($v))continue;
            $color=strtolower($v);
            if($k==='background_color'&&in_array($color,['#fff','#ffffff','#fffcf8','#f6f1ea','#f8f6f2','#faf9f7','#f0e9dc','#f7f4ef'],true))$w['settings'][$k]='#140C11';
            if(in_array($k,['title_color','text_color','description_color','title_text_color','product_title_color'],true)&&in_array($color,['#0b0b0b','#111111','#111','#000000','#000','#3a3a3a','#6b6256'],true))$w['settings'][$k]=$k==='title_color'?'#FFFFFF':'#C8BDC3';
        }});
        update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($d)));
    }
    update_option('smrf_backup_v1',$backup,false);
}

function smrf_class(&$node,$class){$node['settings']['css_classes']=trim(($node['settings']['css_classes']??'').' '.$class);}
function smrf_walk(&$nodes,$callback){foreach($nodes as &$node){$callback($node);if(!empty($node['elements']))smrf_walk($node['elements'],$callback);}}
function smrf_widget($id,$type,$settings){return ['id'=>$id,'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];}

function smrf_install(){
    if(get_option('smrf_installed_v1'))return;
    $ids=[27,28,29,30,31,32,33,34];
    $backup=['time'=>gmdate('c'),'posts'=>[],'menus'=>[],'blogname'=>get_option('blogname')];
    foreach($ids as $id)$backup['posts'][$id]=['data'=>get_post_meta($id,'_elementor_data',true),'title'=>get_the_title($id)];
    add_option('smrf_backup_v1',$backup,'','no');
    $home=json_decode(get_post_meta(27,'_elementor_data',true),true)?:[];
    if(strpos(wp_json_encode($home),'ms-hero2')===false){require_once __DIR__.'/seed.php';sm_reference_seed();}
    foreach([27,28] as $id){
        $data=json_decode(get_post_meta($id,'_elementor_data',true),true)?:[];
        $new=[];
        foreach($data as &$node){
            $serialized=wp_json_encode($node);
            if(strpos($serialized,'loop-grid')!==false)continue;
            if(strpos($serialized,'ms-hero2')!==false){smrf_class($node,'sm-reference-hero');}
            elseif(strpos($serialized,'ms-trust')!==false){
                $icons=['truck','bag','star','heart'];$i=0;
                smrf_walk($node['elements'],function(&$w)use(&$i,$icons){if(($w['widgetType']??'')==='icon-box'){
                    $s=$w['settings'];$w['widgetType']='html';$w['settings']=['html'=>'<div class="sm-trust-item">'.smrf_icon($icons[$i++]??'heart').'<div><h3>'.esc_html($s['title_text']).'</h3><p>'.esc_html($s['description_text']).'</p></div></div>'];
                }});
            }
            elseif(strpos($serialized,'ms-rtile')!==false){
                smrf_class($node,'sm-category-row');
                foreach($node['elements'] as $i=>&$tile){smrf_class($tile,['sm-tile-tumblers','sm-tile-cases','sm-tile-coming'][$i]);
                    if($i===2)smrf_walk($tile['elements'],function(&$w)use($id){if(($w['widgetType']??'')==='button')$w['settings']['link']['url']=get_permalink($id===27?29:30).'#sm-coming-soon';});
                }
            }
            elseif(strpos($serialized,'ms-secret-room')!==false){
                smrf_class($node,'sm-reference-secret');
                $bg=$node['settings']['background_image']??[];
                unset($node['settings']['background_image']);
                smrf_class($node['elements'][0],'sm-secret-image');
                $node['elements'][0]['settings']['background_background']='classic';
                $node['elements'][0]['settings']['background_image']=$bg;
                smrf_class($node['elements'][1],'sm-secret-copy');
                $ci=0;
                smrf_walk($node['elements'][1]['elements'],function(&$w)use(&$ci){
                    if(($w['widgetType']??'')==='icon'){$names=['lock','gem','lips','flame'];$w['widgetType']='html';$w['settings']=['html'=>'<span class="sm-outline-icon">'.smrf_icon($names[$ci++]??'lock').'</span>'];}
                    if(($w['elType']??'')==='container')smrf_class($w,'sm-secret-perks');
                });
            }
            elseif(strpos($serialized,'UNA COMUNIDAD')!==false||strpos($serialized,'MORE THAN A BRAND')!==false){
                smrf_class($node,'sm-community-band');
                smrf_walk($node['elements'],function(&$w){if(isset($w['settings']['title']))$w['settings']['title']=str_replace('♥','<span class="sm-pink-heart">♥</span>',$w['settings']['title']);});
            }
            else {smrf_class($node,'sm-community-strip');$node['elements']=[smrf_widget('smrcomm','html',['html'=>'<div class="sm-community-reference" role="img" aria-label="SM Collection: estilo, comunidad y accesorios sin filtros"></div>'])];}
            $new[]=$node;
        }
        smrf_walk($new,function(&$w){foreach($w['settings']??[] as $key=>$val)if(is_string($val))$w['settings'][$key]=smrf_brand($val);});
        update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($new)));
    }
    // Retain the existing Elementor navigation, account control and side cart.
    $header=json_decode(get_post_meta(33,'_elementor_data',true),true)?:[];
    smrf_class($header[0],'sm-reference-announcement');
    $header[0]['elements']=[smrf_widget('smrlang','shortcode',['shortcode'=>'[sm_reference_languages]'])];
    smrf_class($header[1],'sm-reference-header');
    smrf_walk($header,function(&$w){
        if(($w['widgetType']??'')==='theme-site-title'){$w['widgetType']='shortcode';$w['settings']=['shortcode'=>'[sm_reference_logo]','_css_classes'=>'sm-logo-widget'];}
        if(($w['widgetType']??'')==='nav-menu')$w['settings']['_css_classes']='sm-reference-nav';
        if(($w['widgetType']??'')==='woocommerce-menu-cart')$w['settings']['toggle_icon']='eicon-bag-light';
    });
    foreach($header[1]['elements'] as &$child){if(($child['elType']??'')==='container'){array_splice($child['elements'],1,0,[smrf_widget('smrsrch','shortcode',['shortcode'=>'[sm_reference_search]','_css_classes'=>'sm-search-widget'])]);}}
    update_post_meta(33,'_elementor_data',wp_slash(wp_json_encode($header)));
    foreach([29,30,31,32,34] as $id){$d=json_decode(get_post_meta($id,'_elementor_data',true),true);if(!$d)continue;smrf_walk($d,function(&$w){foreach($w['settings']??[] as $key=>$val)if(is_string($val))$w['settings'][$key]=smrf_brand($val);});update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($d)));}
    update_option('blogname','SM Collection');
    // Persist customer-facing names; preserve slugs, email addresses, order history, and code identifiers.
    foreach(get_posts(['post_type'=>['page','product','elementor_library','attachment'],'numberposts'=>-1,'post_status'=>'any']) as $post){
        $title=smrf_brand($post->post_title);if($title!==$post->post_title)wp_update_post(['ID'=>$post->ID,'post_title'=>$title]);
        if($post->post_type==='attachment'){$alt=get_post_meta($post->ID,'_wp_attachment_image_alt',true);if($alt)update_post_meta($post->ID,'_wp_attachment_image_alt',smrf_brand($alt));}
    }
    foreach(wp_get_nav_menus() as $menu){$items=wp_get_nav_menu_items($menu->term_id);if(!$items)continue;
        $is_en=in_array($menu->slug,['main-menu','footer-menu'],true);
        $main=in_array($menu->slug,['main-menu','menu-principal'],true);
        foreach($items as $item){
            $newtitle=smrf_brand($item->title);
            $object=(int)get_post_meta($item->ID,'_menu_item_object_id',true);
            $order=0;
            if($main){
                if(in_array($object,[27,28],true))$order=1;
                elseif(in_array($object,[6,20],true))$order=2;
                elseif(in_array($object,[31,32],true)){$order=3;$newtitle='SM’S SECRET';}
                elseif(in_array($object,[29,30],true)){$order=4;$newtitle=$is_en?'About us':'Sobre nosotros';}
                else $order=6;
            }
            $patch=['ID'=>$item->ID,'post_title'=>$newtitle];if($order)$patch['menu_order']=$order;
            wp_update_post($patch);
        }
        if($main){
            $has_contact=false;foreach($items as $item)if(strpos($item->url,'contact')!==false)$has_contact=true;
            if(!$has_contact){$contact=get_page_by_path($is_en?'en/contact':'contacto');
                if(!$contact&&function_exists('pll_get_post')){$es=get_page_by_path('contacto');if($es)$contact=get_post(pll_get_post($es->ID,'en'));}
                if($contact)wp_update_nav_menu_item($menu->term_id,0,['menu-item-object-id'=>$contact->ID,'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-title'=>$is_en?'Contact':'Contacto','menu-item-position'=>5,'menu-item-status'=>'publish']);
            }
        }
    }
    if(class_exists('\\Elementor\\Plugin'))\Elementor\Plugin::$instance->files_manager->clear_cache();
    smrf_featured_install();
    smrf_polish_all_data();
    update_option('smrf_installed_v1',gmdate('c'));do_action('litespeed_purge_all');
}
register_activation_hook(__FILE__,'smrf_install');
require_once __DIR__.'/revision-20261029.php';

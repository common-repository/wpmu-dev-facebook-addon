<?php
/*
Plugin Name: WPMU Ultimate Facebook Addon
Version: 0.7
Plugin URI: http://wordpress.org/extend/plugins/wpmu-dev-facebook-addon/
Description: An addon on WPMU Ultimate Facebook, changes the way OpenGraph tags create Descrition (make it according to Simple Facebook Connect).
Author: Alex (Shurf) Frenkel
Author URI: http://alex.frenkel-online.com/
*/

/**
* Has parts from Simple Facebook Connect ( http://ottopress.com/wordpress-plugins/simple-facebook-connect/ )
*/

$objClass = new sirshurf_wpmu_dev_facebook();
add_action('init', array($objClass, 'add_hooks'));


class sirshurf_wpmu_dev_facebook {

	public $data;
	public $model;

	function __construct () {
	}

	function add_hooks(){
		if (is_admin()){
	
		}

		$strKey = "";
		foreach((array)$GLOBALS['wp_filter']['wp_head'][10] as $strFilterName =>$arrFilter){
			if (strpos($strFilterName,'inject_opengraph_info') !== FALSE){
				$strKey = $strFilterName;
				break;
			}
		}
		remove_action('wp_head', $strKey);
		add_action('wp_head', array($this, 'inject_opengraph_info'));


		$strSaveKey = "";
		foreach((array)$GLOBALS['wp_filter']['post_updated'][10] as $strFilterName =>$arrFilter){
			if (strpos($strFilterName,'publish_post_on_facebook') !== FALSE){
				$strSaveKey = $strFilterName;
				break;
			}
		}
		if (!empty($strSaveKey)){
			remove_action('post_updated', $strSaveKey);
			add_action('post_updated', array($this, 'publish_post_on_facebook'), 10, 3);
		}

		$strSaveKey = "";
		foreach((array)$GLOBALS['wp_filter']['save_post'][10] as $strFilterName =>$arrFilter){
			if (strpos($strFilterName,'publish_post_on_facebook') !== FALSE){
				$strSaveKey = $strFilterName;
				break;
			}
		}
		if (!empty($strSaveKey)){
			remove_action('save_post', $strSaveKey);
			add_action('save_post', array($this, 'publish_post_on_facebook'), 10, 3);
		}



	}


	/**
	 * Inject OpenGraph info in the HEAD
	 */
	function inject_opengraph_info () {
		$this->data =& Wdfb_OptionsRegistry::get_instance();
		$title = $url = $site_name = $description = $id = $image = false;
		if (is_singular()) {
			if (have_posts()) while (have_posts()) {
				the_post();
				$id = get_the_ID();
				$post = get_post($id );

				$title = get_the_title($post->post_title);
				$url = get_permalink();
				$site_name = get_option('blogname');
				$description = $this->sfc_base_make_excerpt($post);
				$description = htmlspecialchars($description,ENT_QUOTES,'UTF-8');
//var_dump($description);
			}
		} else {
			$title = get_option('blogname');
			$url = home_url('/');
			$site_name = get_option('blogname');
			$description = get_option('blogdescription');
		}
		$image = wdfb_get_og_image($id);

//var_dump($post);
//var_dump($description);
//exit();
		// App ID
		if (!defined('WDFB_APP_ID_OG_SET')) {
			$app_id = trim($this->data->get_option('wdfb_api', 'app_key'));
			if ($app_id) {
				echo "<meta property='fb:app_id' content='{$app_id}' />\n";
				define('WDFB_APP_ID_OG_SET', true);
			}
		}

		// Type
		if ($this->data->get_option('wdfb_opengraph', 'og_custom_type')) {
			if (!is_singular()) {
				$type = $this->data->get_option('wdfb_opengraph', 'og_custom_type_not_singular');
				$type = $type ? $type : 'website';
			} else {
				$type = $this->data->get_option('wdfb_opengraph', 'og_custom_type_singular');
				$type = $type ? $type : 'article';
			}
			if (is_home() || is_front_page()) {
				$type = $this->data->get_option('wdfb_opengraph', 'og_custom_type_front_page');
				$type = $type ? $type : 'website';
			}
		}
		$type = $type ? $type : (is_singular() ? 'article' : 'website');
		echo "<meta property='og:type' content='{$type}' />\n";

		// Defaults
		if ($title) echo "<meta property='og:title' content='{$title}' />\n";
		if ($url) echo "<meta property='og:url' content='{$url}' />\n";
		if ($site_name) echo "<meta property='og:site_name' content='{$site_name}' />\n";
		if ($description) echo "<meta property='og:description' content='{$description}' />\n";
		if ($image) echo "<meta property='og:image' content='{$image}' />\n";

		$extras = $this->data->get_option('wdfb_opengraph', 'og_extra_headers');
		$extras = $extras ? $extras : array();
		foreach ($extras as $extra) {
			$name = apply_filters('wdfb-opengraph-extra_headers-name', @$extra['name']);
			$value = apply_filters('wdfb-opengraph-extra_headers-value', @$extra['value'], @$extra['name']);
			if (!$name || !$value) continue;
			echo "<meta property='{$name}' content='{$value}' />\n";
		}
	}

	// code to create a pretty excerpt given a post object
	function sfc_base_make_excerpt($post) { 
		$text = "";

		if (function_exists('get_wds_options') && function_exists('wds_get_value')){			
			$wds_options = get_wds_options();
			$text = wds_get_value('metadesc');
		}

		if (empty($text)){
			if ( !empty($post->post_excerpt) ) 
				$text = $post->post_excerpt;
			else 
				$text = $post->post_content;
		}
	
		$text = strip_shortcodes( $text );

		// filter the excerpt or content, but without texturizing
		if ( empty($post->post_excerpt) ) {
			remove_filter( 'the_content', 'wptexturize' );
			$text = apply_filters('the_content', $text);
			add_filter( 'the_content', 'wptexturize' );
		} else {
			remove_filter( 'the_excerpt', 'wptexturize' );
			$text = apply_filters('the_excerpt', $text);
			add_filter( 'the_excerpt', 'wptexturize' );
		}

		$text = str_replace(']]>', ']]&gt;', $text);
		$text = wp_strip_all_tags($text);
		$text = str_replace(array("\r\n","\r","\n"),' ',$text);

		$excerpt_more = apply_filters('excerpt_more', '[...]');
		$excerpt_more = html_entity_decode($excerpt_more, ENT_QUOTES, 'UTF-8');
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		$text = htmlspecialchars_decode($text);
	
		$max = min(1000,apply_filters('sfc_excerpt_length',1000));
		$max -= strlen ($excerpt_more) + 1;
		$max -= strlen ('</fb:intl>') * 2 - 1;

		if ($max<1) return ''; // nothing to send
	
		if (strlen($text) >= $max) {
			$text = substr($text, 0, $max);
			$words = explode(' ', $text);
			array_pop ($words);
			array_push ($words, $excerpt_more);
			$text = implode(' ', $words);
		}

		return $text;
	}


	function publish_post_on_facebook ($id, $new, $old) {
		$this->data =& Wdfb_OptionsRegistry::get_instance();

		$this->model = new Wdfb_Model();
		if (!$id) return false;

		$post_id = $id;
		if ($rev = wp_is_post_revision($post_id)) $post_id = $rev;

		// Should we even try?
		if (
			!$this->data->get_option('wdfb_autopost', 'allow_autopost')
			&&
			!@$_POST['wdfb_metabox_publishing_publish']
		) return false;

		$post = get_post($post_id);
		if ('publish' != $post->post_status) return false; // Draft, auto-save or something else we don't want

		$is_published = get_post_meta($post_id, 'wdfb_published_on_fb', true);
		if ($is_published && !@$_POST['wdfb_metabox_publishing_publish']) return true; // Already posted and no manual override, nothing to do
		if ($old && 'publish' == $old->post_status && !@$_POST['wdfb_metabox_publishing_publish']) return false; // Previously published, we don't want to override

		$post_type = $post->post_type;
		$post_title = @$_POST['wdfb_metabox_publishing_title'] ? stripslashes($_POST['wdfb_metabox_publishing_title']) : $post->post_title;

		// If publishing semi-auto, always use wall
		$post_as = @$_POST['wdfb_metabox_publishing_publish'] ? 'feed' : $this->data->get_option('wdfb_autopost', "type_{$post_type}_fb_type");
		$post_to = @$_POST['wdfb_metabox_publishing_account'] ? $_POST['wdfb_metabox_publishing_account'] : $this->data->get_option('wdfb_autopost', "type_{$post_type}_fb_user");
		if (!$post_to) return false; // Don't know where to post, bail

		$as_page = false;
		if ($post_to != $this->model->get_current_user_fb_id()) {
			$as_page = isset($_POST['wdfb_post_as_page']) ? $_POST['wdfb_post_as_page'] : $this->data->get_option('wdfb_autopost', 'post_as_page');
		}

		if (!$post_as) return true; // Skip this type
		$post_content = strip_shortcodes($post->post_content);

		switch ($post_as) {
			case "notes":
				$send = array (
					'subject' => $post_title,
					'message' => $post_content,
				);
				break;
			case "events":
				$send = array(
					'name' => $post_title,
					'description' => $post_content,
					'start_time' => time(),
					'location' => 'someplace',
				);
				break;
			case "feed":
			default:
				$use_shortlink = $this->data->get_option('wdfb_autopost', "type_{$post_type}_use_shortlink");
				$permalink = $use_shortlink ? wp_get_shortlink($post_id) : get_permalink($post_id);
				$permalink = $permalink ? $permalink : get_permalink($post_id);
				$picture = wdfb_get_og_image($post_id);
				$send = array(
					'caption' => substr($post_content, 0, 999),
					'message' => $post_title,
					'link' => $permalink,
					'name' => $post->post_title,
					'description' => $this->sfc_base_make_excerpt($post), //get_option('blogdescription'),
					'actions' => array (
						'name' => __('Share', 'wdfb'),
						'link' => 'http://www.facebook.com/sharer.php?u=' . rawurlencode($permalink),
					),
				);							
				if ($picture){
					$arrUrl = explode('/',$picture);
					$intLastEl = count($arrUrl)-1;
					$arrUrl[$intLastEl] = urlencode($arrUrl[$intLastEl]);
					$strPic = implode('/',$arrUrl);
					$send['picture'] = $strPic;
				}
				break;
		}
		$res = $this->model->post_on_facebook($post_as, $post_to, $send, $as_page);
		if ($res) update_post_meta($post_id, 'wdfb_published_on_fb', 1);
		add_filter('redirect_post_location', create_function('$loc', 'return add_query_arg("wdfb_published", ' . (int)$res . ', $loc);'));
	}

}



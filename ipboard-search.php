<?php
/**
 * Plugin Name: IP.Board Search
 * Plugin URI:  https://thekrotek.com/wordpress-extensions/ipboard-search
 * Description: Allows you to search for IP.Board (IPS Community) topics and posts right from WordPress.
 * Version:     1.0.0
 * Author:      The Krotek
 * Author URI:  https://thekrotek.com
 * Text Domain: ipbsearch
 * License:     GPL2
*/

defined("ABSPATH") or die("Restricted access");

$ipbsearch = new IPBoardSearch();

class IPBoardSearch
{
	public $debug = false;
	public $name = 'ipbsearch';
	public $basedir;
	public $basename;
	public $params;
	public $prefix;
	public $ipbdb;
	public $url;
	public $paged;	
	
	public function __construct()
	{
		$this->basedir = plugin_dir_path(__FILE__);
		$this->basename = plugin_basename(__FILE__);
		$this->params = get_option($this->name.'_params', array());
		$this->prefix = $this->getOption('dbprefix');

		$this->ipbdb = new WPDB($this->getOption('dbusername'), $this->getOption('dbpassword'), $this->getOption('dbname'), $this->getOption('dbhost'));

		add_action('init', array($this, 'init'));
		add_action('admin_menu', array($this, 'admin_menu'));
		
		if ($this->debug) {
			ini_set('error_reporting', E_ALL);
			ini_set("display_errors", 1);
		}		
	}
	
	public function init()
	{
		add_action('admin_enqueue_scripts', array($this, 'loadAdminScripts'));
		add_action('the_post', array($this, 'updatePostData'));
		
		add_filter('plugin_row_meta', array($this, 'updatePluginMeta'), 10, 2);
		add_filter('pre_get_posts', array($this, 'updateQuery'), 1000, 1);
		add_filter('the_posts', array($this, 'getIPBPosts'), 1000, 1);
		add_filter('the_permalink', array($this, 'updatePostLink'), 1000, 1);
		add_filter('post_thumbnail_html', array($this, 'updateThumbnail'), 1000, 1);
		add_filter('author_link', array($this, 'updateAuthorLink'), 1000, 1);
		add_filter('the_author', array($this, 'updateAuthorName'), 1000, 1);
		add_filter('the_category', array($this, 'updateCategories'), 1000, 1);
				
		$this->url = $this->getOption('url');
		
		if (preg_match("/\/$/", $this->url)) $this->url = substr($this->url, -1);
		
		$this->paged = 0;
		
		load_plugin_textdomain($this->name, false, dirname($this->basename).'/languages');
	}
	
    public function loadAdminScripts($hook)
    {
    	if ($hook == 'settings_page_'.$this->name) {
    		wp_enqueue_media();
			wp_enqueue_style($this->name, plugins_url('', __FILE__).'/assets/style.css'.($this->debug ? '?v='.time() : ''), array(), null);
		}
    }
    	
	public function admin_menu()
	{
    	add_options_page(__('heading', $this->name), __('heading', $this->name), 'manage_options', $this->name, array($this, 'addOptionsPage'));
	}
		
	public function addOptionsPage()
	{
		require($this->basedir.'settings.php');
	}
		
	public function updateQuery($query)
	{
  		if (is_search() && $query->is_main_query() && !is_admin()) {
    		$this->paged = $query->get('paged') ? $query->get('paged') : 1;
    		
    		$query->set('paged', NULL);
    		$query->set('nopaging', true);
  		}
	}
	
	public function getIPBPosts($posts)
	{
		global $wp_query;
		
		if (is_search() && $wp_query->is_main_query() && !is_admin()) {
			if ($this->ipbdb) {
				$text = $wp_query->query_vars['s'];
			
				$limit = $this->getOption('limit', '0');
				$phrase = $this->getOption('phrase', 'exact');
				$ordering = $this->getOption('ordering', 'topic');				
				$titles = $this->getOption('titles', '0');
				$members = $this->getOption('members', '0');
				$forums = $this->getOption('forums', array());
				$action = $this->getOption('action', 'exclude');
		
				$wheres = array();

				if (!$this->getOption('closed', '0')) $wheres[] = "t.state = 'open'";
		
				if (!empty($forums)) $wheres[] = "t.forum_id ".($action == 'exclude' ? "NOT " : "")."IN (".implode(',', $forums).")";
		
				$text = trim($text);
		
				if ($text == '') return $posts;
						
				switch ($phrase) {
					case 'exact':
						$string = "t.title LIKE '%".esc_attr($text)."%'";
			
						if (!$titles) $string .= " OR p.post LIKE '%".esc_attr($text)."%'";
						if ($members) $string .= " OR p.author_name LIKE '%".esc_attr($text)."%'";
				
						$wheres[] = $string;
				
						break;
					case 'any':
					default:
						$words = explode(" ", $text);
			
						foreach ($words as $word) {
							$string = "t.title LIKE '%".esc_attr($word)."%'";
					
							if (!$titles) $string .= " OR p.post LIKE '%".esc_attr($word)."%'";
							if ($members) $string .= " OR p.author_name LIKE '%".esc_attr($word)."%'";
					
							$wheres2[] = $string;
						}
				
						$wheres[] = "(".implode(($phrase == "all" ? ") AND (" : ") OR ("), $wheres2).")";
				
						break;
				}
		
				$wheres[] = 'p.pdelete_time = 0';
				$wheres[] = 'p.queued = 0';
		
				$where = "(".implode(") AND (", $wheres).")";

				$order = "";
		
				switch ($ordering) {
					case 'topic':
						$order = (!$titles ? " ORDER" : " GROUP")." BY t.title ASC";
						break;
					case 'views':
						$order = " ORDER BY t.views DESC";
						break;				
					case 'oldest':
						$order = " ORDER BY p.post_date ASC";
						break;
					case 'newest':
					default:
						$order = " ORDER BY p.post_date DESC";
						break;
				}

				$sql = "";

				$sql .= "SELECT p.pid AS post_id, p.post AS post_content, p.post_date, p.topic_id, p.author_id, p.author_name, ";
				$sql .= "t.title AS post_title, t.title_seo AS post_seo, t.posts AS comment_count, t.forum_id, ";
				$sql .= "m.pp_main_photo AS avatar, m.email, m.members_seo_name AS author_seo, ";
				$sql .= "w.word_default AS forum_name, f.name_seo AS forum_seo ";
				$sql .= "FROM ".$this->prefix."forums_topics AS t ";
				$sql .= "LEFT JOIN ".$this->prefix."forums_forums AS f ON f.id = t.forum_id ";
				$sql .= "LEFT JOIN ".$this->prefix."forums_posts AS p ON p.topic_id = t.tid ";
				$sql .= "LEFT JOIN ".$this->prefix."core_members AS m ON p.author_id = m.member_id ";
				$sql .= "LEFT JOIN ".$this->prefix."core_sys_lang AS l ON lang_default = 1 ";
				$sql .= "LEFT JOIN ".$this->prefix."core_sys_lang_words AS w ON (w.word_key = CONCAT('forums_forum_', f.id) AND w.lang_id = l.lang_id) ";		
				$sql .= "WHERE ".$where.$order.($limit ? " LIMIT 0, ".$limit : "");

				$results = $this->ipbdb->get_results($sql);
			
				if (!empty($results)) {
					foreach ($results as $key => $post) {
 						if (!empty($_COOKIE['ips4_ipsTimezone'])) {
							$post_date = $this->properTime($post->post_date, $_COOKIE['ips4_ipsTimezone']);
						} else {
							$post_date = $post->post_date;
						}
						
						$post->ID = 0;
						$post->post_author = 0;
						$post->post_date = date('Y-m-d H:i:s', $post_date);
						$post->post_date_gmt = date('Y-m-d H:i:s', $post_date);
						$post->post_excerpt = "";
						$post->post_status = "publish";
						$post->comment_status = "open";
    					$post->ping_status =  "open";
	    				$post->post_password = "";
		    			$post->to_ping =  "";
    					$post->pinged = "";
    					$post->post_modified = date('Y-m-d H:i:s', $post_date);
    					$post->post_modified_gmt = date('Y-m-d H:i:s', $post_date);
    					$post->post_content_filtered = "";
		    			$post->post_parent = 0;
    					$post->guid = "";
    					$post->post_name = $post->post_seo;
    					$post->forum_link = $this->url."/forum/".$post->forum_id."-".$post->forum_seo;
    					$post->post_link = $this->url."/topic/".$post->topic_id."-".$post->post_seo."/?do=findComment&comment=".$post->post_id;
	    				$post->author_link = $this->url."/profile/".$post->author_id."-".$post->author_seo;
	    				$post->menu_order = 0;
    					$post->post_type = "ipboard";
    					$post->post_mime_type = "";
    					$post->filter = "raw";
    				
    					$results[$key] = $post;
		    		}
  				
  					remove_filter('the_posts', 'getIPBPosts');
  					
  					$place = $this->getOption('position', 'after');
  				
  					if ($place == 'after') $merged = array_merge($posts, $results);
  					elseif ($place == 'before') $merged = array_merge($results, $posts);
  					elseif ($place == 'replace') $merged = $results;
  				
  					$perpage = get_option('posts_per_page');
  				
	  				$wp_query->found_posts = ($place != 'replace' ? $wp_query->found_posts : 0) + count($results);
    				$wp_query->posts = array_slice($merged, ($perpage * ($this->paged - 1)), $perpage);
					$wp_query->set('paged', $this->paged);
					$wp_query->post_count = count($wp_query->posts);
  					$wp_query->max_num_pages = ceil($wp_query->found_posts / $perpage);
  				
  					$this->paged = 0;
  				
  					return $wp_query->posts;
  				}
			} else {
				error_log(__('heading', $this->name).": ".__('error_database', $this->name));
			}
		}
		
		return $posts;
	}
	
	public function updatePostData($post)
	{
		global $authordata;
		
		if ($post->post_type == "ipboard") {
			$authordata = new stdClass();
			$authordata->ID = $post->post_author;
			$authordata->user_email = $post->email;
			$authordata->user_url = $post->author_link;
			$authordata->user_nicename = $post->author_seo;
			$authordata->display_name = $post->author_name;
		}
		
		return $post;
	}
		
	public function updatePostLink($link)
	{
		global $post;
		
		if ($post->post_type == "ipboard") {
			$link = $post->post_link;
		}
		
		return $link;
	}
	
	public function updateThumbnail($html)
	{
		global $post;
		
		if ($post->post_type == "ipboard") {
			$html = ($this->getOption('thumbnail', '') ? "<a href='".esc_url($post->post_link)."'><img src='".esc_attr($this->getOption('thumbnail'))."'></a>" : "");
		}
		
		return $html;
	}
		
	public function updateAuthorLink($link)
	{
		global $post;
		
		if ($post->post_type == "ipboard") {
			$link = $post->author_link;
		}
		
		return $link;
	}
	
	public function updateAuthorName($name)
	{
		global $post;
		
		if ($post->post_type == "ipboard") {
			$name = $post->author_name;
		}
		
		return $name;
	}
	
	public function updateCategories($categories)
	{
		global $post;
		
		if ($post->post_type == "ipboard") {
			$categories  = $this->getOption('parent', 0) ? "<a href='".esc_url($this->url)."' rel='category'>".__('forum', $this->name)."</a> &rsaquo; " : "";
			$categories .= "<a href='".esc_url($post->forum_link)."' rel='category'>".$post->forum_name."</a>";
		}
		
		return $categories;
	}
	
	public function updatePluginMeta($links, $file)
	{
		if ($file == plugin_basename(__FILE__)) {
			$links = array_merge($links, array('<a href="options-general.php?page=ipbsearch">'.__('Settings', 'ipbsearch').'</a>'));
			$links = array_merge($links, array('<a href="https://thekrotek.com/support">'.__('Donate & Support', 'ipbsearch').'</a>'));
		}
	
		return $links;
	}	
		
	public function getOption($name, $default = "")
	{
		return isset($this->params[$name]) ? $this->params[$name] : $default;
	}

	public function properTime($basetime, $zone)
	{
		$serverzone = new DateTimeZone(date_default_timezone_get());
		$servertime = new DateTime("now", $serverzone);
		$serveroffset = $serverzone->getOffset($servertime);
		
		if (!is_numeric($zone)) {
			$remotezone = new DateTimeZone($zone);
			$remotetime = new DateTime("now", $remotezone);
			$remoteoffset = $remotezone->getOffset($remotetime);
		} else {
			$remoteoffset = $zone;
		}

		$offset = $serveroffset - $remoteoffset;

		if ($offset > 0) $newtime = $basetime - abs($offset);
		else $newtime = $basetime + abs($offset);

		return $newtime;
	}	
}

?>

<?php   
#     /* 
#     Plugin Name: Tweet old post
#     Plugin URI: http://www.readythemes.com/tweet-old-post-lite/
#     Description: Wordpress plugin that helps you to keeps your old posts alive by tweeting about them and driving more traffic to them from twitter. It also helps you to promote your content. You can set time and no of tweets to post to drive more traffic.For questions, comments, or feature requests, contact me! <a href="http://www.readythemes.com/?r=top">Ionut Neagu</a>.
#     Author: ReadyThemes 
#     Version: 4.0.10
#     Author URI: http://www.readythemes.com/
#     */  
 

require_once('top-admin.php');
require_once('top-core.php');
require_once('top-excludepost.php');

define ('top_opt_1_HOUR', 60*60);
define ('top_opt_2_HOURS', 2*top_opt_1_HOUR);
define ('top_opt_4_HOURS', 4*top_opt_1_HOUR);
define ('top_opt_8_HOURS', 8*top_opt_1_HOUR);
define ('top_opt_6_HOURS', 6*top_opt_1_HOUR); 
define ('top_opt_12_HOURS', 12*top_opt_1_HOUR); 
define ('top_opt_24_HOURS', 24*top_opt_1_HOUR); 
define ('top_opt_48_HOURS', 48*top_opt_1_HOUR); 
define ('top_opt_72_HOURS', 72*top_opt_1_HOUR); 
define ('top_opt_168_HOURS', 168*top_opt_1_HOUR); 
define ('top_opt_INTERVAL', 4);
define ('top_opt_AGE_LIMIT', 30); // 120 days
define ('top_opt_MAX_AGE_LIMIT', 60); // 120 days
define ('top_opt_OMIT_CATS', "");
define('top_opt_TWEET_PREFIX',"");
define('top_opt_ADD_DATA',"false");
define('top_opt_URL_SHORTENER',"is.gd");
define('top_opt_HASHTAGS',"");
define('top_opt_no_of_tweet',"1");
define('top_opt_post_type',"post");


   function top_admin_actions() {  
        add_menu_page("Tweet Old Post", "Tweet Old Post", 1, "TweetOldPost", "top_admin");
        add_submenu_page("TweetOldPost", __('Exclude Posts','TweetOldPost'), __('Exclude Posts','TweetOldPost'), 1, __('ExcludePosts','TweetOldPost'), 'top_exclude');
		
    }  
    
  	add_action('admin_menu', 'top_admin_actions');  
	add_action('admin_head', 'top_opt_head_admin');
 	add_action('init','top_tweet_old_post');
        add_action('admin_init','top_authorize',1);
        
        function top_authorize()
        {
            if ( $_GET['page'] == 'TweetOldPost' ) {
                   if ( isset( $_REQUEST['oauth_token'] ) ) {
			$auth_url= str_replace('oauth_token', 'oauth_token1', top_currentPageURL());
			$top_url = get_option('top_opt_admin_url') . substr($auth_url,strrpos($auth_url, "page=TweetOldPost") + strlen("page=TweetOldPost"));
                        echo '<script language="javascript">window.location.href="'.$top_url.'";</script>';
                        die;
                    }
                   
                   
            }
        }
        
add_filter('plugin_action_links', 'top_plugin_action_links', 10, 2);

function top_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        // The "page" query string value must be equal to the slug
        // of the Settings admin page we defined earlier, which in
        // this case equals "myplugin-settings".
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=TweetOldPost">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}

?>
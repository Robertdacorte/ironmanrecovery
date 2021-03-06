<?php if(!defined('ABSPATH')) die('not allowed');

class mymail_template {
	
	
	public $raw;
	public $doc;
	public $data;
	public $modules;
	
	public $path;
	public $url;
	
	private $slug;
	private $file;

	private $templatepath;
	private $download_url = 'https://bitbucket.org/revaxarts/mymail-template/get/master.zip';
	private $headers = array(
			'name' => 'Template Name',
			'label' => 'Name',
			'uri' => 'Template URI',
			'description' => 'Description',
			'author' => 'Author',
			'author_uri' => 'Author URI',
			'version' => 'Version',
	);
	
	
	public function __construct($slug = NULL, $file = 'index.html') {
	
		$this->file = $file;

		$this->path = MYMAIL_UPLOAD_DIR.'/templates';
		$this->url = MYMAIL_UPLOAD_URI.'/templates';
		
		if(!is_null($slug)){
			$this->load_template($slug);
		}

	}
	
	public function get($modules = true, $absolute_img = false) {
		if ( !$modules ) {
			
			if(!$this->doc) return '';
			$xpath = new DOMXpath($this->doc);
			$modulecontainer = $xpath->query("//*/modules");
			
			foreach( $modulecontainer as $container) {

				$activemodules = $this->get_modules(true);
				while ($container->hasChildNodes()) {
					$container->removeChild($container->firstChild);
				}
				foreach ($activemodules as $domElement){
					$domNode = $this->doc->importNode($domElement, true);
					$container->appendChild($domNode);
				}
				
			}
			
			$html = $this->doc->saveHTML();
			
		}else{
		
			$html = $this->raw;	
			
		}
		if(strpos($html, 'data-editable')){
				
			$x = $this->new_template_language($html);
			$html = $x->saveHTML();
				
		}
		if($absolute_img) $html = $this->make_img_absolute( $html );
		
		return $html;
	}
	
	private function make_img_absolute( $html ) {
		preg_match_all("/(src|background)=[\"'](.*)[\"']/Ui", $html, $images);
		$images = array_unique( $images[2] );
		foreach ( $images as $image ) {
			if(substr($image, 0, 7) == 'http://') continue;
			if(substr($image, 0, 8) == 'https://') continue;
			$html = str_replace( $image, $this->url .'/' . $this->slug . '/' . $image, $html );
		}
		return $html;
	}
	
	
	public function load_template($slug = '') {
	
		$this->templatepath = $this->path .'/' . $slug;
		
		$file = $this->templatepath . '/' . $this->file;
		
		if (!file_exists( $file ) )
			return false;
			
		if (!class_exists('DOMDocument'))
			die("PHP Fatal error: Class 'DOMDocument' not found");
		
		$doc = new DOMDocument();
		$doc->validateOnParse = true;
		$doc->formatOutput = true;
		
		@$doc->loadHTMLFile($file);
		
		$doc = $this->new_template_language($doc);
		
		$raw = $doc->saveHTML();
		
		$data = $this->get_template_data( $file );
		if($data['name']){
			$raw = preg_replace('#<!--(.*?)-->#s', '', $raw, 1);
			$this->data = $data;
		}
		
		$this->slug = $slug;
		$this->doc = $doc;
		$this->raw = $raw;


		
	}
	
	
	public function remove_template($slug = '') {
		
		$this->templatepath = $this->path .'/' . $slug;
		
		if ( !file_exists( $this->templatepath . '/index.html' ) )
			return false;
			
		mymail_require_filesystem();
		
		global $wp_filesystem;
		return $wp_filesystem->delete($this->templatepath, true);
	}
	
	public function upload_template() {
		$result = wp_handle_upload( $_FILES['templatefile'], array(
			'mimes' => array('zip' => 'multipart/x-zip'),
		) );
		if(isset($result['error'])){
			return $result;	
		}
		
		mymail_require_filesystem();
		
		$tempfolder = MYMAIL_UPLOAD_DIR.'/uploads';
		
		wp_mkdir_p($tempfolder);
		
		return $this->unzip_template($result['file'], $tempfolder);
		
	}
	
	
	public function unzip_template($templatefile, $uploadfolder, $renamefolder = '') {
		
		if(!unzip_file($templatefile, $uploadfolder)){
			return false;	
		}
		//die();
		if($folders = scandir($uploadfolder)){
		
			global $wp_filesystem;
			
			mymail_require_filesystem();
		
			foreach($folders as $folder){
				if(in_array($folder, array('.', '..')) || !is_dir($uploadfolder.'/'.$folder)) continue;
				
				if(!empty($renamefolder)){
					
					$renamefolder = sanitize_file_name($renamefolder);
					
					if($wp_filesystem->move($uploadfolder.'/'.$folder, $uploadfolder.'/'.$renamefolder, true)){
						$folder = $renamefolder;
					}else{
						return false;
					}
				}
				
				//need index.html file
				if(file_exists($uploadfolder.'/'.$folder.'/index.html')){
					$data = $this->get_template_data($uploadfolder.'/'.$folder.'/index.html');
					
					//with name value
					if(!empty($data['name'])){
						wp_mkdir_p($this->path .'/'.$folder);
						copy_dir($uploadfolder.'/'.$folder, $this->path .'/'.$folder);
					}
					
				}
				
				if(file_exists($uploadfolder.'/'.$folder.'/colors.json')){
				
					$colors = $wp_filesystem->get_contents($uploadfolder.'/'.$folder.'/colors.json');
					
					if($colors){
						$colorschemas = json_decode($colors);
						
						$customcolors = get_option('mymail_colors', array());
						
						if(!isset($customcolors[$folder])){
						
							$customcolors[$folder] = array();
							foreach($colorschemas as $colorschema){
								$hash = md5(implode('', $colorschema));
								$customcolors[$folder][$hash] = $colorschema;
							}
							
							update_option('mymail_colors', $customcolors);
							
						}
						

					}
				}
			}
			
			return $wp_filesystem->delete($uploadfolder, true);
		}

		return false;	
		
	}
	
	
	public function create_new($name, $content = '', $modules = true, $overwrite = true) {
	
		if(!$this->slug) return false;
		
		$filename = strtolower(sanitize_file_name($name).'.html');
		
		if($name == __('Base', 'mymail')) $filename = 'index.html';
		if($name == __('Notification', 'mymail')) $filename = 'notification.html';
		
		if(!$overwrite && file_exists($this->templatepath. '/' . $filename)) return false;
		
		$pre = '<!--'."\n\n";
		
		foreach($this->data as $k => $v){
			$pre .= "\t".$this->headers[$k].": ".($k == 'label' ? $name : $v)."\n";
		}

		$pre .= "\n-->\n";
		
		if($modules){
			
			//$content = preg_replace('#class=(["\'])?(.*)(active)(.*)("|\')?#i', 'class=$1$2$4$5', $content);
			
			//search for active modules
			preg_match_all('#<module[^>]*class=(["\'])?(.*)(active)(.*)("|\')?>#', $content, $hits);
			if(!empty($hits[0])){
				foreach($hits[0] as $hit){
					//cleanup
					$new = str_replace(array('style','class="active"','=""'), '', $hit);
					//make them custom and active
					$new = str_replace('<module ', '<module custom active ', $new);
					$content = str_replace($hit, $new, $content);
					
				}
			}
			
			//remove active from class
			$allmodules = $this->get_modules_html();
			$content = str_replace('</modules>', $allmodules.'</modules>', $content);
			
		}else{
			//remove module from class
			$content = preg_replace('#<modules?[^>]*>#is', '', $content);
			$content = preg_replace('#<\/modules?>#is', '', $content);

			//$content = preg_replace('#class=(["\'])?(.*)(module)(.*)("|\')?#i', 'class=$1$2$4$5', $content);
		}
		
		
		//add some linebreaks after modules
		$content = preg_replace('#<modules[^>]*>#is', "<modules>\n", $content);
		$content = str_replace('<module', "\n<module", $content);
		$content = str_replace('</modules>', "\n</modules>\n", $content);
		$content = str_replace('</module>', "\n</module>", $content);
		//remove absolute path to images from the template
		$content = str_replace('src="'. $this->url .'/' . $this->slug. '/', 'src="', $content);

		$content = str_replace( array('%7B', '%7D') , array( '{', '}' ), $content );

		global $wp_filesystem;
		mymail_require_filesystem();
		
		if ($wp_filesystem->put_contents( $this->templatepath. '/' . $filename, $pre.$content, FS_CHMOD_FILE) ) {
			return $filename;
		}
		
		return false;	
		
	}
	
	
	public function get_modules_html($activeonly = false, $separator = "\n\n") {
	
		return $this->make_img_absolute( $this->get_html_from_nodes($this->get_modules($activeonly), $separator) );
	}
	
	
	public function get_modules($activeonly = false) {
		
		if(!$this->slug) return false;

		$xpath = new DOMXpath($this->doc);
		
		$modules = ($activeonly) 
		 ? $xpath->query("//*/module[@active]")
		 : $xpath->query("//*/module");
		 
		return $modules;
		
	}
	
	
	public function get_styles() {
		if(!$this->raw) return '';
		preg_match_all('#<style[^>]*>(.*?)<\/style>#is', $this->raw, $matches);
		$style = '';
		if(!empty($matches[1])){
			foreach($matches[1] as $styleblock){
				$style .= $styleblock;
			}
		}
		
		return $style;
		
	}
	
	public function get_head() {
		if(!$this->raw) return '';
		
		if($pos = strpos($this->raw, '<body')){
			return trim(substr($this->raw, 0, $pos));
		}
		return '';
		
	}
	
	public function get_background_links($html = '') {
		if(empty($html)) $html = $this->raw;
		if(!$html) return array();
		preg_match_all("/background=[\"'](.*)[\"']/Ui", $html, $links);

		return array_filter(array_unique($links[1]));
	}
	
	
	
	public function new_template_language($doc_or_html) {
		
		if(!is_string($doc_or_html)){
			$doc = $doc_or_html;
		}else{
			$doc = new DOMDocument();
			$doc->validateOnParse = true;
			@$doc->loadHTML($doc_or_html);

		}
		$xpath = new DOMXpath($doc);
		
		//check if it's a new template
		$is_new_template = $doc->getElementsByTagName('single');
		
		if($is_new_template->length) return $doc;

		
		// Module container
		$modulecontainer = $xpath->query("//*/div[@class='modulecontainer']");
			
		foreach( $modulecontainer as $container) {

			$this->dom_rename_element($container, 'modules', false);
			
		}
		
		//Modules
		
		$modules = $xpath->query("//*/div[contains(concat(' ',normalize-space(@class),' '),' module ')]");
			
		foreach( $modules as $module ) {

			$label = $module->getAttribute('data-module');
			$module->setAttribute('label', $label);
			$module->removeAttribute('data-module');
			if($module->hasAttribute('data-auto')) $module->setAttribute('auto', NULL);
			$this->dom_rename_element($module, 'module');
				
		}
		
		//images, editable
		$images = $xpath->query("//*/img[@data-editable]");
			
		foreach( $images as $image ) {

			$label = $image->getAttribute('data-editable');
			$image->setAttribute('editable', NULL);
			if($label) $image->setAttribute('label', $label);
			$image->removeAttribute('data-editable');
				
		}
		
		//other editable stuff
		$editables = $xpath->query("//*[@data-editable]");
		
		foreach( $editables as $editable ) {

			$label = $editable->getAttribute('data-editable');
			$editable->removeAttribute('data-editable');
			if($label) $editable->setAttribute('label', $label);
			
			if($editable->hasAttribute('data-multi')){
				
				$editable->removeAttribute('data-multi');
				$this->dom_rename_element($editable, 'multi');
			}else{
				
				$this->dom_rename_element($editable, 'single');
			}
				
		}
		
		//wrap a diff around (for old templates)
		$editables = $doc->getElementsByTagName('single');
			
		$div = $doc->createElement('div');
			
		foreach( $editables as $editable ) {
	
			$div_clone = $div->cloneNode();
			$editable->parentNode->replaceChild($div_clone,$editable);
			$div_clone->appendChild($editable);
			
		}
		$editables = $doc->getElementsByTagName('multi');
			
		foreach( $editables as $editable ) {
	
			$div_clone = $div->cloneNode();
			$editable->parentNode->replaceChild($div_clone,$editable);
			$div_clone->appendChild($editable);
			
		}
		
		
		//repeatable areas
		$repeatables = $xpath->query("//*/*[@data-repeatable]");
		
		foreach( $repeatables as $repeatable ) {

			$label = $repeatable->getAttribute('data-repeatable');
			$repeatable->setAttribute('repeatable', NULL);
			$repeatable->removeAttribute('data-repeatable');
				
		}
		
		
		//buttons and buttongroups
		$buttons = $xpath->query("//*/buttons");
		
		if(!$buttons->length){
		
			$buttons = $xpath->query("//*/div[@class='btn']");
			
			foreach( $buttons as $button ) {
	
				$button->removeAttribute('class');
				$this->dom_rename_element($button, 'buttons');
				
			}
			
			$buttons = $doc->getElementsByTagName('buttons');
			
			$new_div = $doc->createElement('div');
			$new_div->setAttribute('class','btn');
			
			foreach( $buttons as $button ) {
	
				$div_clone = $new_div->cloneNode();
				$button->parentNode->replaceChild($div_clone,$button);
				$div_clone->appendChild($button);
				
				$children = $button->childNodes;
				foreach( $children as $child ){
					if(strtolower($child->nodeName) == 'a'){
						$achildren = $child->childNodes;
						foreach( $achildren as $achild ){
							if(strtolower($achild->nodeName) == 'img'){
								$label = $achild->getAttribute('label');
								$achild->removeAttribute('editable');
							}
						}
						
						$child->setAttribute('editable', NULL);
						$child->setAttribute('label', $label);
					}
					
				}
					
			}
		
		}
		
		$styles = $doc->getElementsByTagName('style');
		
		foreach( $styles as $style ) {
		
			$style->nodeValue = str_replace('img{outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;display:block;}','img{outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;display:block;max-width:100%;}', $style->nodeValue);
			

		}
		
		return $doc;
		
	}
	
	public function get_templates($slugsonly = false) {
		
		$templates = array();
		$files = list_files($this->path);
		sort($files);
		foreach($files as $file){
			if(basename($file) == 'index.html'){
				
				$filename = str_replace($this->path .'/', '', $file);
				$slug = dirname($filename);
				if(!$slugsonly){
					$templates[$slug] = $this->get_template_data($file);
				}else{
					$templates[] = $slug;
				}
			}
		}
		return $templates;
		
	}
	
	public function get_files($slug = '') {
		
		if(empty($slug)) $slug = $this->slug;
		
		$templates = array();
		$files = list_files($this->path .'/'.$slug, 1);
		
		sort($files);
		
		$list = array(
			'index.html' => $this->get_template_data($this->path .'/'.$slug .'/index.html'),
		);
		
		if(file_exists($this->path .'/'.$slug .'/notification.html'))
			$list['notification.html'] = $this->get_template_data($this->path .'/'.$slug .'/notification.html');
			
		foreach($files as $file){
			
			if(strpos($file, '.html') && is_file($file)) $list[basename($file)] = $this->get_template_data($file);
			
		}
		
		return $list;
		
	}

	public function get_versions($slugsonly = false) {
		
		$templates = $this->get_templates();
		$return = array();
		foreach($templates as $slug => $data){
			
			$return[$slug] = $data['version'];
		}
		
		return $return;
		
	}
	
	public function get_updates() {
		$updates = get_site_transient( 'mymail_updates' );
		if(isset($updates['templates'])){
			$updates = $updates['templates'];
		}else{
			$updates = array();	
		}
		return $updates;
	}
	
	public function buttons( $basefolder = 'img' ) {
	
		$root = list_files($this->path .'/'.$this->slug.'/'.$basefolder, 1);
		
		sort($root);
		$folders = array();
		
		//common_button_folder_names in use for __($name, 'mymail')
		__('light', 'mymail');
		__('dark', 'mymail');
		
		foreach($root as $file){
		
			if(!is_dir($file)) continue;
			$rootbtn = '';
			
			?>
		<div class="button-nav-wrap">
			<?php
			$nav = $btn = '';
			$id = basename($file);
			$files = list_files(dirname($file).'/'.$id, 1);
			natsort($files);
			foreach($files as $file){
				if(is_dir($file)){
					$file = str_replace('//','/', $file);
					$name =  basename($file);
					$folders[] = $name;
					$nav .= '<a class="nav-tab" href="#buttons-'.$id.'-'.$name.'">'.__($name, 'mymail').'</a>';
					$btn .= $this->list_buttons(substr($file,0,-1), $id);
				}else{
					if(!in_array(strrchr($file, '.'), array('.png', '.gif', '.jpg', '.jpeg'))) continue;
					if($rootbtn) continue;
					$rootbtn = $this->list_buttons(dirname($file), 'root');
					
				}
			}
			
			if($nav) :?>
		<div id="button-nav-<?php echo $id ?>" class="button-nav nav-tab-wrapper hide-if-no-js" data-folders="<?php echo implode('-', $folders)?>"><?php echo $nav ?></div>
			<?php endif;
		echo $btn;
			?>
		</div>
		
		
		<?php if($rootbtn):?>
		<div class="button-nav-wrap button-nav-wrap-root"><?php echo $rootbtn; ?></div>
		<?php endif;
		
		}
		
		
		
		
	}
	
	
	public function list_buttons($folder, $id) {
		
		$files = list_files($folder, 1);
		
		$btn = '<ul class="buttons buttons-'.basename($folder).'" id="tab-buttons-'.$id.'-'.basename($folder).'">';
		
		foreach($files as $file){
		
			if(is_dir($file)) continue;
			if(!in_array(strrchr($file, '.'), array('.png', '.gif', '.jpg', '.jpeg'))) continue;
			
			$filename = str_replace($folder .'/', '', $file);
			$btn .= '<li><a class="btnsrc" title="'.substr($filename, 0, strrpos($filename, '.')).'" data-link="'.$this->get_social_link($filename).'"><img src="'.str_replace($this->path .'/', $this->url .'/', $file).'"></a></li>';
			
		}
		
		$btn .= '</ul>';

		return $btn;
		
		
	}
	
	
	public function get_social_link($file) {
		
		$network = substr($file, 0, strrpos($file, '.'));
		
		$links = array(
			'amazon' => 'http://amazon.com',
			'android' => 'http://android.com',
			'apple' => 'http://apple.com',
			'appstore' => 'http://apple.com',
			'behance' => 'http://www.behance.net/USERNAME',
			'blogger' => 'http://USERNAME.blogspot.com/',
			'delicious' => 'https://delicious.com/USERNAME',
			'deviantart' => 'http://USERNAME.deviantart.com',
			'digg' => 'http://digg.com/users/USERNAME',
			'dribbble' => 'http://dribbble.com/USERNAME',
			'drive' => 'https://drive.google.com',
			'dropbox' => 'https://dropbox.com',
			'ebay' => 'http://www.ebay.com',
			'facebook' => 'https://facebook.com/USERNAME',
			'flickr' => 'http://www.flickr.com/photos/USERNAME',
			'forrst' => 'http://forrst.me/USERNAME',
			'google' => 'http://www.google.com',
			'googleplus' => 'http://plus.google.com/USERNAME',
			'html5' => 'http://html5.com',
			'instagram' => 'http://instagram.com/USERNAME',
			'lastfm' => 'http://www.lastfm.de/user/USERNAME',
			'linkedin' => 'http://www.linkedin.com/in/USERNAME',
			'myspace' => 'http://www.myspace.com/USERNAME',
			'paypal' => 'http://paypal.com',
			'picasa' => 'http://picasa.com',
			'pinterest' => 'http://pinterest.com/USERNAME',
			'rss' => get_bloginfo('rss2_url'),
			'skype' => 'skype:USERNAME',
			'soundcloud' => 'http://soundcloud.com/USERNAME',
			'stumbleupon' => 'http://stumbleupon.com',
			'technorati' => 'http://technorati.com',
			'tumblr' => 'http://USERNAME.tumblr.com',
			'twitter' => 'https://twitter.com/USERNAME',
			'twitter_2' => 'https://twitter.com/USERNAME',
			'vimeo' => 'http://vimeo.com/USERNAME',
			'windows' => 'http://microsoft.com',
			'windows_8' => 'http://microsoft.com',
			'wordpress' => 'http://profiles.wordpress.org/USERNAME',
			'yahoo' => 'http://yahoo.com',
			'youtube' => 'http://youtube.com/user/USERNAME', 
		);
		
		return (isset($links[$network])) ? $links[$network] : '';
		
	}
	
	
	
	
	public function get_raw_template( $file = 'index.html') {
		if ( !file_exists( $this->path .'/' . $this->slug . '/' .$file) )
			return false;
		
		return file_get_contents( $this->path .'/' . $this->slug . '/'. $file );
	}
	
	

	/*----------------------------------------------------------------------*/
	/* Other
	/*----------------------------------------------------------------------*/
	
	
public function get_screenshot_OLD( $slug, $file = 'index.html', $size = 300 ) {
	
		global $wp_filesystem;

		$fileuri = $this->url .'/'.$slug.'/'.$file;
		$screenshotfile = MYMAIL_UPLOAD_DIR.'/screenshots/'.$slug.'_'.$file.'.jpg';
		$screenshoturi = MYMAIL_UPLOAD_URI.'/screenshots/'.$slug.'_'.$file.'.jpg';
		$file = $this->path .'/'.$slug.'/'.$file;
		
		//serve saved
		if(file_exists($screenshotfile) && file_exists($file) && filemtime($file) < filemtime($screenshotfile)){
			$url = $screenshoturi.'?c='.filemtime($screenshotfile);
		}else if(!file_exists($file) || substr($_SERVER['REMOTE_ADDR'], 0, 4) == '127.' || $_SERVER['REMOTE_ADDR'] == '::1'){
			$url = 'http://s.wordpress.com/wp-content/plugins/mshots/default.gif';
		}else{
			$url = 'http://s.wordpress.com/mshots/v1/'.(rawurlencode($fileuri.'?c='.md5_file($file))).'?w='.$size;
			
			$remote = wp_remote_get($url, array('redirection' => 0));
			
			if(wp_remote_retrieve_response_code($remote) == 200){

				$data = wp_remote_retrieve_body($remote);

				mymail_require_filesystem();
				
				if(!is_dir( dirname($screenshotfile) )) wp_mkdir_p( dirname($screenshotfile) ) ;
				
				$wp_filesystem->put_contents($screenshotfile, wp_remote_retrieve_body($remote), false );
			}
			
		}
		return $url;
	}
	
	
	
	
	
	public function copy_templates() {
	
		global $wpdb;
		
		if (is_network_admin() && is_multisite()) {
		
			$old_blog = $wpdb->blogid;
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			
		}else{
		
			$blogids = array(false);
			
		}
		
		mymail_require_filesystem();
		
		foreach ($blogids as $blog_id) {
		
			if($blog_id) switch_to_blog( $blog_id );
	
			$upload_folder = wp_upload_dir();
		
			if(!is_dir( $upload_folder['basedir'].'/myMail/templates' )){
				wp_mkdir_p(  $upload_folder['basedir'].'/myMail/templates' );
				copy_dir(MYMAIL_DIR . 'templates', $upload_folder['basedir'].'/myMail/templates' );
			}
		}
		
		if($blog_id) switch_to_blog($old_blog);
		

	}
	
	
	
	
	
	/*----------------------------------------------------------------------*/
	/* Privates
	/*----------------------------------------------------------------------*/
	

	
	private function get_html_from_nodes($nodes, $separator = ''){
	
		$parts = array();
		
		if(!$nodes) return '';
		foreach ($nodes as $node) {
			$parts[] = $this->get_html_from_node($node);
		}
	
		return implode($separator, $parts);
	}
	
	private function get_html_from_node($node){
	
		$html = $node->ownerDocument->saveXML($node);
		return $html;
		
	}
	
	
	private function dom_rename_element(DOMElement $node, $name, $attributes = true) {
		
		$renamed = $node->ownerDocument->createElement($name);
	
		if($attributes){
			foreach ($node->attributes as $attribute) {
				$renamed->setAttribute($attribute->nodeName, $attribute->nodeValue);
			}
		}
		while ($node->firstChild) {
			$renamed->appendChild($node->firstChild);
		}
	
		return $node->parentNode->replaceChild($renamed, $node);
	}
	
	
	private function get_template_data($file) {
	
		return mymail('templates')->get_template_data($file);
		
	}
	
	

}
?>
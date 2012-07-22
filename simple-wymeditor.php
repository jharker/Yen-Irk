<?php
/*
Plugin Name: Simple WYMeditor
Plugin URI: http://www.herewithme.fr/wordpress-plugins/simple-wymeditor
Description: Replace the default WordPress editor with <a href="http://www.wymeditor.org/"> WYMeditor</a> 0.4
Version: 0.4.6.1
Author: Amaury BALMER
Author URI: http://www.herewithme.fr

### Instructions ###
	1. Send plugin into "wp-content/plugins"
	2. Active it
	3. New editor visual is Ok.
	4. To customize predefined style, edit styles.css

### License ###
Copyright (C) 2007 Amaury Balmer (email : balmer.amaury@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

Class SimpleWYMeditor {
	var $version = '0.4.6.1';

	var $wymeditor_url = '';
	var $plugin_url = '';
	var $plugin_path = '';
	var $locale = '';

	var $wp_25 = false; // WordPress 2.5 ?
	var $wp_26 = false; // WordPress 2.6 ?
	
	function SimpleWYMeditor() {
		// Set URL & Path
		$this->plugin_url    = trailingslashit(get_option('siteurl')) . 'wp-content/plugins/' . basename(dirname(__FILE__)) .'/';
		$this->plugin_path   = ABSPATH . 'wp-content/plugins/' . basename(dirname(__FILE__)) .'/';
		$this->wymeditor_url = $this->plugin_url . 'wymeditor/';

		// Add JS in admin header
		add_action('admin_print_scripts', array(&$this, 'addAdminHead'));

		// Desactive visual editor
		add_action('personal_options_update', array(&$this, 'userPersonaloptsUpdate'));

		// Add editor in admin
		add_action('edit_form_advanced', array(&$this, 'loadWYMeditor'));
		add_action('edit_page_form', array(&$this, 'loadWYMeditor'));
		add_action('simple_edit_form', array(&$this, 'loadWYMeditor'));
	
		// Put right autosave script and put right quicktags script to allow SendToEditor button to work
		add_filter('script_loader_src', array(&$this, 'editDefaultJavaScript'));

		// Active/deactive visual editor for current user
		register_activation_hook(basename(dirname(__FILE__)). '/' . basename(__FILE__), array(&$this, 'activate'));
		register_deactivation_hook(basename(dirname(__FILE__)). '/' . basename(__FILE__), array(&$this, 'deactivate'));
		
		global $wp_version;
		if ( strpos($wp_version, '2.6') !== false ) {
			$this->wp_26 = true;		
		} elseif ( strpos($wp_version, '2.5') !== false ) {
			$this->wp_25 = true;		
		} else {
			// remove old WP preview
			add_action('admin_footer', array(&$this, 'disablePreview'));
		}

		return true;
	}

	function addAdminHead() {
		global $pagenow;
		$pages = apply_filters( 'pages_with_wymeditor_editor', array('post-new.php', 'page-new.php', 'post.php', 'page.php') );

		if ( in_array($pagenow, (array) $pages) ) {
			wp_enqueue_script( 'jquery' );
			add_action('admin_head', array(&$this, 'addFilesWYMeditor'));
		}
		return true;
	}

	function addFilesWYMeditor() {
		if ( get_locale() ) {
			$short_locale = substr( strtolower(get_locale()), 0, 2 );
			if ( is_file( $this->plugin_path . 'wymeditor/lang/' . $short_locale . '.js' ) ) {
				$this->locale = $short_locale;
				echo '<script type="text/javascript" src="'.$this->wymeditor_url.'lang/'.$this->locale.'.js"></script>';
			}
		}
		$rows = 10 * get_option('default_post_edit_rows');
		if (($rows <= 100) || ($rows > 1000)) {
			$rows = 300;
		}		
		?>
		<script type="text/javascript" src="<?php echo $this->wymeditor_url;?>jquery.wymeditor.min.js"></script>
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $this->wymeditor_url;?>../styles.css" />
		<style type="text/css">
			#postdiv a { text-decoration:none; border:none; }
			#quicktags { display: none; }
			.view-link { padding-top:5px; padding-right:10px; }
			
			.wym_skin_default .wym_html textarea { height: <?php echo $rows; ?>px; }
			.wym_skin_default .wym_iframe iframe { height: <?php echo $rows; ?>px; }
			
			<?php if( $this->wp_25 === true || $this->wp_26 === true ) : ?>
			.wym_skin_default .wym_buttons ul { height: 25px; }
			#poststuff #editorcontainer h2 { margin:0; }
			<?php endif; ?>
		</style>
		<?php
		return true;
	}

	function deactivate() {
		global $current_user;
		update_user_option($current_user->id, 'rich_editing', 'true', true);
		return true;
	}

	function activate() {
		global $current_user;
		update_user_option($current_user->id, 'rich_editing', 'false', true);
		return true;
	}

	function userPersonaloptsUpdate() {
		global $current_user;
		update_user_option($current_user->id, 'rich_editing', 'false', true);
		return true;
	}

	function disablePreview() {
		?>
		<script type="text/javascript">
			<!--
			var oPreview = document.getElementById('preview');
			if ( oPreview ) { oPreview.innerHTML = '&nbsp;'; }
			-->
		</script>
		<?php
		return true;
	}

	function loadWYMeditor() {
		global $wp_version;
		
		$selector = '#wp-content-editor-container textarea';

		?>
		<script type="text/javascript">
			<!--
			jQuery( function() {
				jQuery('<?php echo $selector; ?>').wymeditor({
					updateSelector: '#save-post',
					updateEvent: 'click',
                    skin: 'default'
					<?php if ( !empty($this->locale) ) echo ", lang: '".$this->locale."'"; ?>
				});
                // Remove the HTML editor
                jQuery("#ed_toolbar").remove();
                jQuery("#wp-content-editor-tools").remove();
			});
			-->
		</script>
		<?php
		return true;
	}
	
	function editDefaultJavaScript( $source = '' ) {
		$path = basename(dirname(__FILE__));
		
		// Auto save
		if( strpos($source, '/wp-includes/js/autosave.js') ) {
			if( $this->wp_26 === true ) {
				$source = str_replace( '/wp-includes/js/autosave.js', '/wp-content/plugins/' . $path .'/wymeditor/autosave-2.6.js', $source);
			} elseif( $this->wp_25 === true ) {
				$source = str_replace( '/wp-includes/js/autosave.js', '/wp-content/plugins/' . $path .'/wymeditor/autosave-2.5.js', $source);
			} else { // WP 2.3, 2.2, 2.1 ?
				$source = str_replace( '/wp-includes/js/autosave.js', '/wp-content/plugins/' . $path .'/wymeditor/autosave-old.js', $source);				
			}
		}

		// Quick tags
		if( strpos($source, '/wp-includes/js/quicktags.js') ) {
			$source = str_replace( '/wp-includes/js/quicktags.js', '/wp-content/plugins/' . $path .'/wymeditor/quicktags.js', $source);
		}

		return $source;
	}
}

if ( is_admin() ) {
	$simple_wymeditor = new SimpleWYMeditor();
    add_filter('user_can_richedit',
        create_function ( '$a' , 'return false;' ) , 50 );
}
?>

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$plugin_dir = basename(dirname(__FILE__)).'/languages';
load_plugin_textdomain( 'wp-youtube-lyte', false, $plugin_dir );

add_action('admin_menu', 'lyte_create_menu');

if (get_option('lyte_emptycache','0')==="1") {
	$emptycache=lyte_rm_cache();
	if ($emptycache==="OK") {
		add_action('admin_notices', 'lyte_cacheclear_ok_notice');
	} else {
		add_action('admin_notices', 'lyte_cacheclear_fail_notice');
	}
	update_option('lyte_emptycache','0');
}

function lyte_cacheclear_ok_notice() {
	echo '<div class="updated"><p>';
	_e('Your WP YouTube Lyte cache has been succesfully cleared.', 'wp-youtube-lyte' );
	echo '</p></div>';
}

function lyte_cacheclear_fail_notice() {
	echo '<div class="error"><p>';
	_e('There was a problem, the WP YouTube Lyte cache could not be cleared.', 'wp-youtube-lyte' );
	echo '</p></div>';
}

function lyte_create_menu() {
        $hook=add_options_page( 'WP YouTube Lyte settings', 'WP YouTube Lyte', 'manage_options', 'lyte_settings_page', 'lyte_settings_page');
        add_action( 'admin_init', 'register_lyte_settings' );
        add_action( 'admin_print_scripts-'.$hook, 'lyte_admin_scripts' );
        add_action( 'admin_print_styles-'.$hook, 'lyte_admin_styles' );
}

function register_lyte_settings() {
	register_setting( 'lyte-settings-group', 'lyte_show_links' );
	register_setting( 'lyte-settings-group', 'lyte_size' );
	register_setting( 'lyte-settings-group', 'lyte_hidef' );
	register_setting( 'lyte-settings-group', 'lyte_position' );
	register_setting( 'lyte-settings-group', 'lyte_notification' );
	register_setting( 'lyte-settings-group', 'lyte_microdata' );
	register_setting( 'lyte-settings-group', 'lyte_emptycache' );
	register_setting( 'lyte-settings-group', 'lyte_greedy' );
	register_setting( 'lyte-settings-group', 'lyte_yt_api_key' );
}

function lyte_admin_scripts() {
	wp_enqueue_script('jqzrssfeed', plugins_url('/external/jquery.zrssfeed.min.js', __FILE__), array('jquery'),null,true);
	wp_enqueue_script('jqcookie', plugins_url('/external/jquery.cookie.min.js', __FILE__), array('jquery'),null,true);
}

function lyte_admin_styles() {
        wp_enqueue_style('zrssfeed', plugins_url('/external/jquery.zrssfeed.css', __FILE__));
}

function lyte_admin_nag_apikey(){
    _e('<div class="update-nag">For WP YouTube Lyte to function optimally, you need to enter an YouTube API key <a href="options-general.php?page=lyte_settings_page">in the settings screen</a>.</div>');
    }

$lyte_yt_api_key=get_option('lyte_yt_api_key','');
$lyte_yt_api_key=apply_filters('lyte_filter_yt_api_key', $lyte_yt_api_key);
if (empty($lyte_yt_api_key)) {
	add_action('admin_notices', 'lyte_admin_nag_apikey');
	}

function lyte_admin_api_error(){
	$yt_error=json_decode(get_option('lyte_api_error'),1);
	echo '<div class="error"><p>';
	_e('WP YouTube Lyte got the following error back from the YouTube API: ');
	echo "<strong>".$yt_error["reason"]."</strong>";
	echo " (".date("r",$yt_error["timestamp"]).").";
	echo '</a>.</p></div>';
	update_option('lyte_api_error','');
}

if (get_option('lyte_api_error','')!=='') {
	add_action('admin_notices', 'lyte_admin_api_error');
	}

function lyte_settings_page() {
	global $pSize, $pSizeOrder;
?>
<div class="wrap">
<h2><?php _e("WP YouTube Lyte Settings","wp-youtube-lyte") ?></h2>
<div style="float:left;width:70%;">
<form method="post" action="options.php">
    <?php settings_fields( 'lyte-settings-group' ); ?>
    <table class="form-table">
	<input type="hidden" name="lyte_notification" value="<?php echo get_option('lyte_notification','0'); ?>" />
	<?php
	// only show api key input field if there's no result from filter
	$filter_key=apply_filters('lyte_filter_yt_api_key','');
	if (empty($filter_key)) { ?>
		<tr valign="top">
			<th scope="row"><?php _e("Please enter your YouTube API key.","wp-youtube-lyte") ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php _e("Please enter your YouTube API key.","wp-youtube-lyte") ?></span></legend>
					<label title="API key"><input type="text" size="40" name="lyte_yt_api_key" value="<?php echo get_option('lyte_yt_api_key',''); ?>"></label><br /><?php _e("WP YouTube Lyte uses YouTube's API to fetch information on each video. For your site to use that API, you will have to <a href=\"https://console.developers.google.com/project/\" target=\"_blank\">register your site</a>, enable the YouTube API, get a server key and fill that key out here. There is more info on this topic <a href=\"https://wordpress.org/plugins/wp-youtube-lyte/faq/\" target=\"_blank\">in the FAQ</a>.","wp-youtube-lyte"); ?>
				</fieldset>
			</td>
	        </tr>
	<?php } else { ?>
		<tr valign="top">
                	<th scope="row"><?php _e("YouTube API key provided by plugin.","wp-youtube-lyte"); ?></th>
                	<td><?php _e("Great, your YouTube API key has been taken care of by another plugin.","wp-youtube-lyte"); ?></td>
		</tr>
	<?php } ?>
        <tr valign="top">
            <th scope="row">Player size:</th>
            <td>
                <fieldset><legend class="screen-reader-text"><span><?php _e("Player size","wp-youtube-lyte") ?></span></legend>
		<?php
			if (is_bool(get_option('lyte_size'))) { $sel = (int) $pDefault; } else { $sel= (int) get_option('lyte_size'); }
			foreach (array("169","43") as $f) {
				foreach ($pSizeOrder[$f] as $i) {
					$pS=$pSize[$i];
					if ($pS['a']===true) {
						?>
						<label title="<?php echo $pS['w']."X".$pS['h']; ?>"><input type="radio" name="lyte_size" class="l_size" value="<?php echo $i."\"";if($i===$sel) echo " checked";echo " /> ".$pS['w']."X".$pS['h']." (".$pS['t'];?>)</label><br />
						<?php
					}
				}
				?><br /><?php
			}
		?>
                </fieldset>
             </td>
         </tr>
        <tr valign="top">
			<th scope="row"><?php _e("Add links below the embedded videos?","wp-youtube-lyte") ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php _e("Show links?","wp-youtube-lyte") ?></span></legend>
					<label title="Show YouTube-link"><input type="radio" name="lyte_show_links" value="1" <?php if (get_option('lyte_show_links')==="1") echo "checked" ?> /><?php _e(" Add YouTube-link.","wp-youtube-lyte") ?></label><br />
					<label title="Show YouTube and Ease YouTube link"><input type="radio" name="lyte_show_links" value="2" <?php if (get_option('lyte_show_links')==="2") echo "checked" ?> /><?php _e(" Add both a YouTube and an <a href=\"http://icant.co.uk/easy-youtube/docs/index.html\" target=\"_blank\">Easy YouTube</a>-link.","wp-youtube-lyte") ?></label><br />
					<label title="Don't include links."><input type="radio" name="lyte_show_links" value="0" <?php if ((get_option('lyte_show_links')!=="1") && (get_option('lyte_show_links')!=="2")) echo "checked" ?> /><?php _e(" Don't add any links.","wp-youtube-lyte") ?></label>
				</fieldset>
			</td>
         </tr>
         <tr valign="top">
                <th scope="row"><?php _e("Player position:","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("Left, center or right?","wp-youtube-lyte"); ?></span></legend>
                                <label title="Left"><input type="radio" name="lyte_position" value="0" <?php if (get_option('lyte_position','0')==="0") echo "checked" ?> /><?php _e("Left","wp-youtube-lyte") ?></label><br />
				<label title="Center"><input type="radio" name="lyte_position" value="1" <?php if (get_option('lyte_position','0')==="1") echo "checked" ?> /><?php _e("Center","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
         </tr>
         <tr valign="top">
                <th scope="row"><?php _e("Try to force HD (experimental)?","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("HD or not?","wp-youtube-lyte"); ?></span></legend>
                                <label title="Enable HD?"><input type="radio" name="lyte_hidef" value="1" <?php if (get_option('lyte_hidef','0')==="1") echo "checked" ?> /><?php _e("Enable HD","wp-youtube-lyte") ?></label><br />
                                <label title="Don't enable HD playback"><input type="radio" name="lyte_hidef" value="0" <?php if (get_option('lyte_hidef','0')!=="1") echo "checked" ?> /><?php _e("No HD (default)","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
	</tr>
         <tr valign="top">
                <th scope="row"><?php _e("Add microdata?","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("Add video microdata to the HTML?","wp-youtube-lyte"); ?></span></legend>
                                <label title="Sure, add microdata!"><input type="radio" name="lyte_microdata" value="1" <?php if (get_option('lyte_microdata','1')==="1") echo "checked" ?> /><?php _e("Yes (default)","wp-youtube-lyte") ?></label><br />
                                <label title="No microdata in my HTML please."><input type="radio" name="lyte_microdata" value="0" <?php if (get_option('lyte_microdata','1')!=="1") echo "checked" ?> /><?php _e("No microdata, thanks.","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
        </tr>
        <tr valign="top">
                <th scope="row"><?php _e("Also act on normal YouTube links?","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("Also act on normal YouTube links?","wp-youtube-lyte") ?></span></legend>
                                <label title="That would be great!"><input type="radio" name="lyte_greedy" value="1" <?php if (get_option('lyte_greedy','1')==="1") echo "checked" ?> /><?php _e("Yes (default)","wp-youtube-lyte") ?></label><br />
                                <label title="No, I'll stick to httpv or shortcocdes."><input type="radio" name="lyte_greedy" value="0" <?php if (get_option('lyte_greedy','1')!=="1") echo "checked" ?> /><?php _e("No thanks.","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
        </tr>
	<tr valign="top">
                <th scope="row"><?php _e("Empty WP YouTube Lyte's cache","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span>Remove WP YouTube Lyte's cache</span></legend>
                                <input type="checkbox" name="lyte_emptycache" value="1" />
                        </fieldset>
                </td>
        </tr>
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<div style="float:right;width:30%" id="lyte_admin_feed">
        <div style="margin-left:10px;margin-top:-5px;">
                <h3>
                        <?php _e("futtta about","wp-youtube-lyte") ?>
                        <select id="feed_dropdown" >
                                <option value="1"><?php _e("WP YouTube Lyte","wp-youtube-lyte") ?></option>
                                <option value="2"><?php _e("WordPress","wp-youtube-lyte") ?></option>
                                <option value="3"><?php _e("Web Technology","wp-youtube-lyte") ?></option>
                        </select>
                </h3>
                <div id="futtta_feed"></div>
		<div style="float:right;margin:50px 15px;"><a href="http://blog.futtta.be/2013/10/21/do-not-donate-to-me/" target="_blank"><img width="100px" height="85px" src="<?php echo content_url(); ?>/plugins/wp-youtube-lyte/external/do_not_donate_smallest.png" title="<?php _e("Do not donate for this plugin!"); ?>"></a></div>
        </div>
</div>

<script type="text/javascript">
	var feed = new Array;
	feed[1]="http://feeds.feedburner.com/futtta_wp-youtube-lyte";
	feed[2]="http://feeds.feedburner.com/futtta_wordpress";
	feed[3]="http://feeds.feedburner.com/futtta_webtech";
	cookiename="wp-youtube-lyte_feed";

        jQuery(document).ready(function() {
		jQuery("#feed_dropdown").change(function() { show_feed(jQuery("#feed_dropdown").val()) });

		feedid=jQuery.cookie(cookiename);
		if(typeof(feedid) !== "string") feedid=1;

		show_feed(feedid);
		})

	function show_feed(id) {
  		jQuery('#futtta_feed').rssfeed(feed[id], {
			<?php if ( is_ssl() ) echo "ssl: true,"; ?>
    			limit: 4,
			date: true,
			header: false
  		});
		jQuery("#feed_dropdown").val(id);
		jQuery.cookie(cookiename,id,{ expires: 365 });
	}
</script>

</div>
<?php } ?>

<?php
/*
Plugin Name: WP YouTube Lyte
Plugin URI: http://blog.futtta.be/wp-youtube-lyte/
Description: Lite and accessible YouTube audio and video embedding.
Author: Frank Goossens (futtta)
Version: 1.5.0
Author URI: http://blog.futtta.be/
Text Domain: wp-youtube-lyte
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

$debug=false;
$lyte_version="1.6.0";
$lyte_db_version=get_option('lyte_version','none');

/** have we updated? */
if ($lyte_db_version !== $lyte_version) {
	switch($lyte_db_version) {
		case "1.5.0":
			lyte_rm_cache();
			break;
		case "1.4.2":
		case "1.4.1":
		case "1.4.0":
			lyte_rm_cache();
			lyte_not_greedy();
			break;
	}
	update_option('lyte_version',$lyte_version);
	$lyte_db_version=$lyte_version;
}

/** are we in debug-mode */
if (!$debug) {
	$wyl_version=$lyte_version;
	$wyl_file="lyte-min.js";
} else {
	$wyl_version=rand()/1000;
	$wyl_file="lyte.js";
	lyte_rm_cache();
}

/** get paths, language and includes */
$plugin_dir = basename(dirname(__FILE__)).'/languages';
load_plugin_textdomain( 'wp-youtube-lyte', null, $plugin_dir );
require_once(dirname(__FILE__).'/player_sizes.inc.php');
require_once(dirname(__FILE__).'/widget.php');

/** get default embed size and build array to change size later if requested */
$oSize = (int) get_option('lyte_size');
if ((is_bool($oSize)) || ($pSize[$oSize]['a']===false)) { $sel = (int) $pDefault; } else { $sel=$oSize; }

$pSizeFormat=$pSize[$sel]['f'];
$j=0;
foreach ($pSizeOrder[$pSizeFormat] as $sizeId) {
	$sArray[$j]['w']=(int) $pSize[$sizeId]['w'];
	$sArray[$j]['h']=(int) $pSize[$sizeId]['h'];
	if ($sizeId===$sel) $selSize=$j;
	$j++;
}

/** get other options and push in array*/
$lyteSettings['sizeArray']=$sArray;
$lyteSettings['selSize']=$selSize;
$lyteSettings['links']=get_option('lyte_show_links');
$lyteSettings['file']=$wyl_file."?wyl_version=".$wyl_version;
$lyteSettings['ratioClass']= ( $pSizeFormat==="43" ) ? " fourthree" : "";
$lyteSettings['pos']= ( get_option('lyte_position','0')==="1" ) ? "margin:5px auto;" : "margin:5px;";
$lyteSettings['microdata']=get_option('lyte_microdata','1');
$lyteSettings['hidef']=get_option('lyte_hidef',0);
$lyteSettings['scheme'] = ( is_ssl() ) ? "https" : "http";

/** API: filter hook to alter $lyteSettings */
function lyte_settings_enforcer() {
	global $lyteSettings;
	$lyteSettings = apply_filters( 'lyte_settings', $lyteSettings );
	}
add_action('after_setup_theme','lyte_settings_enforcer');

function lyte_parse($the_content,$doExcerpt=false) {
	/** main function to parse the content, searching and replacing httpv-links */
	global $lyteSettings, $toCache_index, $postID, $cachekey;
	$lyteSettings['path']=plugins_url() . "/" . dirname(plugin_basename(__FILE__)) . '/lyte/';
	$urlArr=parse_url($lyteSettings['path']);
	$origin=$urlArr['scheme']."://".$urlArr['host']."/";

	/** API: filter hook to preparse the_content, e.g. to force normal youtube links to be parsed */
	$the_content = apply_filters( 'lyte_content_preparse',$the_content );

	if (get_option('lyte_greedy','1')==="1"){
		$the_content=preg_replace('/^https?:\/\/(www.)?youtu(be.com|.be)\/(watch\?v=)?/m','httpv://www.youtube.com/watch?v=',$the_content);
	}

	if((strpos($the_content, "httpv")!==FALSE)||(strpos($the_content, "httpa")!==FALSE)) {
		$char_codes = array('&#215;','&#8211;');
		$replacements = array("x", "--");
		$the_content=str_replace($char_codes, $replacements, $the_content);
		$lyte_feed=is_feed();
		
		$hidefClass = ($lyteSettings['hidef']==="1") ? " hidef" : "";

		$postID = get_the_ID();
		$toCache_index=array();

		$lytes_regexp="/(?:<p>)?http(v|a):\/\/([a-zA-Z0-9\-\_]+\.|)(youtube|youtu)(\.com|\.be)\/(((watch(\?v\=|\/v\/)|.+?v\=|)([a-zA-Z0-9\-\_]{11}))|(playlist\?list\=([a-zA-Z0-9\-\_]*)))([^\s<]*)(<?:\/p>)?/";

		preg_match_all($lytes_regexp, $the_content, $matches, PREG_SET_ORDER); 

		foreach($matches as $match) {
			/** API: filter hook to preparse fragment in a httpv-url, e.g. to force hqThumb=1 or showinfo=0 */
			$match[12] = apply_filters( 'lyte_match_preparse_fragment',$match[12] );

			preg_match("/stepSize\=([\+\-0-9]{2})/",$match[12],$sMatch);
			preg_match("/showinfo\=([0-1]{1})/",$match[12],$showinfo);
			preg_match("/start\=([0-9]*)/",$match[12],$start);
			preg_match("/enablejsapi\=([0-1]{1})/",$match[12],$jsapi);
			preg_match("/hqThumb\=([0-1]{1})/",$match[12],$hqThumb);
			preg_match("/noMicroData\=([0-1]{1})/",$match[12],$microData);

			$thumb="normal";
			if (!empty($hqThumb)) {
				if ($hqThumb[0]==="hqThumb=1") {
						$thumb="highres";
				}
			}

			$noMicroData="0";
			if (!empty($microData)) {
				if ($microData[0]==="noMicroData=1") {
					$noMicroData="1";
				}
			}
  
			$qsa="";
			if (!empty($showinfo[0])) {
				$qsa="&amp;".$showinfo[0];
				$titleClass=" hidden";
			} else {
				$titleClass="";
			}
			if (!empty($start[0])) $qsa.="&amp;".$start[0];
			if (!empty($jsapi[0])) $qsa.="&amp;".$jsapi[0]."&amp;origin=".$origin;

			if (!empty($qsa)) {
				$esc_arr=array("&" => "\&", "?" => "\?", "=" => "\=");
				$qsaClass=" qsa_".strtr($qsa,$esc_arr);
			} else {
				$qsaClass="";
			}

			if (!empty($sMatch)) {
				$newSize=(int) $sMatch[1];
				$newPos=(int) $lyteSettings['selSize']+$newSize;
				if ($newPos<0) {
					$newPos=0;
				} else if ($newPos > count($lyteSettings['sizeArray'])-1) {
					$newPos=count($lyteSettings['sizeArray'])-1;
				}
				$lyteSettings[2]=$lyteSettings['sizeArray'][$newPos]['w'];
				$lyteSettings[3]=$lyteSettings['sizeArray'][$newPos]['h'];
			} else {
				$lyteSettings[2]=$lyteSettings['sizeArray'][$lyteSettings['selSize']]['w'];
				$lyteSettings[3]=$lyteSettings['sizeArray'][$lyteSettings['selSize']]['h'];
			}

			if ($match[1]!=="a") {
 				$divHeight=$lyteSettings[3];
				$audioClass="";
				$audio=false;
			} else {
				$audio=true;
				$audioClass=" lyte-audio";
				$divHeight=38;
			}

			$NSimgHeight=$divHeight-20;

			if ($match[11]!="") {
				$plClass=" playlist";
                $vid=$match[11];
				switch ($lyteSettings['links']) {
					case "0":
						$noscript_post="<br />".__("Watch this playlist on YouTube","wp-youtube-lyte");
						$noscript="<noscript><a href=\"".$lyteSettings['scheme']."://youtube.com/playlist?list=".$vid."\">".$noscript_post."</a></noscript>";
						$lytelinks_txt="";
						break;
					default:
						$noscript="";
						$lytelinks_txt="<div class=\"lL\" style=\"width:".$lyteSettings[2]."px;".$lyteSettings['pos']."\">".__("Watch this playlist","wp-youtube-lyte")." <a href=\"".$lyteSettings['scheme']."://www.youtube.com/playlist?list=".$vid."\">".__("on YouTube","wp-youtube-lyte")."</a></div>";
				}
			} else if ($match[9]!="") {
				$plClass="";
				$vid=$match[9];
				switch ($lyteSettings['links']) {
					case "0":
						$noscript_post="<br />".__("Watch this video on YouTube","wp-youtube-lyte");
						$lytelinks_txt="<div class=\"lL\" style=\"width:".$lyteSettings[2]."px;".$lyteSettings['pos']."\"></div>";
						break;
					case "2":
						$noscript_post="";
						$lytelinks_txt="<div class=\"lL\" style=\"width:".$lyteSettings[2]."px;".$lyteSettings['pos']."\">".__("Watch this video","wp-youtube-lyte")." <a href=\"".$lyteSettings['scheme']."://youtu.be/".$vid."\">".__("on YouTube","wp-youtube-lyte")."</a> ".__("or on","wp-youtube-lyte")." <a href=\"http://icant.co.uk/easy-youtube/?http://www.youtube.com/watch?v=".$vid."\">Easy Youtube</a>.</div>";
						break;
					default:
						$noscript_post="";
						$lytelinks_txt="<div class=\"lL\" style=\"width:".$lyteSettings[2]."px;".$lyteSettings['pos']."\">".__("Watch this video","wp-youtube-lyte")." <a href=\"".$lyteSettings['scheme']."://youtu.be/".$vid."\">".__("on YouTube","wp-youtube-lyte")."</a>.</div>";
					}

				$noscript="<noscript><a href=\"".$lyteSettings['scheme']."://youtu.be/".$vid."\"><img src=\"".$lyteSettings['scheme']."://i.ytimg.com/vi/".$vid."/0.jpg\" alt=\"\" width=\"".$lyteSettings[2]."\" height=\"".$NSimgHeight."\" />".$noscript_post."</a></noscript>";
			}

			// fetch data from YT api (v2 or v3)
			$isPlaylist=false;
			if ($plClass===" playlist") {
				$isPlaylist=true;
			}
			$cachekey = '_lyte_' . $vid;
			$yt_resp_array=lyte_get_YT_resp($vid,$isPlaylist,$cachekey);

            // If there was a result from youtube or from cache, use it
            if ( $yt_resp_array ) {				
				if (is_array($yt_resp_array)) {
					if ($isPlaylist!==true) {
						// captions, thanks to Benetech
						$captionsMeta="";
						$doCaptions=true;
	
						/** API: filter hook to disable captions */
						$doCaptions = apply_filters( 'lyte_docaptions', $doCaptions );
	
						if(($lyteSettings['microdata'] === "1")&&($noMicroData !== "1" )&&($doCaptions === true)) {
							if (array_key_exists('captions_data',$yt_resp_array)) {
								if ($yt_resp_array["captions_data"]=="true") {
									$captionsMeta="<meta itemprop=\"accessibilityFeature\" content=\"captions\" />";
									$forceCaptionsUpdate=false;
								} else {
									$forceCaptionsUpdate=true;
								}
							} else {
								$forceCaptionsUpdate=true;
								$yt_resp_array["captions_data"]=false;
							}
	
							if ($forceCaptionsUpdate===true) {
								$captionsMeta="";
								$threshold = 30;
								if (array_key_exists('captions_timestamp',$yt_resp_array)) {
									$cache_timestamp = $yt_resp_array["captions_timestamp"];
									$interval = (strtotime("now") - $cache_timestamp)/60/60/24;
								} else {
									$cache_timestamp = false;
									$interval = $threshold+1;
								}
							
								if(!is_int($cache_timestamp) || ($interval > $threshold && !is_null( $yt_resp_array["captions_data"]))) {
									$yt_resp_array['captions_timestamp'] = strtotime("now");
							    	wp_schedule_single_event(strtotime("now") + 60*60, 'schedule_captions_lookup', array($postID, $cachekey, $vid));
									$yt_resp_precache=json_encode($yt_resp_array);
									$toCache=base64_encode(gzcompress($yt_resp_precache));
									update_post_meta($postID, $cachekey, $toCache); 
								}
						  	}
						}
					}
					$thumbUrl="";
					if (($thumb==="highres") && (!empty($yt_resp_array["HQthumbUrl"]))){
						$thumbUrl=$yt_resp_array["HQthumbUrl"];
					} else {
						if (!empty($yt_resp_array["thumbUrl"])) {
							$thumbUrl=$yt_resp_array["thumbUrl"];
						} else {
							$thumbUrl="//i.ytimg.com/vi/".$vid."/hqdefault.jpg";
						} 
					}
				/** API: filter hook to override thumbnail URL */
				$thumbUrl = apply_filters( 'lyte_match_thumburl', $thumbUrl );
		      } else {
				// no useable result from youtube, fallback on video thumbnail (doesn't work on playlist)
				$thumbUrl = "//i.ytimg.com/vi/".$vid."/hqdefault.jpg";
			}
		}
		
			if ($audio===true) {
				$wrapper="<div class=\"lyte-wrapper-audio\" style=\"width:".$lyteSettings[2]."px;max-width:100%;overflow:hidden;height:38px;".$lyteSettings['pos']."\">";
			} else {
				$wrapper="<div class=\"lyte-wrapper".$lyteSettings['ratioClass']."\" style=\"width:".$lyteSettings[2]."px;max-width: 100%;".$lyteSettings['pos']."\">";
			}

			if ($doExcerpt) {
				$lytetemplate="";
				$templateType="excerpt";
			} elseif ($lyte_feed) {
				$postURL = get_permalink( $postID ); 
				$textLink = ($lyteSettings['links']===0)? "" : "<br />".strip_tags($lytelinks_txt, '<a>')."<br />";
				$lytetemplate = "<a href=\"".$postURL."\"><img src=\"".$thumbUrl."\" alt=\"YouTube Video\"></a>".$textLink;
				$templateType="feed";
			} elseif (($audio !== true) && ( $plClass !== " playlist") && (($lyteSettings['microdata'] === "1")&&($noMicroData !== "1" ))) {
				$lytetemplate = $wrapper."<div class=\"lyMe".$audioClass.$hidefClass.$plClass.$qsaClass."\" id=\"WYL_".$vid."\" itemprop=\"video\" itemscope itemtype=\"http://schema.org/VideoObject\"><meta itemprop=\"thumbnailUrl\" content=\"".$thumbUrl."\" /><meta itemprop=\"embedURL\" content=\"http://www.youtube.com/embed/".$vid."\" /><meta itemprop=\"uploadDate\" content=\"".$yt_resp_array["dateField"]."\" />".$captionsMeta."<div id=\"lyte_".$vid."\" data-src=\"".$thumbUrl."\" class=\"pL\"><div class=\"tC".$titleClass."\"><div class=\"tT\" itemprop=\"name\">".$yt_resp_array["title"]."</div></div><div class=\"play\"></div><div class=\"ctrl\"><div class=\"Lctrl\"></div><div class=\"Rctrl\"></div></div></div>".$noscript."<meta itemprop=\"description\" content=\"".$yt_resp_array["description"]."\"></div></div>".$lytelinks_txt;
				$templateType="postMicrodata";
			} else {
				$lytetemplate = $wrapper."<div class=\"lyMe".$audioClass.$hidefClass.$plClass.$qsaClass."\" id=\"WYL_".$vid."\"><div id=\"lyte_".$vid."\" data-src=\"".$thumbUrl."\" class=\"pL\"><div class=\"tC".$titleClass."\"><div class=\"tT\">".$yt_resp_array["title"]."</div></div><div class=\"play\"></div><div class=\"ctrl\"><div class=\"Lctrl\"></div><div class=\"Rctrl\"></div></div></div>".$noscript."</div></div>".$lytelinks_txt;
				$templateType="post";
			}

			/** API: filter hook to parse template before being applied */
			$lytetemplate = apply_filters( 'lyte_match_postparse_template',$lytetemplate,$templateType );
			$the_content = preg_replace($lytes_regexp, $lytetemplate, $the_content, 1);
        }

		// update lyte_cache_index
		if ((is_array($toCache_index))&&(!empty($toCache_index))) {
			$lyte_cache=json_decode(get_option('lyte_cache_index'),true);
            		$lyte_cache[$postID]=$toCache_index;
            		update_option('lyte_cache_index',json_encode($lyte_cache));
		}

		if (!$lyte_feed) {
			lyte_initer();
		}
	}

	/** API: filter hook to postparse the_content before returning */
	$the_content = apply_filters( 'lyte_content_postparse',$the_content );

	return $the_content;
}

function captions_lookup($postID, $cachekey, $vid) {
	// captions lookup at YouTube via a11ymetadata.org
	$response = wp_remote_request("http://api.a11ymetadata.org/captions/youtubeid=".$vid."/youtube");
	
	if(!is_wp_error($response)) {	
		$rawJson = wp_remote_retrieve_body($response);
		$decodeJson = json_decode($rawJson, true);

		$yt_resp = get_post_meta($postID, $cachekey, true);

		if (!empty($yt_resp)) {
			$yt_resp = gzuncompress(base64_decode($yt_resp));
			if($yt_resp) {
				$yt_resp_array=json_decode($yt_resp,true);

				if ($decodeJson['status'] == 'success' && $decodeJson['data']['captions'] == '1') {	
					$yt_resp_array['captions_data'] = true;
				} else {	
					$yt_resp_array['captions_data'] = false;
				}

				$yt_resp_array['captions_timestamp'] = strtotime("now");						
				$yt_resp_precache=json_encode($yt_resp_array);
				$toCache=base64_encode(gzcompress($yt_resp_precache));
				update_post_meta($postID, $cachekey, $toCache);	
			}
		}
	}
}

function lyte_get_YT_resp($vid,$playlist=false,$cachekey,$apiTestKey="") {
	/** logic to get video info from cache or get it from YouTube and set it */
	global $postID, $cachekey, $toCache_index;
	if ( $postID && empty($apiTestKey)) {
        	$cache_resp = get_post_meta( $postID, $cachekey, true );
		if (!empty($cache_resp)) {
			$_thisLyte = json_decode(gzuncompress(base64_decode($cache_resp)),1);
			// make sure there are not old APIv2 full responses in cache
			if (array_key_exists('entry', $_thisLyte)) {
				if ($_thisLyte['entry']['xmlns$yt']==="http://gdata.youtube.com/schemas/2007") {
					$_thisLyte = "";
				}
			}
		}
	} else {
		$_thisLyte = "";
	}

	if ( empty( $_thisLyte ) ) {
		// get info from youtube
        	// first get yt api key
	        $lyte_yt_api_key = get_option('lyte_yt_api_key','');
		$lyte_yt_api_key = apply_filters('lyte_filter_yt_api_key', $lyte_yt_api_key);
		if (!empty($apiTestKey)) {
			$lyte_yt_api_key=$apiTestKey;
		}

		if (empty($lyte_yt_api_key)) {
			// v2 (if no API key)
			$yt_api_base = "http://gdata.youtube.com/feeds/api/";
						
			if ($playlist) {
				$yt_api_target = "playlists/".$vid."?v=2&alt=json&fields=id,title,author,updated,media:group(media:thumbnail)";
			} else {
				$yt_api_target = "videos/".$vid."?v=2&alt=json&fields=id,title,published,content,media:group(media:description,yt:duration,yt:aspectRatio),author(name)";
			}
		} else {
			// v3
			$yt_api_base = "https://www.googleapis.com/youtube/v3/";
			
			if ($playlist) {
				$yt_api_target = "playlists?part=snippet%2C+id&id=".$vid."&key=".$lyte_yt_api_key;
			} else {
				$yt_api_target = "videos?part=id%2C+snippet%2C+contentDetails&id=".$vid."&key=".$lyte_yt_api_key;
			}
		}

		$yt_api_url = $yt_api_base.$yt_api_target;
		$yt_resp = wp_remote_get($yt_api_url);

		// check if we got through
		if (is_wp_error($yt_resp)) {
			$_thisLyte = "";
		} else {
			$yt_resp_array=json_decode(wp_remote_retrieve_body($yt_resp),true);
													
			if(is_array($yt_resp_array)) {
				// extract relevant data
				if (empty($lyte_yt_api_key)) {
					// v2
					if ($playlist) {
						$_thisLyte['title']="Playlist: ".esc_attr(sanitize_text_field(@$yt_resp_array['feed']['title']['$t']));
						$_thisLyte['thumbUrl']=esc_url(@$yt_resp_array['feed']['media$group']['media$thumbnail'][2]['url']);
						$_thisLyte['HQthumbUrl']="";
						$_thisLyte['dateField']=sanitize_text_field(@$yt_resp_array['feed']['updated']['$t']);
						$_thisLyte['duration']="";
						$_thisLyte['description']=$yt_title;
						$_thisLyte['captions_data']="false";
						$_thisLyte['captions_timestamp'] = "";
					} else {
						$_thisLyte['title']=esc_attr(sanitize_text_field(@$yt_resp_array['entry']['title']['$t']));
						$_thisLyte['thumbUrl']="//i.ytimg.com/vi/".$vid."/hqdefault.jpg";
						$_thisLyte['HQthumbUrl']="//i.ytimg.com/vi/".$vid."/maxresdefault.jpg";
						$_thisLyte['dateField']=sanitize_text_field(@$yt_resp_array['entry']['published']['$t']);
						$_thisLyte['duration']="T".sanitize_text_field(@$yt_resp_array['entry']['media$group']['yt$duration']['seconds'])."S";
						$_thisLyte['description']=esc_attr(sanitize_text_field(@$yt_resp_array['entry']['media$group']['media$description']['$t']));
						$_thisLyte['captions_data']="false";
						$_thisLyte['captions_timestamp'] = "";
					}
				} else {
					// v3
					if (in_array(wp_remote_retrieve_response_code($yt_resp),array(400,403,404))) {
						$yt_error['code']=wp_remote_retrieve_response_code($yt_resp);
						$yt_error['reason']=$yt_resp_array['error']['errors'][0]['reason'];
						$yt_error['timestamp']=strtotime("now");
						if (empty($apiTestKey)) {
							update_option("lyte_api_error",json_encode($yt_error));
						} else {
							return $yt_error;
						}
						$_thisLyte = "";
					} else {
						if ($playlist) {
							$_thisLyte['title']="Playlist: ".esc_attr(sanitize_text_field(@$yt_resp_array['items'][0]['snippet']['title']));
							$_thisLyte['thumbUrl']=esc_url(@$yt_resp_array['items'][0]['snippet']['thumbnails']['high']['url']);
							$_thisLyte['HQthumbUrl']=esc_url(@$yt_resp_array['items'][0]['snippet']['thumbnails']['maxres']['url']);
							$_thisLyte['dateField']=sanitize_text_field(@$yt_resp_array['items'][0]['snippet']['publishedAt']);
							$_thisLyte['duration']="";
							$_thisLyte['description']=esc_attr(sanitize_text_field(@$yt_resp_array['items'][0]['snippet']['description']));
							$_thisLyte['captions_data']="false";
							$_thisLyte['captions_timestamp'] = "";
						} else {
							$_thisLyte['title']=esc_attr(sanitize_text_field(@$yt_resp_array['items'][0]['snippet']['title']));
							$_thisLyte['thumbUrl']=esc_url(@$yt_resp_array['items'][0]['snippet']['thumbnails']['high']['url']);
							$_thisLyte['HQthumbUrl']=esc_url(@$yt_resp_array['items'][0]['snippet']['thumbnails']['maxres']['url']);
							$_thisLyte['dateField']=sanitize_text_field(@$yt_resp_array['items'][0]['snippet']['publishedAt']);
							$_thisLyte['duration']=sanitize_text_field(@$yt_resp_array['items'][0]['contentDetails']['duration']);
							$_thisLyte['description']=esc_attr(sanitize_text_field(@$yt_resp_array['items'][0]['snippet']['description']));
							$_thisLyte['captions_data']=sanitize_text_field(@$yt_resp_array['items'][0]['contentDetails']['caption']);
							$_thisLyte['captions_timestamp'] = strtotime("now");
						}
					}
				}
					
				// try to cache the result
				if ( ($postID) && (!empty($_thisLyte)) && (empty($apiTestKey)) ) {
					$_thisLyte['lyte_date_added']=time();
					$yt_resp_precache=json_encode($_thisLyte);

					// then gzip + base64 (to limit amount of data + solve problems with wordpress removing slashes)
					$yt_resp_precache=base64_encode(gzcompress($yt_resp_precache));

					// and do the actual caching
					$toCache = ( $yt_resp_precache ) ? $yt_resp_precache : '{{unknown}}';
					update_post_meta( $postID, $cachekey, $toCache );

					// and finally add new cache-entry to toCache_index which will be added to lyte_cache_index pref
					$toCache_index[]=$cachekey;
				}
			}
		}
	}
	return $_thisLyte;
}

/* only add js/css once and only if needed */
function lyte_initer() {
	global $lynited;
	if (!$lynited) {
		$lynited=true;
		add_action('wp_footer', 'lyte_init');
	}
}

/* actual initialization */
function lyte_init() {
	global $lyteSettings;
	$lyte_css = ".lyte-wrapper-audio div, .lyte-wrapper div {margin:0px !important; overflow:hidden;} .lyte,.lyMe{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;background-color:#777;} .fourthree .lyMe, .fourthree .lyte {padding-bottom:75%;} .lidget{margin-bottom:5px;} .lidget .lyte, .widget .lyMe {padding-bottom:0!important;height:100%!important;} .lyte-wrapper-audio .lyte{height:38px!important;overflow:hidden;padding:0!important} .lyMe iframe, .lyte iframe,.lyte .pL{position:absolute;top:0;left:0;width:100%;height:100%!important;background:no-repeat scroll center #000;background-size:cover;cursor:pointer} .tC{background-color:rgba(0,0,0,0.5);left:0;position:absolute;top:0;width:100%} .tT{color:#FFF;font-family:sans-serif;font-size:12px;height:auto;text-align:left;padding:5px 10px} .tT:hover{text-decoration:underline} .play{background:no-repeat scroll 0 0 transparent;width:90px;height:62px;position:absolute;left:43%;left:calc(50% - 45px);left:-webkit-calc(50% - 45px);top:38%;top:calc(50% - 31px);top:-webkit-calc(50% - 31px);opacity:0.9;} .widget .play {top:30%;top:calc(45% - 31px);top:-webkit-calc(45% - 31px);transform:scale(0.6);-webkit-transform:scale(0.6);-ms-transform:scale(0.6);} .lyte:hover .play{background-position:0 -65px; opacity:1;} .lyte-audio .pL{max-height:38px!important} .lyte-audio iframe{height:438px!important} .ctrl{background:repeat scroll 0 -215px transparent;width:100%;height:40px;bottom:0;left:0;position:absolute} .Lctrl{background:no-repeat scroll 0 -132px transparent;width:158px;height:40px;bottom:0;left:0;position:absolute} .Rctrl{background:no-repeat scroll -42px -174px transparent;width:117px;height:40px;bottom:0;right:0;position:absolute} .lyte-audio .play{display:none} .hidden{display:none}";
	
	/** API: filter hook to change css */
	$lyte_css = apply_filters( 'lyte_css', $lyte_css);
				
	if (!empty($lyte_css)) {
		echo "<script type=\"text/javascript\">var bU='".$lyteSettings['path']."';style = document.createElement('style');style.type = 'text/css';rules = document.createTextNode(\"".$lyte_css."\" );if(style.styleSheet) { style.styleSheet.cssText = rules.nodeValue;} else {style.appendChild(rules);}document.getElementsByTagName('head')[0].appendChild(style);</script>";
	}
	echo "<script type=\"text/javascript\" async src=\"".$lyteSettings['path'].$lyteSettings['file']."\"></script>";
}

/** override default wp_trim_excerpt to have lyte_parse remove the httpv-links */
function lyte_trim_excerpt($text) {
	global $post;
	$raw_excerpt = $text;
	if ( '' == $text ) {
		$text = get_the_content('');
		$text = lyte_parse($text, true);
                $text = strip_shortcodes( $text );
                $text = apply_filters('the_content', $text);
                $text = str_replace(']]>', ']]&gt;', $text);
                $excerpt_length = apply_filters('excerpt_length', 55);
                $excerpt_more = apply_filters('excerpt_more', ' ' . '[...]');
		if (function_exists('wp_trim_words')) {
               		$text = wp_trim_words( $text, $excerpt_length, $excerpt_more );
		} else {
			$length = $excerpt_length*6;
			$text = substr( strip_tags(trim(preg_replace('/\s+/', ' ', $text))), 0, $length );
			$text .= $excerpt_more;
		}
        }
        return apply_filters('wp_trim_excerpt', $text, $raw_excerpt);
}

/** Lyte shortcode */
function shortcode_lyte($atts) {
        extract(shortcode_atts(array(
                "id"    => '',
                "audio" => '',
		"playlist" => '',
        ), $atts));
        
	if ($audio) {$proto="httpa";} else {$proto="httpv";}
	if ($playlist) {$action="playlist?list=";} else {$action="watch?v=";}

        return lyte_parse($proto.'://www.youtube.com/'.$action.$id);
    }

/** update functions */
/** upgrade, so lyte should not be greedy */
function lyte_not_greedy() {
	update_option( "lyte_greedy", "0" );
}

/** function to flush YT responses from cache */
function lyte_rm_cache() {
	try {
		$lyte_posts=json_decode(get_option('lyte_cache_index'),true);
		if (is_array($lyte_posts)){
			foreach ($lyte_posts as $postID => $lyte_post) {
				foreach ($lyte_post as $cachekey) {
					delete_post_meta($postID, $cachekey);
				}
			}
			delete_option('lyte_cache_index');
		}
		return "OK";
	} catch(Exception $e) {
		return $e->getMessage();
	}
}

/** function to call from within themes */
/* use with e.g. : <?php if(function_exists('lyte_preparse')) { echo lyte_preparse($videoId); } ?> */
function lyte_preparse($videoId) {
    return lyte_parse('httpv://www.youtube.com/watch?v='.$videoId);
}

function lyte_add_action_link($links) {
	$links[]='<a href="' . admin_url( 'options-general.php?page=lyte_settings_page' ) . '">' . _e('Settings') . '</a>';
	return $links;
}

/** hooking it all up to wordpress */
if ( is_admin() ) {
	require_once(dirname(__FILE__).'/options.php');
	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'lyte_add_action_link' );
} else {
	add_filter('the_content', 'lyte_parse', 4);
	add_shortcode("lyte", "shortcode_lyte");
	remove_filter('get_the_excerpt', 'wp_trim_excerpt');
	add_filter('get_the_excerpt', 'lyte_trim_excerpt');
	add_action('schedule_captions_lookup', 'captions_lookup', 1, 3);

	/** API: action hook to allow extra actions or filters to be added */
	do_action("lyte_actionsfilters");
}
?>

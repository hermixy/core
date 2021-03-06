<?php
/* $Id$ */
/*
    functions.inc
    Copyright (C) 2004-2006 Scott Ullrich
    All rights reserved.

    originally part of m0n0wall (http://m0n0.ch/wall)
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.

	pfSense_MODULE:	utils

*/

/* BEGIN compatibility goo with HEAD */
if(!function_exists("gettext")) {
	function gettext($text) {
		return $text;
	}
}

if(!function_exists("pfSenseHeader")) {
	/****f* pfsense-utils/pfSenseHeader
	 * NAME
	 *   pfSenseHeader
	 * INPUTS
	 *   none
	 * RESULT
	 *   Javascript header change or browser Location:
	 ******/
	function pfSenseHeader($text) {
	        global $_SERVER;
	        if (isAjax()) {
	                if ($_SERVER['HTTPS'] == "on")
	                        $protocol = "https";
	                else
	                        $protocol = "http";

	                $port = ":{$_SERVER['SERVER_PORT']}";
	                if ($_SERVER['SERVER_PORT'] == "80" && $protocol == "http")
	                        $port = "";
	                if ($_SERVER['SERVER_PORT'] == "443" && $protocol == "https")
	                        $port = "";
	                $complete_url = "{$protocol}://{$_SERVER['SERVER_NAME']}{$port}/{$text}";
	                echo "\ndocument.location.href = '{$complete_url}';\n";
	        } else {
	                header("Location: $text");
	        }
	}
}
/* END compatibility goo with HEAD */

/*fetch menu notices function*/
if(!function_exists("get_menu_messages")) {
	function get_menu_messages(){
		global $g,$config;
		if (are_notices_pending()) {
			$notices = get_notices();
			$requests=array();

			## Get Query Arguments from URL ###
			foreach ($_REQUEST as $key => $value) {
				if ($key != "PHPSESSID")
					$requests[] = $key.'='.$value;
				}
			if(is_array($requests))
				$request_string = implode("&", $requests);

				if(is_array($notices)) {
					$notice_msgs = "<ul class=\"dropdown-menu\" role=\"menu\">";

					$notice_msgs .= "<li><a href=\"#\" onclick=\"notice_action('acknowledge','all');\" >".gettext("Acknowledge All Notices")."</a></li><li class=\"divider\"></li>";

					foreach ($notices as $key => $value) {
						$date = date("m-d-y H:i:s", $key);
						$noticemsg = ($value['notice'] != "" ? $value['notice'] : $value['id']);
						$noticemsg = preg_replace("/(\"|\'|\n|<.?\w+>)/i","",$noticemsg);
						if ((strlen($noticemsg)* 8) > $domtt_width)
							$domtt_width=(strlen($noticemsg) *8);
						if ((strlen($noticemsg)* 8) > 900)
							$domtt_width= 900;
						$alert_action ="onclick=\"notice_action('acknowledge','{$key}'); jQuery(this).parent().parent().remove();\"";
						$notice_msgs .= "<li><a href=\"#\" {$alert_link} {$alert_action}>{$date} [ ".htmlspecialchars($noticemsg)."]</a></li>";
						}
					$notice_msgs .="</ul>";


					$menu_messages = '';

                    if(count($notices)==1)
			$msg= sprintf("%1$02d",count($notices))." ".gettext("unread notice");
                    else
			$msg= sprintf("%1$02d",count($notices))." ".gettext("unread notices");

                    $menu_messages.="<a href=\"/\" class=\"dropdown-toggle \" data-toggle=\"dropdown\" role=\"button\" aria-expanded=\"false\"><span class=\"text-primary\">{$msg}&nbsp;</span><span class=\"caret text-primary\"></span></a>{$notice_msgs}\n";

                    }
			}
		else {
			$menu_messages='';
			$menu_messages.= "<a href=\"#\">".$config['system']['hostname'] . "." . $config['system']['domain']."</a>";
			$menu_messages.='';
			}
		return ($menu_messages);
		}
}

if(!function_exists("dom_title")) {
	function dom_title($title_msg,$width=NULL){
		$width=preg_replace("/\D+/","",$width);
		if (!empty($width)){
			$width=",'width',$width";
			}
		if (!empty($title_msg)){
			$title_msg=preg_replace("/\s+/"," ",$title_msg);
		    $title_msg=preg_replace("/'/","\'",$title_msg);
			return "onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\" onmouseover=\"domTT_activate(this, event, 'content', '{$title_msg}', 'trail', true, 'delay', 250, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle' $width);\"";
			}
		}
	}
/* include all configuration functions */
require_once("interfaces.inc");
require_once("gwlb.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");
require_once("certs.inc");
require_once("system.inc");
require_once("vslb.inc");

?>

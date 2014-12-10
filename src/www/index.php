<?php
/* $Id$ */
/*
	index.php
	Copyright (C) 2004-2012 Scott Ullrich
	All rights reserved.

	Originally part of m0n0wall (http://m0n0.ch/wall)
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
	oR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-system-login/logout
##|*NAME=System: Login / Logout page / Dashboard
##|*DESCR=Allow access to the 'System: Login / Logout' page and Dashboard.
##|*MATCH=index.php*
##|-PRIV

// Turn on buffering to speed up rendering
ini_set('output_buffering','true');

// Start buffering with a cache size of 100000
ob_start(null, "1000");


## Load Essential Includes
require_once('functions.inc');
require_once('guiconfig.inc');
require_once('notices.inc');
require_once("pkg-utils.inc");

if(isset($_REQUEST['closenotice'])){
	close_notice($_REQUEST['closenotice']);
	echo get_menu_messages();
	exit;
}
if ($_REQUEST['act'] == 'alias_info_popup' && !preg_match("/\D/",$_REQUEST['aliasid'])){
	alias_info_popup($_REQUEST['aliasid']);
	exit;
}

if($g['disablecrashreporter'] != true) {
	// Check to see if we have a crash report
	$x = 0;
	if(file_exists("/tmp/PHP_errors.log")) {
		$total = `/usr/bin/grep -vi warning /tmp/PHP_errors.log | /usr/bin/wc -l | /usr/bin/awk '{ print $1 }'`;
		if($total > 0)
			$x++;
	}
	$crash = glob("/var/crash/*");
	$skip_files = array(".", "..", "minfree", "");
	if(is_array($crash)) {
		foreach($crash as $c) {
			if (!in_array(basename($c), $skip_files))
				$x++;
		}
		if($x > 0)
			$savemsg = "{$g['product_name']} has detected a crash report or programming bug.  Click <a href='crash_reporter.php'>here</a> for more information.";
	}
}

##build list of widgets
$directory = "/usr/local/www/widgets/widgets/";
$dirhandle  = opendir($directory);
$filename = "";
$widgetnames = array();
$widgetfiles = array();
$widgetlist = array();

while (false !== ($filename = readdir($dirhandle))) {
	$periodpos = strpos($filename, ".");
	/* Ignore files not ending in .php */
	if (substr($filename, -4, 4) != ".php")
		continue;
	$widgetname = substr($filename, 0, $periodpos);
	$widgetnames[] = $widgetname;
	if ($widgetname != "system_information")
		$widgetfiles[] = $filename;
}

##sort widgets alphabetically
sort($widgetfiles);

##insert the system information widget as first, so as to be displayed first
array_unshift($widgetfiles, "system_information.widget.php");

##if no config entry found, initialize config entry
if (!is_array($config['widgets'])) {
	$config['widgets'] = array();
}
		
if ($_POST && $_POST['sequence']) {
	
	
	$config['widgets']['sequence'] = $_POST['sequence'];

	foreach ($widgetnames as $widget){
		if ($_POST[$widget . '-config']){
			$config['widgets'][$widget . '-config'] = $_POST[$widget . '-config'];
		}
	}

	write_config(gettext("Widget configuration has been changed."));
	header("Location: index.php");
	exit;
}

## Load Functions Files
require_once('includes/functions.inc.php');

## Check to see if we have a swap space,
## if true, display, if false, hide it ...
if(file_exists("/usr/sbin/swapinfo")) {
	$swapinfo = `/usr/sbin/swapinfo`;
	if(stristr($swapinfo,'%') == true) $showswap=true;
}

## User recently restored his config.
## If packages are installed lets resync
if(file_exists('/conf/needs_package_sync')) {
	if($config['installedpackages'] <> '' && is_array($config['installedpackages']['package'])) {
		if($g['platform'] == "pfSense" || $g['platform'] == "nanobsd") {
			header('Location: pkg_mgr_install.php?mode=reinstallall');
			exit;
		}
	} else {
		conf_mount_rw();
		@unlink('/conf/needs_package_sync');
		conf_mount_ro();
	}
}



	## Find out whether there's hardware encryption or not
	unset($hwcrypto);
	$fd = @fopen("{$g['varlog_path']}/dmesg.boot", "r");
	if ($fd) {
		while (!feof($fd)) {
			$dmesgl = fgets($fd);
			if (preg_match("/^hifn.: (.*?),/", $dmesgl, $matches)
				or preg_match("/.*(VIA Padlock)/", $dmesgl, $matches)
				or preg_match("/^safe.: (\w.*)/", $dmesgl, $matches)
				or preg_match("/^ubsec.: (.*?),/", $dmesgl, $matches)
				or preg_match("/^padlock.: <(.*?)>,/", $dmesgl, $matches)
				or preg_match("/^glxsb.: (.*?),/", $dmesgl, $matches)
				or preg_match("/^aesni.: (.*?),/", $dmesgl, $matches)) {
				$hwcrypto = $matches[1];
				break;
			}
		}
		fclose($fd);
	}

##build widget saved list information
if ($config['widgets'] && $config['widgets']['sequence'] != "") {
	$pconfig['sequence'] = $config['widgets']['sequence'];
	

	$widgetlist = $pconfig['sequence'];
	$colpos = array();
	$savedwidgetfiles = array();
	$widgetname = "";
	$widgetlist = explode(",",$widgetlist);

	##read the widget position and display information
	foreach ($widgetlist as $widget){
		$dashpos = strpos($widget, "-");
		$widgetname = substr($widget, 0, $dashpos);
		$colposition = strpos($widget, ":");
		$displayposition = strrpos($widget, ":");
		$colpos[] = substr($widget,$colposition+1, $displayposition - $colposition-1);
		$displayarray[] = substr($widget,$displayposition+1);
		$savedwidgetfiles[] = $widgetname . ".widget.php";
	}

	##add widgets that may not be in the saved configuration, in case they are to be displayed later
	foreach ($widgetfiles as $defaultwidgets){
		if (!in_array($defaultwidgets, $savedwidgetfiles)){
			$savedwidgetfiles[] = $defaultwidgets;
		}
	}

	##find custom configurations of a particular widget and load its info to $pconfig
	foreach ($widgetnames as $widget){
		if ($config['widgets'][$widget . '-config']){
			$pconfig[$widget . '-config'] = $config['widgets'][$widget . '-config'];
		}
	}

	$widgetlist = $savedwidgetfiles;
} else{
	// no saved widget sequence found, build default list.
	$widgetlist = $widgetfiles;
}



##build list of php include files
$phpincludefiles = array();
$directory = "/usr/local/www/widgets/include/";
$dirhandle  = opendir($directory);
$filename = "";
while (false !== ($filename = readdir($dirhandle))) {
	$phpincludefiles[] = $filename;
}
foreach($phpincludefiles as $includename) {
	if(!stristr($includename, ".inc"))
		continue;
	include($directory . $includename);
}

##begin AJAX
$jscriptstr = <<<EOD
<script type="text/javascript">
//<![CDATA[

function widgetAjax(widget) {
	uri = "widgets/widgets/" + widget + ".widget.php";
	var opt = {
		// Use GET
		type: 'get',
		async: true,
		// Handle 404
		statusCode: {
		404: function(t) {
			alert('Error 404: location "' + t.statusText + '" was not found.');
		}
		},
		// Handle other errors
		error: function(t) {
			alert('Error ' + t.status + ' -- ' + t.statusText);
		},
		success: function(data) {
			widget2 = '#' + widget + "-loader";
			jQuery(widget2).fadeOut(1000,function(){
				jQuery('#' + widget).show();
			});
			jQuery('#' + widget).html(data);
		}
	}
	jQuery.ajax(uri, opt);
}


function addWidget(selectedDiv){
	container 	= 	$('#'+selectedDiv);
	state		=	$('#'+selectedDiv+'-config');
	
	container.show();
	showSave();
	state.val('show');
}

function configureWidget(selectedDiv){
	selectIntLink = '#' + selectedDiv + "-settings";
	if ($(selectIntLink).css('display') == "none")
		$(selectIntLink).show();
	else
		$(selectIntLink).hide();
}

function showWidget(selectedDiv,swapButtons){
	container 	= 	$('#'+selectedDiv+'-container');
	min_btn		=	$('#'+selectedDiv+'-min');
	max_btn		=	$('#'+selectedDiv+'-max');
	state		=	$('#'+selectedDiv+'-config');
		
	container.show();
	min_btn.show();
	max_btn.hide();
	
	showSave();
	
	state.val('show');
}

function minimizeWidget(selectedDiv,swapButtons){
	container 	= 	$('#'+selectedDiv+'-container');
	min_btn		=	$('#'+selectedDiv+'-min');
	max_btn		=	$('#'+selectedDiv+'-max');
	state		=	$('#'+selectedDiv+'-config');
		
	container.hide();
	min_btn.hide();
	max_btn.show();
	
	showSave();
	
	state.val('hide');


}

function closeWidget(selectedDiv){
	widget 		= 	$('#'+selectedDiv);	
	state		=	$('#'+selectedDiv+'-config');
	
	showSave();
	widget.hide();
	state.val('close');
}

function showSave(){
	$('#updatepref').show();
}

function updatePref(){
	var widgets = $('.widgetdiv');
	var widgetSequence = '';
	var firstprint = false;
	
	widgets.each(function(key) {
		obj = $(this);
		
		if (firstprint) 
			widgetSequence += ',';
		
		
		state = $('input[name='+obj.attr('id')+'-config]').val();
		
		widgetSequence += obj.attr('id')+'-container:col1:'+state;
		
		firstprint = true;
	});
	
	$("#sequence").val(widgetSequence);
	
	$("#iform").submit();
	
	return false;
}


//]]>
</script>
EOD;


## Set Page Title and Include Header
$pgtitle = array(gettext("Status: Dashboard"));
include("head.inc");

?>

<body>



<!--script type="text/javascript">
//<![CDATA[
columns = ['col1','col2','col3','col4', 'col5','col6','col7','col8','col9','col10'];
//]]>
</script-->

<?php
include("fbegin.inc");


echo $jscriptstr;

?>

<?php 
	## If it is the first time webConfigurator has been
	## accessed since initial install show this stuff. 
	if(file_exists('/conf/trigger_initial_wizard')) : ?>
	<header class="page-content-head">
		<div class="container-fluid">
			<h1><?=gettext("Starting initial configuration"); ?>!</h1>
		</div>
	</header>

	<section class="page-content-main">
		<div class="container-fluid col-xs-12 col-sm-10 col-md-9">
			<div class="row">
	        	<section class="col-xs-12">
		        	<div class="content-box" style="padding: 20px;">                                   						
							<div class="table-responsive">
								<?php
									echo "<img src=\"/themes/{$g['theme']}/images/default-logo.png\" border=\"0\" alt=\"logo\" /><p>\n";
								?>
								<br />
								<div class="content-box-main">
									<?php
										echo sprintf(gettext("Welcome to %s!\n"),$g['product_name']) . "<p>";
										echo gettext("One moment while we start the initial setup wizard.") . "<p>\n";
										echo gettext("Embedded platform users: Please be patient, the wizard takes a little longer to run than the normal GUI.") . "<p>\n";
										echo sprintf(gettext("To bypass the wizard, click on the %s logo on the initial page."),$g['product_name']) . "\n";
									?>
								</div>
							<div>
					</div>
				</section>
			</div>
		</div>
	</section>
	<meta http-equiv="refresh" content="3;url=wizard.php?xml=setup_wizard.xml">
	<?php exit; ?>
<?php endif; ?>

<section class="page-content-main">
	<div class="container-fluid">
        
        <div class="row">

				<?php            
				/* Print package server mismatch warning. See https://redmine.pfsense.org/issues/484 */
				if (!verify_all_package_servers())
					print_info_box(package_server_mismatch_message());

				if ($savemsg)
					print_info_box($savemsg);


				?>

          

				<?php
					$totalwidgets = count($widgetfiles);
					$halftotal = $totalwidgets / 2 - 2;
					$widgetcounter = 0;
					$directory = "/usr/local/www/widgets/widgets/";
					$printed = false;
					$firstprint = false;
					
					foreach($widgetlist as $widget) {

						if(!stristr($widget, "widget.php"))
									continue;
						$periodpos = strpos($widget, ".");
						$widgetname = substr($widget, 0, $periodpos);
						if ($widgetname != ""){
							$nicename = $widgetname;
							$nicename = str_replace("_", " ", $nicename);

							//make the title look nice
							$nicename = ucwords($nicename);
						}

						if ($config['widgets'] && $pconfig['sequence'] != ""){
							switch($displayarray[$widgetcounter]){
								case "show":
									$divdisplay = "block";
									$display = "block";
									$inputdisplay = "show";
									$showWidget = "none";
									$mindiv = "inline";
									break;
								case "hide":
									$divdisplay = "block";
									$display = "none";
									$inputdisplay = "hide";
									$showWidget = "inline";
									$mindiv = "none";
									break;
								case "close":
									$divdisplay = "none";
									$display = "block";
									$inputdisplay = "close";
									$showWidget = "none";
									$mindiv = "inline";
									break;
								default:
									$divdisplay = "none";
									$display = "block";
									$inputdisplay = "none";
									$showWidget = "none";
									$mindiv = "inline";
									break;
							}
						} else {
							if ($firstprint == false){
								$divdisplay = "block";
								$display = "block";
								$inputdisplay = "show";
								$showWidget = "none";
								$mindiv = "inline";
								$firstprint = true;
							} else {
								switch ($widget) {
									case "interfaces.widget.php":
									case "traffic_graphs.widget.php":
										$divdisplay = "block";
										$display = "block";
										$inputdisplay = "show";
										$showWidget = "none";
										$mindiv = "inline";
										break;
									default:
										$divdisplay = "none";
										$display = "block";
										$inputdisplay = "close";
										$showWidget = "none";
										$mindiv = "inline";
										break;
								}
							}
						}

	

				?>
						<section class="col-xs-12 col-md-6 widgetdiv" id="<?php echo $widgetname;?>"  style="display:<?php echo $divdisplay; ?>;">
				          	<div class="content-box">	          	
			   <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post" id="iform">
	           <input type="hidden" value="" name="sequence" id="sequence" />
								<header class="content-box-head container-fluid">

								    <ul class="list-inline __nomb">
								        <li><h3>
									    <?php
											$widgettitle = $widgetname . "_title";
											$widgettitlelink = $widgetname . "_title_link";
											if ($$widgettitle != "")
											{
												//only show link if defined
												if ($$widgettitlelink != "") {?>
												<u><span onclick="location.href='/<?php echo $$widgettitlelink;?>'" style="cursor:pointer">
												<?php }
													//echo widget title
													echo $$widgettitle;
												if ($$widgettitlelink != "") { ?>
												</span></u>
												<?php }
											}
											else{
												if ($$widgettitlelink != "") {?>
												<u><span onclick="location.href='/<?php echo $$widgettitlelink;?>'" style="cursor:pointer">
												<?php }
												echo $nicename;
													if ($$widgettitlelink != "") { ?>
												</span></u>
												<?php }
											}
										?>					        
								        </h3></li>

								        <li class="pull-right">
								            <div class="btn-group">
								                <button type="button" class="btn btn-default btn-xs" title="minimize" id="<?php echo $widgetname;?>-min" onclick='return minimizeWidget("<?php echo $widgetname;?>",true)' style="display:<?php echo $mindiv; ?>;"><span class="glyphicon glyphicon-minus"></span></button>
								                
								                  <button type="button" class="btn btn-default btn-xs" title="maximize" id="<?php echo $widgetname;?>-max" onclick='return showWidget("<?php echo $widgetname;?>",true)' style="display:<?php echo $mindiv == 'none' ? 'inline' : 'none'; ?>;"><span class="glyphicon glyphicon-plus"></span></button>
								                
								                <button type="button" class="btn btn-default btn-xs" title="remove widget" onclick='return closeWidget("<?php echo $widgetname;?>",true)'><span class="glyphicon glyphicon-remove"></span></button>
								               
								                <button type="button" class="btn btn-default btn-xs" id="<?php echo $widgetname;?>-configure" onclick='return configureWidget("<?php echo $widgetname;?>")' style="display:none; cursor:pointer" ><span class="glyphicon glyphicon-pencil"></span></button>
																	
								            </div>
								        </li>   
								    </ul>                             
								</header>
					          	</form>
					          	<div class="content-box-main collapse in" id="<?php echo $widgetname;?>-container" style="display:<?=$mindiv;?>">
					          		<input type="hidden" value="<?php echo $inputdisplay;?>" id="<?php echo $widgetname;?>-config" name="<?php echo $widgetname;?>-config" />
									
									
									<?php if ($divdisplay != "block") { ?>
									<div id="<?php echo $widgetname;?>-loader" style="display:<?php echo $display; ?>;" align="center">
										<br />
											<span class="glyphicon glyphicon-refresh"></span> <?=gettext("Loading selected widget"); ?>
										<br />
									</div> <?php $display = "none"; } ?>
									
									<?php
										if ($divdisplay == "block")
										{
											include($directory . $widget);
										}
									?>
									<?php $widgetcounter++; ?>
				      			</div>
				            </div>                   
				        </section>

				<? } //end foreach ?>
			
	    </div>
    </div>
</section>



<?php
	//build list of javascript include files
	$jsincludefiles = array();
	$directory = "widgets/javascript/";
	$dirhandle  = opendir($directory);
	$filename = "";
	while (false !== ($filename = readdir($dirhandle))) {
		$jsincludefiles[] = $filename;
	}
	foreach($jsincludefiles as $jsincludename) {
		if(!preg_match('/\.js$/', $jsincludename))
			continue;
		echo "<script src='{$directory}{$jsincludename}' type='text/javascript'></script>\n";
	}
?>


<?php include("foot.inc"); ?>

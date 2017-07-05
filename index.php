<?php
/*
TODO:
HIGH:
  - copy
  - leave notes
  - view source code
  - FAQ about copyright

MEDIUM:
  - can't put images in head, can't do iframe in head, etc.
  - add a "flush before" and "flush after" to each resource

LOW:
  - don't allow "Create" (and other actions?) if edit dialog is open
  - History - pick list of the last N pages I created
  - allow caching option - Don't put a "&t=" param in an asset and do a far-future Expires header so that it gets read from cache.
  - chunk flush delay - add a component that causes the server to return the HTML document up to that point and then delay for N seconds
*/

// AWS is hitting this 1K times per day. There's no User-Agent nor Referer. Stop it!
if ( !array_key_exists('HTTP_USER_AGENT', $_SERVER) || !$_SERVER['HTTP_USER_AGENT'] ) {
	header('HTTP/1.1 400 Bad Request');
	exit();
}

$gCntr = 0;
$gInlineScriptCntr = 0;
$gbGetXHRObjectInserted = false; // use this to only insert the getXHRObject function once
$ghResources = array();  // for each area, this is the string of actual HTML to inject in that region to create the resource
$ghDrops = array();      // for each area, this is the string of HTML to create the editable representation of the resource in the div
$ghPrevConstruct = array(); // for each area, record the most recent construction technique used
$ghStyles = array();
$ghStyles['component'] = "padding: 2px; font-family: Arial; text-align: center; display: block; text-decoration: none; color: white;";
$ghStyles['LI'] = "cursor: move; list-style: none; border-width: 2px; border-style: solid; border-color: #555; margin: 4px;";
$ghStyles['extjs'] = "background: #040;";
$ghStyles['injs'] = "background: #080;";
$ghStyles['extcss'] = "background: #930000;";
$ghStyles['incss'] = "background: #C00;";
$ghStyles['image'] = "background: #000080;";
$ghStyles['iframe'] = "background: #606;";
$ghStyles['compdesc'] = "margin: 0 4px 4px 12px; font-size: 0.8em;";

$ghCharToType = array(
	'j' => 'extjs',   // Js
	'b' => 'injs',    // Block of javascript
	'c' => 'extcss',  // Css
	's' => 'incss',   // Style
	'i' => 'image',   // Image
	'f' => 'iframe'   // iFrame
	);

$ghCharToConst = array(
	'h' => 'html',
	'w' => 'docwrite',
	'd' => 'dom',
	'f' => 'iframe',
	'x' => 'xhr',
	'i' => 'xhrinj'
	);

$ghCharToDrop = array(
	'h' => 'inhead',
	'b' => 'inbody'
	);

$ghCharToDomain = array(
	'0' => '',
	'1' => 'domain1',
	'2' => 'domain2',
	'3' => 'domain3',
	'4' => 'domain4',
	'5' => 'domain5',
	'6' => 'domain6',
	'7' => 'domain7',
	'8' => 'domain8',
	'9' => 'domain9'
	);

$ghTypeToString = array(
	extjs => 'external script',
	injs => 'inline script block',
	extcss => 'external stylesheet',
	incss => 'inline style block',
	image => 'image',
	iframe => 'iframe'
	);

$ghTypeToSleepType = array(
	extjs => 'js',
	extcss => 'css',
	image => 'gif',
	iframe => 'html'
	);


// Iterate over the querystring and construct the HTML strings for the actual
// component as well as its avatar.
function genResources() {
	global $gCntr, $ghCharToType, $ghCharToConst, $ghCharToDrop, $ghCharToDomain;
	$aKeys = array_keys($_GET);
	$len = count($aKeys);

	for ( $i = 0; $i < $len; $i++ ) {
		$regs = array(); // initialize
		$jsdelay = 0;
		$defer = "f";
		$async = "f";

		if ( preg_match('/c[0-9]+$/', $aKeys[$i]) ) {
			if ( preg_match('/^([a-z])([a-z])([0-9])([a-z])([tf])([tf])([tf])([0-9]+)_([0-9]+)_([tf])$/', $_GET[$aKeys[$i]], $regs) ) {
				// example: &c0=hj1hfff2_0_f
				// Added "async" attribute for scripts Nov 11, 2009.
				$drop = $ghCharToDrop[ $regs[1] ];
				$type = $ghCharToType[ $regs[2] ];
				$domain = $ghCharToDomain[ $regs[3] ];;
				$construct = $ghCharToConst[ $regs[4] ];
				$sTimeout = $regs[5];
				$sOnload = $regs[6];
				$defer = $regs[7];
				$delay = $regs[8];
				$jsdelay = $regs[9];
				$async = $regs[10];

				genResource($drop, $type, $domain, $delay, $construct, "t" == $sTimeout, "t" == $sOnload, $jsdelay, "t" == $defer, "t" == $async);
			}
		}
	}
}

function genResource($drop, $type, $domain, $delay, $construct, $bTimeout, $bOnload, $jsdelay, $bDefer, $bAsync) {
	global $gCntr, $ghResources, $ghDrops, $ghStyles, $ghPrevConstruct;
	$gCntr++;

	$domainNum = substr($domain, 6, 1);
	$domain = convertDomain($domain);
	$sPretty = printComponent($type, $domainNum, $delay, $construct, $bTimeout, $bOnload, $jsdelay, $bDefer, $bAsync);

	$results = "<!-- $sPretty -->\n";
	if ( "html" == $construct ) {
		$results .= genHtml($type, $domain, $delay, $jsdelay, $bDefer, $bAsync);
	}
	else if ( "docwrite" == $construct ) {
		$results .= genDocWrite($type, $domain, $delay, $jsdelay);
	}
	else if ( "dom" == $construct ) {
		$results .= genDom($type, $domain, $delay, $bTimeout, $bOnload, $jsdelay);
	}
	else if ( "iframe" == $construct ) {
		$results .= genIframe($type, $domain, $delay, $bTimeout, $bOnload, $jsdelay);
	}
	else if ( "xhr" == $construct ) {
		$results .= genXhr($type, $domain, $delay, $bTimeout, $bOnload, $jsdelay);
	}
	else if ( "xhrinj" == $construct ) {
		$results .= genXhrInjection($type, $domain, $delay, $bTimeout, $bOnload, $jsdelay);
	}

	// Add this component to the real components in the page in the appropriate droparea.
	if ( array_key_exists($drop, $ghResources) ) {
		// To avoid inline script blocking behavior, if we have two adjacent inline blocks, make them a single block.
		if ( array_key_exists($drop, $ghPrevConstruct) &&
			 ( "docwrite" == $ghPrevConstruct[$drop] || "dom" == $ghPrevConstruct[$drop] || "xhr" == $ghPrevConstruct[$drop] || "xhrinj" == $ghPrevConstruct[$drop] ) &&
			 ( "docwrite" == $construct || "dom" == $construct || "xhr" == $construct || "xhrinj" == $construct ) ) {
			if ( preg_match('/^(.*)<\/script>/', $ghResources[$drop], $regs) ) {
				$ghResources[$drop] = $regs[1] . "\n";
				$results = str_replace("<script>\n", "", $results);
				$results = str_replace("<!--", "// <!--", $results);  // change the HTML comment to a JS comment
			}
		}
		$ghResources[$drop] = $ghResources[$drop] . $results . "\n";
	}
	else {
		$ghResources[$drop] = $results . "\n";
	}

	// Construct the HTML to display the component in the appropriate droparea later. (The "avatar".)
	$sId = "acomp$gCntr";
	$sResource = "<li onclick='enableEdit()' id='$sId' class='sortitem $type' style='" . $ghStyles['LI'] . "'><div id={$sId}Div class='component $type' style='$ghStyles[component] $ghStyles[$type] text-align: left;'><span>$sPretty</span></div>";

	$ghDrops[$drop] = ( array_key_exists($drop, $ghDrops) ? $ghDrops[$drop] : "" ) . "$sResource\n";
	$ghPrevConstruct[$drop] = $construct;
}
// Convert from querystring param (eg, "domain1") to actual hostname (eg, "stevesouders.com").
function convertDomain($domain) {
 	if ( preg_match('/^domain([0-9]+)$/', $domain, $regs) ) {
		return $regs[1] . ".cuzillion.com";
	}

	return $domain;
}
function genUrl($type, $domain, $delay, $jsdelay) {
	global $gCntr, $ghTypeToSleepType;
	$type = ( $ghTypeToSleepType[ $type ] ? $ghTypeToSleepType[ $type ] : $type );
	return "http://$domain/bin/resource.cgi?type=$type&sleep=$delay" . ( $jsdelay ? "&jsdelay=$jsdelay" : "" ) . "&n=$gCntr&t=" . time();
}
function genHtml($type, $domain, $delay, $jsdelay, $bDefer, $bAsync) {
    global $gInlineScriptCntr;
	if ( "extjs" == $type ) {        // external script
		$url = genUrl($type, $domain, $delay, $jsdelay);
		return "<script type='text/javascript'" . ( $bDefer ? " defer" : "" ) . ( $bAsync ? " async" : "" ) . " src='$url'></script>\n";
	}
	else if ( "injs" == $type ) {    // inline script
		return "<script" . ( $bDefer ? " defer" : "" ) . ( $bAsync ? " async" : "" ) . "> var injs_now = Number(new Date()); while( injs_now + ($jsdelay*1000) > Number(new Date()) ) { var tmp = injs_now; } if ( 'function' == typeof(scriptSleepOnload) ) scriptSleepOnload('inline script " . (++$gInlineScriptCntr) . "');</script>\n";
	}
	else if ( "extcss" == $type ) {  // external stylesheet
		$url = genUrl($type, $domain, $delay, $jsdelay);
		if ( $bDefer ) { // we piggyback on $bDefer as a flag for stylesheets to use @import (since @import causing them to be slightly delayed)
			return "<style>@import url('$url');</style>\n";
		}
		else {
			return "<link rel='stylesheet' type='text/css' href='$url'>\n";
		}
	}
	else if ( "incss" == $type ) {   // inline style block
		return "<style>\n.floatdiv {width:300px;margin-left:362px};\n</style>\n";
	}
	else if ( "image" == $type ) {   // image
		$url = genUrl($type, $domain, $delay, $jsdelay);
		return "<img src='$url'>\n";
	}
	else if ( "iframe" == $type ) {   // iframe
		$url = genUrl($type, $domain, $delay, $jsdelay);
		return "<iframe src='$url' width=100 height=30 style='background: #EEE;'></iframe>\n";
	}

	return "";
}
function genDocWrite($type, $domain, $delay, $jsdelay) {
	global $gInlineScriptCntr;
	if ( "extjs" == $type ) {        // external script
		$url = genUrl($type, $domain, $delay, $jsdelay);
		return "<script>\ndocument.write('<scr' + 'ipt type=\"text/javascript\" src=\"$url\"></scr' + 'ipt>');\n</script>\n";
	}
	else if ( "injs" == $type ) {    // inline script
		return "<script>\ndocument.write('<scr' + 'ipt> var injs_now = Number(new Date()); while( injs_now + ($jsdelay*1000) > Number(new Date()) ) { var tmp = injs_now; } if ( \"function\" == typeof(scriptSleepOnload) ) scriptSleepOnload(\"inline script " . (++$gInlineScriptCntr) . "\"); </scr' + 'ipt>');\n</script>\n";
	}
	else if ( "extcss" == $type ) {  // external stylesheet
		$url = genUrl($type, $domain, $delay, $jsdelay);
		return "<script>\ndocument.write('<link rel=\"stylesheet\" type=\"text/css\" href=\"$url\">');</script>\n";
	}
	else if ( "incss" == $type ) {   // inline style block
		return "<script>\ndocument.write('<style>.floatdiv {width:300px;margin-left:362px};</style>');\n</script>\n";
	}
	else if ( "image" == $type ) {   // image
		$url = genUrl($type, $domain, $delay, $jsdelay);
		return "<script>\ndocument.write('<img src=\"$url\">');\n</script>\n";
	}
	else if ( "iframe" == $type ) {   // iframe
		$url = genUrl($type, $domain, $delay, $jsdelay);
		return "<script>\ndocument.write('<iframe src=\"$url\" width=100 height=30></iframe>');\n</script>\n";
	}

	return "";
}

function genDom($type, $domain, $delay, $bTimeout, $bOnload, $jsdelay) {
	global $gCntr;
	$url = genUrl($type, $domain, $delay, $jsdelay);
	$elemname = "elem$gCntr";

	// All components are created within a "create_XXX" function.
	$results = "<script>\nfunction create_$elemname() {\n";

	// The guts of the create_ function depend on the component type:
	if ( "extjs" == $type ) {        // external script
	    $results .=
"    var $elemname = document.createElement('script');\n" .
"    $elemname.src = '$url';\n" .
"    document.getElementsByTagName('head')[0].appendChild($elemname);\n";
	}
	else if ( "extcss" == $type ) {  // external stylesheet
	    $results .=
"    var $elemname = document.createElement('link');\n" .
"    $elemname.rel = 'stylesheet';\n" .
"    $elemname.type = 'text/css';\n" .
"    $elemname.href = '$url';\n" .
"    document.getElementsByTagName('head')[0].appendChild($elemname);\n";
	}
	else if ( "image" == $type ) {   // image
	    $results .=
"    var $elemname = new Image();\n" .
"    $elemname.src = '$url';\n";
	}
	else if ( "iframe" == $type ) {   // iframe
	    $results .=
"    var $elemname = document.createElement('iframe');\n" .
"    $elemname.width = '80%';\n" .
"    $elemname.height = '70';\n" .
"    $elemname.src = '$url';\n" .
"    document.body.appendChild($elemname);\n";
	}

	$results .= "}\n" . genCreateFunctions($elemname, $bTimeout, $bOnload) . "</script>\n";

	return $results;
}
function genIframe($type, $domain, $delay, $bTimeout, $bOnload, $jsdelay) {
	global $gCntr;

	$url = "";
	if ( "extjs" == $type ) {        // external script
		$url = genUrl("jsiframe", $domain, $delay, $jsdelay);
	}
	else if ( "extcss" == $type ) {  // external stylesheet
		$url = genUrl("cssiframe", $domain, $delay, $jsdelay);
	}

	if ( $url ) {
		if ( !$bTimeout && !$bOnload ) {
			// straightforward - add an invisible iframe with the appropriate URL
			return "<iframe src='$url' width=0 height=0 frameborder=0 onload='scriptSleepOnload(\"$url\")'></iframe>\n";
		}
		else {
			// more complicated - use a function to create an invisible iframe DOM element,
			// then call that function either directly, or via setTimeout, or via onload listener.
			$elemname = "elem$gCntr";
			return
				"<script>\n" .
				"function create_$elemname() {\n" .
				"    var $elemname = document.createElement('iframe');\n" .
				"    $elemname.width = '0';\n" .
				"    $elemname.height = '0';\n" .
				"    $elemname.src = '$url';\n" .
				( "extjs" == $type ? "    cuz_addHandler($elemname, 'load', function(){scriptSleepOnload('$url');});\n" : "" ) .
				"    document.body.appendChild($elemname);\n" .
				"}\n" .
				genCreateFunctions($elemname, $bTimeout, $bOnload) .
				"</script>\n";
		}
	}

	return "";
}
// for XHR we need to always request them from the domain of the main page
function thisDomain() {
	return $_SERVER{'HTTP_HOST'};
}
function genXhr($type, $domain, $delay, $bTimeout, $bOnload, $jsdelay) {
	global $gCntr;
	if ( "extjs" == $type ) {        // external script
		$url = genUrl($type, thisDomain(), $delay, $jsdelay);
		$elemname = "elem$gCntr";

		return
			"<script>\n" .
			"function create_$elemname() {\n" .
            "  xhrObj_$elemname = getXHRObject();\n" .
            "  xhrObj_$elemname.onreadystatechange = function() { if ( xhrObj_$elemname.readyState != 4 || 200 != xhrObj_$elemname.status ) return; eval(xhrObj_$elemname.responseText); };\n" .
            "  try {\n" .
            "    xhrObj_$elemname.open('GET', '$url', true);\n" .
            "    xhrObj_$elemname.send('');\n" .
            "  }\n" .
            "  catch(e) {\n" .
            "    }\n" .
            "}\n" .
			insertGetXHRObjectFunction() .
			genCreateFunctions($elemname, $bTimeout, $bOnload) .
			"</script>\n";
	}

	return "";
}

// download a typical script or stylesheet, but inject it into the page
function genXhrInjection($type, $domain, $delay, $bTimeout, $bOnload, $jsdelay) {
	global $gCntr;

		$url = genUrl($type, thisDomain(), $delay, $jsdelay);
		$elemname = "elem$gCntr";

		return
			"<script>\n" .
			"function create_$elemname() {\n" .
            "  xhrObj_$elemname = getXHRObject();\n" .
            "  xhrObj_$elemname.onreadystatechange = function() { if ( xhrObj_$elemname.readyState != 4 || 200 != xhrObj_$elemname.status ) return; " .

			( "extjs" == $type ?
			  "var se = document.createElement('script'); document.getElementsByTagName('head')[0].appendChild(se); se.text = xhrObj_$elemname.responseText;"
			  :
			  // CVSNO - do stylesheet
			  "var se = document.createElement('script'); document.getElementsByTagName('head')[0].appendChild(se); se.text = xhrObj_$elemname.responseText;"
			) .

            " };\n" .
            "  try {\n" .
            "    xhrObj_$elemname.open('GET', '$url', true);\n" .
            "    xhrObj_$elemname.send('');\n" .
            "  }\n" .
            "  catch(e) {\n" .
            "  }\n" .
            "}\n" .
			insertGetXHRObjectFunction() .
			genCreateFunctions($elemname, $bTimeout, $bOnload) .
			"</script>\n";
}
function genCreateFunctions($elemname, $bTimeout, $bOnload) {
	$results = "";

	if ( $bTimeout ) {
		// Create a function that calls the create_elemN function in a setTimeout.
		$results .= "function setTimeout_$elemname() { setTimeout('create_$elemname()', 1); }\n";
	}

	if ( !$bTimeout && !$bOnload ) {
		$results .= "create_$elemname();\n";
	}
	else if ( $bTimeout && !$bOnload ) {
		$results .= "setTimeout_$elemname();\n";
	}
	else if ( !$bTimeout && $bOnload ) {
		$results .= "cuz_addHandler(window, 'load', create_$elemname);\n";
	}
	else if ( $bTimeout && $bOnload ) {
		$results .= "cuz_addHandler(window, 'load', setTimeout_$elemname);\n";
	}

	return $results;
}
function printComponent($type, $domainNum, $delay, $construct, $bTimeout, $bOnload, $jsdelay, $bDefer, $bAsync) {
	global $ghTypeToString, $ghStyles;
	$sResult = $ghTypeToString[ $type ] . "<p style='" . $ghStyles['compdesc'] . "'>";

	$sResult .= ( $domainNum ? " on domain" . $domainNum : "" );
	$sResult .= ( 0 < $delay ? " with a " . $delay . " second delay" : "" );
	$sResult .= ( 0 < $jsdelay ? " with a " . $jsdelay . " second execute time" : "" );
	$sResult .= ( 'html' == $construct ? " using HTML tags" :
				 ( 'docwrite' == $construct ? " using document.write" :
				   ( 'dom' == $construct ? " using script DOM element" :
					 ( 'iframe' == $construct ? " using an iframe" :
					   ( 'xhr' == $construct ? " using XHR eval" :
	                     ( 'xhrinj' == $construct ? " using XHR injection" : " using unknown" ) ) ) ) ) );
	$sResult .= ( $bDefer ? ("extcss" == $type ? " with @import" : " with defer") : "" );
	$sResult .= ( $bAsync ? " with async" : "" );
	$sResult .= ( $bTimeout ? " via setTimeout" : "" );
	$sResult .= ( $bOnload ? " after onload" : "" );

    return $sResult;
}
function printActualResources($sDrop) {
	global $ghResources;

	if ( array_key_exists($sDrop, $ghResources) ) {
		return "<!-- begin resources for $sDrop -->\n" . $ghResources[$sDrop] . "<!-- end resources for $sDrop -->\n";
	}
}
function printResources($sDrop) {
	global $ghDrops;

	if ( array_key_exists($sDrop, $ghDrops) ) {
		return $ghDrops[$sDrop];
	}
}

// return a URL that will properly reload the current test page
function reloadUrl() {
	$uri = $_SERVER{'REQUEST_URI'};
	if ( ! strpos($uri, "?") ) {
		return $uri . "?t=" . time();
	}
	else if ( ! strpos($uri, "&t=") ) {
		return $uri . "&t=" . time();
	}
 	else if ( preg_match('/^(.*)&t=[0-9]*(.*)$/', $uri, $regs) ) {
		return $regs[1] . "&t=" . time() . $regs[2];
	}
	else {
		return $uri;
	}
}

function insertGetXHRObjectFunction() {
	global $gbGetXHRObjectInserted;
	if ( ! $gbGetXHRObjectInserted ) {
		$gbGetXHRObjectInserted = true;
		return
			"function getXHRObject() {\n" .
            "  var xhrObj = false;\n" .
            "  try {\n" .
            "    xhrObj = new XMLHttpRequest();\n" .
            "  }\n" .
            "  catch(e){\n" .
            "    var progid = ['MSXML2.XMLHTTP.5.0', 'MSXML2.XMLHTTP.4.0', 'MSXML2.XMLHTTP.3.0', 'MSXML2.XMLHTTP', 'Microsoft.XMLHTTP'];\n" .
            "    for ( var i=0; i < progid.length; ++i ) {\n" .
            "      try {\n" .
            "        xhrObj = new ActiveXObject(progid[i]);\n" .
            "      }\n" .
            "      catch(e) {\n" .
            "        continue;\n" .
            "      }\n" .
            "      break;\n" .
            "    }\n" .
            "  }\n" .
            "  finally {\n" .
            "    return xhrObj;\n" .
            "  }\n" .
            "}\n";
	}

	return "";
}

// This is the main function call to parse the querystring and create all the resources.
genResources();  // create all the resources and save them for later
?>

<script>
var gTop = Number(new Date());
var gScriptMsg = "";
function cuz_addHandler(elem, sType, fn, capture) {
    capture = (capture) ? true : false;
    if (elem.addEventListener) {
        elem.addEventListener(sType, fn, capture);
    }
    else if (elem.attachEvent) {
        elem.attachEvent("on" + sType, fn);
    }
    else {
        // Netscape 4
        if ( elem["on" + sType] ) {
            // Do nothing - we don't want to overwrite an existing handler.
        }
        else {
            elem["on" + sType] = fn;
        }
    }
}
function doOnload() {
	var end = Number(new Date());
<?php
	if ( 0 < count($ghDrops) ) {
		echo "    document.getElementById('loadtime').innerHTML = 'page load time: ' + (end - gTop) + ' ms';\n";
	}
?>
	if ( gScriptMsg && document.getElementById('loadedscripts') ) {
		document.getElementById('loadedscripts').innerHTML += gScriptMsg;
	}
}
cuz_addHandler(window, 'load', doOnload);
var gbEnabled = false;
function enableEdit() {
	if ( gbEnabled ) return;
	gbEnabled = true;
	addStylesheet('cuzillion.css');
	addScript('cuzillion.js');
}
function addStylesheet(url) {
	var stylesheet = document.createElement('link');
	stylesheet.rel = 'stylesheet';
	stylesheet.type = 'text/css';
	stylesheet.href =  url;
	document.getElementsByTagName('head')[0].appendChild(stylesheet);
}
function addScript(url) {
	var script = document.createElement('script');
	script.src = url;
	document.getElementsByTagName('head')[0].appendChild(script);
}
function scriptSleepOnload(sUrl) {
	var now = Number(new Date());
	var msg = "<nobr>" + (now - gTop) + "ms: \"" + sUrl + "\" done</nobr>\n";
	if ( document.getElementById('loadedscripts') ) {
		document.getElementById('loadedscripts').innerHTML += msg;
	}
	else {
		gScriptMsg += msg;
	}
}
<?php
/*
function doPreview() {
// CVSNO - move this to an external script
    var ti = cleanText(document.form1.ti.value);
    var flname = cleanText(document.form1.flname.value);
    var userurl = cleanText(document.form1.userurl.value);
    var desc = cleanText(document.form1.desc.value);
    sHtml = "<div style='font-weight: bold; font-size: 1.3em;'>" + ti + "</div>";
    sHtml += "<div id=description style='font-size: 0.9em;'>";
    if ( flname && userurl ) {
        sHtml += "<div style='margin: 0 0 4px 20px; font-size: 0.9em;'><i>posted by <a href='" + userurl + "'>" + flname + "</a></i></div>";
    }
    else if ( flname ) {
        sHtml += "<div style='margin: 0 0 4px 20px; font-size: 0.9em;'><i>posted by " + flname + "</i></div>";
    }
    else if ( userurl ) {
        sHtml += "<div style='margin: 0 0 4px 20px; font-size: 0.9em;'><i>posted by <a href='" + userurl + "'>" + userurl + "</a></i></div>";
    }
    sHtml += desc + "</div>";
    document.getElementById('previewhtml').innerHTML = sHtml;

    document.getElementById('preview').style.display = "block";
    document.getElementById('saveform').style.display = "none";
}
*/
?>
function reloadPage(url) {
	document.body.innerHTML = '';
	document.location = url;
}
function cleanText(sText) {
    return sText.replace(/<.*?>/g, '');
}
</script>
<html>
<?php echo printActualResources('ahtml') ?>
<head>
<title>Cuzillion</title>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<!--
Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

     http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
-->
<?php echo printActualResources('inhead') ?>
<?php
if ( 0 == count($ghDrops) ) {
	// If no components, automatically load Edit mode.
	echo "<link rel='stylesheet' type='text/css' href='cuzillion.css'>\n";
}
?>
</head>

<?php echo printActualResources('bbody') ?>
<body style='margin: 0px; padding: 0px; font-family: "Trebuchet MS", "Bitstream Vera Serif", Utopia, "Times New Roman", times, serif;'>

<div style="background: #333; color: white; padding: 8px;">
  <div style="float:right; margin-top: 2px;">
    <a href="help.php" style="color: white; font-size: 0.9em; text-decoration: none;">Help</a>
  </div>
  <font style="font-size: 2em; font-weight: bold; margin-right: 10px;"><a href="." style="color:white; text-decoration: none;"><img border=0 src="logo-32x32.gif">&nbsp;Cuzillion</a></font><i>&apos;cuz there are a zillion pages to check</i>
</div>

<div id=content style="margin: 8px;">

<?php echo printActualResources('inbody') ?>

<div id=floattray style="float: left; width: 170px; margin-right: 30px;">
  <div id=step1text style="text-align: left; margin: 0 0 4px 4px; height: 50px; padding-top: 12px;"></div>
  <div id=comptray>
  &nbsp;
  </div>
</div>

<div id=pageavatar style="float: left; width: 310px; margin-right: 30px;">
  <div id=step2text style="text-align: left; margin: 0 0 4px 4px; height: 50px; padding-top: 12px;"></div>
  <div style="background: #CCC; border: 1px solid black; ">
    <code style="font-size: 1.2em; font-weight: bold; color: #666666; display: block;">&lt;HTML&gt;</code>
    <code style="font-size: 1.2em; font-weight: bold; color: #666666; display: block;">&lt;HEAD&gt;</code>
    <div class="drop" style="border: 1px solid #EEE; background: #EEE; padding: 12px 0 12px 0; width: 300px; margin: 0 0 0 4px;">
	  <ul style="margin: 0; padding: 0;" id=inhead><?php echo printResources('inhead') ?></ul>
	  <div id=inheadTarget></div>
	</div>
    <code style="font-size: 1.2em; font-weight: bold; color: #666666; display: block;">&lt;/HEAD&gt;</code>
    <code style="font-size: 1.2em; font-weight: bold; color: #666666; display: block;">&lt;BODY&gt;</code>
    <div class="drop" style="border: 1px solid #EEE; background: #EEE; padding: 12px 0 12px 0; width: 300px; margin: 0 0 0 4px;">
	  <ul style="margin: 0; padding: 0;" id=inbody><?php echo printResources('inbody') ?></ul>
	  <div id=inbodyTarget></div>
	</div>
    <code style="font-size: 1.2em; font-weight: bold; color: #666666; display: block;">&lt;/BODY&gt;</code>
    <code style="font-size: 1.2em; font-weight: bold; color: #666666; display: block;">&lt;/HTML&gt;</code>
  </div>
  <div id=loadtime style="text-align: left; margin-top: 10px;"></div>
  <div id=loadedscripts style="text-align: left; margin-top: 10px; width: 300px; font-size: 0.9em;"></div>
</div> <!-- end pageavatar -->

<div style="position: absolute; left: 560px;">
  <div id=step3text style="text-align: left; margin: 0 0 4px 4px; height: 50px; padding-top: 12px;"></div>
  <div id=pagesubmit style="text-align: left;">
<?php
if ( 0 < count($ghDrops) ) {
?>
<nobr>
<input type=button value="Edit" onclick="enableEdit()">&nbsp;&nbsp;
<input type=button value="Reload" onclick="reloadPage('<?php echo reloadUrl() ?>')">&nbsp;&nbsp;
<input type=button value="Clear" onclick="document.location='.'">&nbsp;&nbsp;
<?php
if ( array_key_exists('sks', $_GET) ) { // for now hide the Save button
?>
<input type=button value="Save..." onclick="document.getElementById('save').style.display='block';document.getElementById('saveform').style.display='block';">
<?php
}
?>
</nobr>
<?php
}
?>
 </div>
</div>

<div style="clear: both;">
</div>

</div> <!-- content -->

<?php
if ( 0 == count($ghDrops) ) {
	// If no components, automatically load Edit mode.
	echo "<script src='cuzillion.js'></script>\n";
}
?>

</body>
<?php echo printActualResources('abody') ?>

</html>

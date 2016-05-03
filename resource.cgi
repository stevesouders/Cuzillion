#!/usr/bin/perl

################################################################################
# This CGI is used to simulate different types of components in an HTML page
# that take different lengths of time. Here are the CGI params and legal values:
#     type=[gif|js|css|html|swf|xhr|jsxhr|jsiframe|cssiframe]
#           default is "gif"
#     sleep=N
#           N is a number of seconds for the server to wait before returning the response
#           default is 0
#     jsdelay=N
#           This option is only applicable if the type is js, jsxhr, or jsiframe. The JavaScript
#           that is returned will contain a loop that executes for N seconds.
#     expires=[-1|0|1]
#          -1   return an Expires header in the past
#           0   do not return an Expires header
#           1   return an Expires header in the future (default)
#     max-age=N
#           If the "expires" option is 1, this specifies the number of seconds for the 
#           Cache-Control max-age value, as well as the time used to calculate the Expires date.
#     last=[-1|0|1]
#          -1   return a Last-Modified header in the past
#           0   do not return a Last-Modified header
#           1   return a Last-Modified header 1 day in the past
#     redir=1
#           1 means return a 302 redirect response that redirects right back with the "redir=1" removed
#     cookie=[N|t]
#           The response sets a cookie named A that is N characters in length.
#           If "cookie=t" is specified, the epoch time is returned as the A cookie.
#     headers=1
#           The response is JavaScript with an array containing each HTTP request header.
#     size=N
#           The size of the response.
################################################################################

use URI::Escape;

my $gQuerystring;
my %gParams;
my %gStatusText;
$gStatusText[301] = "Moved Permanently";
$gStatusText[302] = "Moved Temporarily";
$gStatusText[303] = "See Other";
$gStatusText[304] = "Not Modified";
$gStatusText[305] = "Use Proxy";
$gStatusText[306] = "";
$gStatusText[307] = "Temporary Redirect";


main();

exit 0;


sub main {
    parseParams();

    # I intentionally send the headers, THEN sleep, THEN send the content, so the browser gets a nibble of a first byte.
    print genHeaders();

    sleep($gParams{'sleep'}) if ( 0 < $gParams{'sleep'} );

    print genContent();
}


sub parseParams {
    my $querystring = "";

    if ($ENV{'REQUEST_METHOD'} eq 'GET') {
        $querystring = $ENV{'QUERY_STRING'};
    }
    elsif ($ENV{'REQUEST_METHOD'} eq 'POST') {
        read(STDIN, $querystring, $ENV{'CONTENT_LENGTH'});
    }
    else {
        $querystring = $ARGV[0];   # for commandline execution
    }
    $gQuerystring = $querystring;

    # Defaults:
    $gParams{'type'} = "gif";
    $gParams{'sleep'} = 0;
    $gParams{'jsdelay'} = 0;
    $gParams{'expires'} = 1;  # future Expires header
    $gParams{'last'} = -1;   # Last-Modified equal to a date in the past

    # Now parse the CGI querystring.
    if ( $querystring && "" ne $querystring ) {
        foreach my $tuple ( split(/&/, $querystring) ) {
            my ($key, $value) = split(/=/, $tuple);
            $gParams{$key} = $value;
        }
    }
}


sub genHeaders {
    my $type = $gParams{'type'};
	# For some reason Apache always returns a 304 when there's an If-Modified-Since header,
	# even when the response $content is changed. (I verified that this CGI gets executed
	# and the $content changes but Apache STILL returns a 304.) 
	# If this code is being executed, then we ALWAYS want to return a 200 response.
	# (Except for redirects which is taken care of below.
    my $headers = "Status: 200 OK\n"; 

    if ( $gParams{'redir'} ) {
        my $querystring = $gQuerystring;
		my $location = "";
        # Redir back to sleep.cgi without the redir param in the querystring.
        $querystring =~ s/[&]*redir=[^&]*//g;
        $querystring =~ s/^&//;  # make sure it doesn't start with "&"

        my $uri = $ENV{'REQUEST_URI'};
        $uri = $1 if ( $uri =~ /^(.*)\?/ );
        my $host = $ENV{'HTTP_HOST'};
        my $port = $ENV{'SERVER_PORT'};
        $location = ( 443 == $port ? "https://" : "http://" ) . "$host$uri?$querystring";
		# Use the more aggressive 301 response to try and promote caching (since most browsers don't even cache 301s). 
		my $status = $gParams{'status'} || 301;
		$status = ( 301 <= $status && $status <= 307 ? $status : 301 );
        $headers = "Status: $status " . $gStatusText[$status] . "\nContent-Type: text/html\nLocation: $location\n";
    }
    elsif ( "css" eq $type ) {
        $headers .= "Content-Type: text/css\n";
    }
    elsif ( "js" eq $type || $gParams{'headers'} ) {
        $headers .= "Content-Type: application/x-javascript\n";
    }
    elsif ( "html" eq $type || "xhr" eq $type || "cssiframe" eq $type || "jsiframe" eq $type || "jsxhr" eq $type ) {
        $headers .= "Content-Type: text/html\n";
    }
    elsif ( "swf" eq $type ) {
        $headers .= "Content-Type: application/x-shockwave-flash\n";
    }
    elsif ( "font" eq $type ) {
        $headers .= "Content-Type: " . (-1 == index($ENV{'HTTP_USER_AGENT'}, "MSIE") ? "font/ttf" : "application/octet-stream" )  . "\n";
		if ( ! $gParams{'accessoff'} ) {
			$headers .= "Access-Control-Allow-Origin: *\n";
		}
    }
    else {  # gif
        $headers .= "Content-Type: image/gif\n";
    }

	if ( $gParams{'cookie'} ) {
		if ( "t" == $gParams{'cookie'} ) {
			$headers .= "Set-Cookie: A=" . time() . "; path=/\n";
		}
		else {
			$headers .= "Set-Cookie: A=" . ('a' x $gParams{'cookie'}) . "; path=/\n";
		}
	}

    # If requested, include an Expires (and Cache-Control) header in the past or future.
    if ( $gParams{'expires'} ) {
		my $expirationSeconds = ( $gParams{'max-age'} ? $gParams{'max-age'} : 30*24*60*60 );  # 30 days by default
		my $epoch = ( -1 == $gParams{'expires'} ? time() - $expirationSeconds : time() + $expirationSeconds );
		my ($sec, $min, $hour, $day, $month, $year, $wday) = gmtime($epoch);
		$year += 1900;
		my $expires =  sprintf("Expires: %s, %.2d %s %d %.2d:%.2d:%.2d GMT\n",
							   ("Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat")[$wday],
							   $day, 
							   ("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec")[$month],
							   $year, $hour, $min, $sec);
		$expires .= "Cache-Control: public, max-age=" . ( -1 == $gParams{'expires'} ? "0" : $expirationSeconds ) . "\n";
		$headers .= $expires;
    }

    # If requested, include a Last-Modified header in the past.
    if ( $gParams{'last'} ) {
		# 'last' should be either -1 or 1
		#   -1 => a fixed date in the past: noon 1/15/2006 GMT = 1137326400
		#    1 => 1 day in the past
		my $epoch = ( 1 == $gParams{'last'} ? ( time() - (24*60*60) ) : 1137326400 );
		my ($sec, $min, $hour, $day, $month, $year, $wday) = gmtime($epoch);
		$year += 1900;
		my $last =  sprintf("Last-Modified: %s, %.2d %s %d %.2d:%.2d:%.2d GMT\n",
							("Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat")[$wday],
							$day, 
							("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec")[$month],
							$year, $hour, $min, $sec);
		$headers .= $last;
    }

    return $headers . "\n";  # need two returns at the end of the headers
}


sub genContent {
    my $type = $gParams{'type'};
    my $content = "";

    if ( "css" eq $type ) {
        $content = ".sleepcgi { background: #EEE; color: #606; font-weight: bold; padding: 10px; }\n";
		if ( $gParams{'size'} ) {
			$content .= generate_random_css($gParams{'size'} - length($content));
		}
    }
	elsif ( $gParams{'headers'} ) {
		foreach $var (sort keys (%ENV)) {
			$content .= ( $content ? ",\n" : "\n" ) . "    '$var': '$ENV{$var}'";
		}
		$content = "var aHeaders = {" . $content . "\n};\n";
    }
    elsif ( "js" eq $type ) {
        $content = "var se = " . time() . ";\nvar sleep_now = Number(new Date());\n\nfunction externalFunction1(i) { return i + 1; }\n\nwhile(sleep_now+$gParams{'jsdelay'}000>Number(new Date())) { var tmp = sleep_now; }\nif ( 'function' == typeof(scriptSleepOnload) ) scriptSleepOnload('http://" . $ENV{'HTTP_HOST'} . $ENV{'REQUEST_URI'} . "');\n";
		if ( $gParams{'size'} ) {
			$content .= generate_random_js($gParams{'size'} - length($content));
		}
    }
    elsif ( "xhr" eq $type ) { # might want to make this JSON
        $content = "XHR response from resource.cgi, epoch time = " . time() . "\n";
    }
    elsif ( "jsxhr" eq $type ) {
        $content = "var sleep_now = Number(new Date()); while(sleep_now+$gParams{'jsdelay'}000>Number(new Date())) { var tmp = sleep_now; } if ( 'function' == typeof(scriptSleepOnload) ) scriptSleepOnload('http://" . $ENV{'HTTP_HOST'} . $ENV{'REQUEST_URI'} . "');";
    }
    elsif ( "html" eq $type ) {
		$sleepVal = $gParams{'sleep'};
        $content1 = <<OUTPUT
<html>
<head>
<title>resource.cgi test page</title>
</head>
<body bgcolor=#F0F0F0>
This HTML document took $sleepVal seconds to return.
OUTPUT
    ;
        $content2 = <<OUTPUT
</body>
</html>
OUTPUT
    ;
		$content = $content1 . 
			( $gParams{'size'} ? generate_random_html($gParams{'size'} - length($content1 . $content2)) : "" ) .
			$content2;
    }
    elsif ( "jsiframe" eq $type ) {
        $content = <<OUTPUT
<html>
<head>
<title>resource.cgi test page</title>
<script>
var sleep_now = Number(new Date());
while(sleep_now+$gParams{'jsdelay'}000>Number(new Date())) { var tmp = sleep_now; }
</script>
</head>
<body>
</body>
</html>
OUTPUT
    ;
    }
    elsif ( "cssiframe" eq $type ) {
        $content = <<OUTPUT
<html>
<head>
<title>resource.cgi test page</title>
<style>
.sleepcgi { background: #EEE; color: #606; font-weight: bold; padding: 10px; }
</style>
</head>
<body>
</body>
</html>
OUTPUT
    ;
    }
    elsif ( "swf" eq $type ) {
        my $file = "./sleep.swf";
        if ( -e $file ) {
			open(IN, $file);
			while(<IN>) {
				my $line = $_;
				$content .= $line;
			}
		}
    }
    elsif ( "font" eq $type ) {
		my $file = (-1 == index($ENV{'HTTP_USER_AGENT'}, "MSIE") ? "./yanone.ttf" : "./yanone.eot" );
        if ( -e $file ) {
			open(IN, $file);
			while(<IN>) {
				my $line = $_;
				$content .= $line;
			}
		}
    }
    else {  # "gif"
        # Can't figure out a way to output a GIF in code, so read a file from disk.
        my $file = gifFile();
        if ( -e $file ) {
			open(IN, $file);
			while(<IN>) {
				my $line = $_;
				$content .= $line;
			}
		}
		else {
			print STDERR "ERROR: resource.cgi: couldn't open file \"$file\".\n";
		}
    }

    return $content;
}



# This function generates random strings of a given length
sub generate_random_string {
	my $length_of_randomstring=shift;
	my @chars=('a'..'z','A'..'Z');
	my $random_string;
	foreach (1..$length_of_randomstring) {
		# rand @chars will generate a random number between 0 and scalar @chars
		$random_string .= $chars[rand @chars];
	}

	return $random_string;
}


sub generate_random_js {
	my $size = shift;
	my $cursize = 0;
	my $result = "";
	my $fixedCost = length("var abcdefgh='';\n");
	while ( $cursize < $size ) {
		# Either use it all up, or make sure to leave enough to generate another line.
		$rem = $size - $cursize;
		$randSize = ( ((1000 + (2*$fixedCost)) < $rem ) ? 1000 : ($rem - $fixedCost) );
		$result .= "var " . generate_random_string(8) . "='" . generate_random_string($randSize) . "';\n";
		$cursize = length($result);
	}

	return $result;
}


sub generate_random_css {
	my $size = shift;
	my $cursize = 0;
	my $result = "";
	my $fixedCost = length(".12345678901234567890123456789012 { font-family: ; }\n");
	while ( $cursize < $size ) {
		# Either use it all up, or make sure to leave enough to generate another line.
		$rem = $size - $cursize;
		$randSize = ( ((300 + (2*$fixedCost)) < $rem ) ? 300 : ($rem - $fixedCost) );
		$result .= "." . generate_random_string(32) . " { font-family: " . generate_random_string($randSize) . "; }\n";
		$cursize = length($result);
	}

	return $result;
}


sub generate_random_html {
	my $size = shift;
	my $cursize = 0;
	my $result = "";
	my $fixedCost = length("<p></p>\n");
	while ( $cursize < $size ) {
		# Either use it all up, or make sure to leave enough to generate another line.
		$rem = $size - $cursize;
		$randSize = ( ((300 + (2*$fixedCost)) < $rem ) ? 300 : ($rem - $fixedCost) );
		$result .= "<p>" . generate_random_string($randSize) . "</p>\n";
		$cursize = length($result);
	}

	return $result;
}


sub gifFile {
	if ( $gParams{'giffile'} ) {
		my $giffile = $gParams{'giffile'};
		$giffile =~ s/\//_/g;
		return "../images/" . $giffile;
	}
	else {
		my $i = rand(4);
		return "./" . ("starfish1.gif", "barracuda.gif", "turtle2.gif", "crab3.gif")[$i];
	}
}


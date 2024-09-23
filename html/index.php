<?php
// Galaxy Apple Wallet Server Dashboard
// by Luke Jozwiak
// Last Update: 2 May 2018

require '../Outside_Web_Root/config.php';
session_start ();

// Show the login page first, and if they enter the right password, reload as the dashboard page
if ($_POST['txtPassword'] != $stats_pass)
	LoginPage ();
else
	Dashboard ($conn_method, $SOAP_URL, $API_SourceID, $server, $db, $username, $password, $week_start_day, $web_stats_link, $logname);


// Renders Login Page
function LoginPage ()
{ ?>
	<html>
	<head>
		<title>Galaxy Apple Wallet</title>
		<link rel="stylesheet" type="text/css" href="css/login.css">
	</head>

	<body>
		<div class="login">
			<h1>Galaxy Apple Wallet Server</h1>

			<form name="form" method="post" action="<?php echo htmlentities ($_SERVER['PHP_SELF'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
				<input type="password" id="passfield" title="Enter password" name="txtPassword" autofocus="autofocus">

				<p><input type="submit" name="Submit" value="Login"></p>
			</form>
		</div>
	</body>
	</html> <?php
}


// Dashboard (The Main Event)
function Dashboard ($conn_method, $SOAP_URL, $API_SourceID, $server, $db, $username, $password, $week_start_day, $web_stats_link, $logname)
{
	// Render the head of the dashboard page (mainly CSS)
	StyleDashboard ();

	// Test and display DB connectivity
	ConnTest ($conn_method, $SOAP_URL, $API_SourceID, $server, $db, $username, $password);

	// Render settings panel
	ShowSettings ();

	// Garner current dates
	date_default_timezone_set ("America/Los_Angeles");
	$today = date ("Y-m-d");
	$yesterday = date ("Y-m-d", strtotime ("yesterday"));

	// Get the week end string
	list ($week_start_string, $week_end_string, $week_end_day) = WeekStartEnd ($week_start_day);

	// Get the current and previous week date range
	list ($beginning_last_week, $end_last_week, $beginning_week, $end_week) = ThisAndLastWeek ($week_start_day, $week_end_day, $week_start_string, $week_end_string);

	// Get the current and previous months
    $this_month = date ("Y-m");
	$last_month = date ("Y-m", strtotime ("first day of last month"));

	// Get the current and previous years
    $this_year = date ("Y");
	$last_year = date ("Y", strtotime ("last year"));

	// Tally how many downloads in each date range from the log
	list ($counter, $count_today, $count_yesterday, $count_this_week, $count_last_week, $count_this_month, $count_last_month, $count_this_year, $count_last_year) = ParseLog ($today, $yesterday, $beginning_week, $end_week, $beginning_last_week, $end_last_week, $this_month, $last_month, $this_year, $last_year, $logname);

	// Render stats
	ShowStats ($counter, $count_today, $count_yesterday, $count_this_week, $count_last_week, $count_this_month, $count_last_month, $count_this_year, $count_last_year);

	// Render log window
	ShowLog ($logname);

	// Render footer
	ShowFooter ($web_stats_link, $logname);
}


// Renders the head of the dashboard page
function StyleDashboard ()
{ ?>
	<html>
	<head>
		<title>Galaxy Apple Wallet</title>
		<link rel="stylesheet" type="text/css" href="css/dashboard.css">
	</head>
	<body onload="UpdateLog ()"> <?php
}


// Tests and displays DB connectivity status
function ConnTest ($conn_method, $SOAP_URL, $API_SourceID, $server, $db, $username, $password)
{
	if ($conn_method == "api")
	{
		$XML_Post = BuildXML ($API_SourceID);
		$POST_Headers = SetPOSTHeaders ($XML_Post);
		$conn_test = APIQuery ($SOAP_URL, $POST_Headers, $XML_Post);
	}
	elseif ($conn_method == "db")
	{
		$conn_test = 1;

		try
		{
			// Open DB connection (using dblib PDO driver [DBLIB/pdo_dblib.so])
			$conn = new PDO("dblib:host=$server;dbname=$db", "$username", "$password");

			// Open DB connection (using Microsoft's PDO driver [SQLSRV/php_pdo_sqlsrv_xxxxx.dll])
			//$conn = new PDO("sqlsrv:Server=$server;Database=$db", "$username", "$password");
		}
		catch (PDOException $e)     // Error message if unable to make DB connection
		{
			$conn_test = 0;
		}
	}

	// Display test result
	?>

	<div class="header">
		<div class="title">
			Wallet Ticket Downloads
		</div>
		<div class="conninfo"> <?php	// The &#xfe0e below is so that Safari treats the Unicode as text and not
										// an emoji, because it draws emoji as a full color bitmap, which is
										// immune to CSS colors.
			if ($conn_test == 1)
				echo 'Galaxy Connection &nbsp;<span style="color: green; font-weight: bold;">✔&#xfe0e</span>';
			else
				echo 'Galaxy Connection &nbsp;<span class="blink" style="color: red; font-weight: bold;">✖&#xfe0e</span>'; ?>

		</div>
	</div> <?php
}


// Create XML POST Request
function BuildXML ($API_SourceID)
{
    date_default_timezone_set ("America/Los_Angeles");
    $API_TimeStamp = date ("Y-m-d H:i:s");              // Pull current date/time for TimeStamp field

    $XML_Post = '
        <?xml version="1.0"?>
        <Envelope>
            <Header>
                <MessageID>1</MessageID>
                <MessageType>QueryServerStatus</MessageType>
                <SourceID>' . $API_SourceID . '</SourceID>
                <TimeStamp>' . $API_TimeStamp . '</TimeStamp>
            </Header>
            <Body>
                <TestData>123456789</TestData>
            </Body>
        </Envelope>';

    return ($XML_Post);
}


// Create headers for POST request
function SetPOSTHeaders ($XML_Post)
{
    $POST_Headers = array (
    "POST /eGalaxy.asp HTTP/1.1",
    "Host: localhost",
    "Content-Type: text/xml",
    "Content-Length: " . strlen ($XML_Post)
    );

    return ($POST_Headers);
}


// Tests the connection to eGalaxy API
function APIQuery ($SOAP_URL, $POST_Headers, $XML_Post)
{
	$conn_test = 1;

    // Use cURL to POST SOAP to the API */
    $ch = curl_init ();
    curl_setopt ($ch, CURLOPT_URL, $SOAP_URL);
    curl_setopt ($ch, CURLOPT_POST, true);
    curl_setopt ($ch, CURLOPT_POSTFIELDS, $XML_Post);
    curl_setopt ($ch, CURLOPT_HTTPHEADER, $POST_Headers);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt ($ch, CURLOPT_FAILONERROR,true);

    $XML_Response = curl_exec ($ch);

    if (curl_errno ($ch))       // Report any connection errors
		$conn_test = 0;

    curl_close ($ch);

    return ($conn_test);
}


// Shows the settings panel
function ShowSettings ()
{ ?>
	<div class="settings">
		<img src="gfx/Webpage/speaker.png" id="speaker" onclick="ToggleSound();">
	</div> <?php
}


// Change week start/end variables based on the week start day
function WeekStartEnd ($week_start_day)
{
	$week_start_string = 'last ' . $week_start_day . ' midnight';   // this string will be fed to strtotime()

	// Default week ending day is saturday
	$week_end_day = "saturday";

	// If the week start day is monday, change the week end day to sunday
	if ($week_start_day == "monday")
		$week_end_day = "sunday";

	$week_end_string = 'next ' . $week_end_day; // this string will be fed to strtotime()

	return array ($week_start_string, $week_end_string, $week_end_day);
}


// Get the current and previous week date range
function ThisAndLastWeek ($week_start_day, $week_end_day, $week_start_string, $week_end_string)
{
	// Generate the previous week range
	$previous_week = strtotime ("-1 week +1 day");
	$beginning_last_week = strtotime ($week_start_string, $previous_week);  // gives unix time
	$end_last_week = strtotime ($week_end_string, $beginning_last_week);    // gives unix time
	$beginning_last_week = date ("Y-m-d", $beginning_last_week);            // converts to human-readable
	$end_last_week = date ("Y-m-d", $end_last_week);                        // converts to human-readable

	// Generate this week range
	// Re-load week start/end strings (since they're different) for the current week
	$week_start_string = 'last ' . $week_start_day; // this string will be fed to strtotime()
	$week_end_string = 'this ' . $week_end_day;     // this string will be fed to strtotime()

	$beginning_week = strtotime ($week_start_string, strtotime ('tomorrow'));   // gives unix time
	$end_week = strtotime ($week_end_string);           // gives unix time
	$beginning_week = date ("Y-m-d", $beginning_week);  // converts to human-readable
	$end_week = date ("Y-m-d", $end_week);              // converts to human-readable

	return array ($beginning_last_week, $end_last_week, $beginning_week, $end_week);
}


// Parses the log file - takes defined dates, goes through the log, and returns how many downloads fall within those dates
function ParseLog ($today, $yesterday, $beginning_week, $end_week, $beginning_last_week, $end_last_week, $this_month, $last_month, $this_year, $last_year, $logname)
{
	// Initialize all date counters
	$counter = -1;  // -1 to not count the last newline in the file
	$count_today = $count_yesterday = $count_this_week = $count_last_week = $count_this_month = $count_last_month = $count_this_year = $count_last_year = 0;

	if (!$logfile = fopen ($logname, "r"))			// If log file doesn't exist, create it.
	{
		touch ($logname);
		$logfile = fopen ($logname, "r") or die ("Error: Unable to open log file");
	}

	while (!feof ($logfile))						// Run through the file the first time to get all the stats
	{
		$counter++;                                     // A line in the log = a pass served
		$this_line = fgets ($logfile);                  // Grab a line
		$parsed_line = explode (",", $this_line);       // Parse the line, CSV-style
		$just_day = explode (" ", $parsed_line[0]);		// Parse the time out of the date

		// Yeah, I also did this $just_day[0] check as a switch statement, but was too many lines/not worth it
		if ($just_day[0] == $today)						// Check if current line is today's date
			$count_today++;

		if ($just_day[0] == $yesterday)					// Check if the current line is yesterday's date
			$count_yesterday++;

		if ($just_day[0] >= $beginning_week && $just_day[0] <= $end_week)				// Check if the current
			$count_this_week++;															// line is from this week

		if ($just_day[0] >= $beginning_last_week && $just_day[0] <= $end_last_week)		// Check if the current
			$count_last_week++;															// line is from last week

		$just_month = substr ($just_day[0], 0, 7);		// Parse the month out of the date

		if ($just_month == $this_month)					// Check if current line is this month
			$count_this_month++;

		if ($just_month == $last_month)					// Check if current line is last month
			$count_last_month++;

		$just_year = substr ($just_month, 0, 4);		// Parse the year out of the date

		if ($just_year == $this_year)					// Check if current line is this year
			$count_this_year++;

		if ($just_year == $last_year)
			$count_last_year++;

		if ($this_line)									// Remember the last actual (non-EOF) line
			$last_line = $this_line;
	}

	fclose ($logfile);
	$_SESSION['LastLine'] = rtrim ($last_line);			// Remember the last line of the log, stripping newline
														// character (for logstream.php)
	return array ($counter, $count_today, $count_yesterday, $count_this_week, $count_last_week, $count_this_month, $count_last_month, $count_this_year, $count_last_year);
}


// Shows Stats
function ShowStats ($counter, $count_today, $count_yesterday, $count_this_week, $count_last_week, $count_this_month, $count_last_month, $count_this_year, $count_last_year)
{ ?>
	<table class="StatsTable">
		<tr> <?php
			echo '<td class="StatsTD" id="TDAllTime">All-Time<br>' . number_format ($counter) . '</td>'; ?>
		<tr> <?php
			echo '<td class="StatsTD" id="TDToday">Today<br>' . number_format ($count_today) . '</td><td class="StatsTD" id="TDThisWeek">This Week<br>' . number_format ($count_this_week) . '</td><td class="StatsTD" id="TDThisMonth">This Month<br>' . number_format ($count_this_month) . '</td><td class="StatsTD" id="TDThisYear">This Year<br>' . number_format ($count_this_year) . '</td>'; ?>
		</tr>
		<tr> <?php
			echo '<td class="StatsTD">Yesterday<br>' . number_format ($count_yesterday) . '</td><td class="StatsTD">Last Week<br>' . number_format ($count_last_week) . '</td><td class="StatsTD">Last Month<br>' . number_format ($count_last_month) . '</td><td class="StatsTD">Last Year<br>' . number_format ($count_last_year) . '</td>'; ?>
		</tr>
	</table> <?php
}


// Shows the 10 most recent lines of the log, with most recent at the top
function ShowLog ($logname)
{
	// Reverse the last 10 lines of the log into an array - with help from sergio [stack overflow]
	$logfile = fopen ($logname, "r") or die ("Error: Unable to open log file");

	for ($x_pos = 0, $line_count = 0, $reverse_log = array (); fseek ($logfile, $x_pos, SEEK_END) !== -1 && $line_count <= 10; $x_pos--)
	{
		$char = fgetc ($logfile);
		if ($char === "\n")
		{
        	// analyse completed line $output[$ln] if need be
			$line_count++;
			continue;
        }

		$reverse_log[$line_count] = $char . ((array_key_exists ($line_count, $reverse_log)) ? $reverse_log[$line_count] : '');
    }

	fclose ($logfile);

	echo '<table id="log">';				// Print the reversed array
	for ($row = 1; $row <= 10; $row++)		// Generate 10 table rows, empty or not
	{
		if ($reverse_log[$row])
			echo '<tr><td class="LogTD">' . $reverse_log[$row] . '</td></tr>';
		else
			echo '<tr><td class="LogTD">&nbsp;</td></tr>';
	}
	echo '</table>';

	ShowLogLive ();		// Now that we've displayed the last 10 lines, hand it over to the live streamer
}


// Live updates the log via SSE (Server Sent-Events)
function ShowLogLive ()
{ ?>
	<script type="text/javascript" src="js/logstream.js"></script> <?php
}


// Shows the footer
function ShowFooter ($web_stats_link, $logname)
{ ?>
		<div class="footer"> <?php
			echo '<a class="links" href="' . $logname . '">Full log</a>';
			echo '<a class="links" href="' . $web_stats_link . '">AWStats</a>'; ?>

		</div>
	</body>
	</html> <?php
}

?>

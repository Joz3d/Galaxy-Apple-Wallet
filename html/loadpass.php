<?php
/* Receives an Order # and email address, queries the database to verify that the email matches
   the order #, and if so, queries the database for unused tickets from that order, and
   generates links to download Wallet tickets.

by Luke Jozwiak

Last Update: 3 May 2018 */

setlocale (LC_MONETARY, 'en_US');
require '../Outside_Web_Root/config.php';

$GLOBALS['debug'] = 0;

$GLOBALS['API_MessageID'] = 0;

// Initialize the session
session_start ();
$_SESSION['TicketQuantity'] = 0;

StyleHead ();	// Render the head of the web page

// Check that incoming parameters are there (Order # & Email Address), and if so, receive them
list ($OrderID, $Email) = ParamCheck ($_GET["o"], $_GET["e"]);

// Test Galaxy Connection
if ($conn_method == "api")
{
	$XML_Post = BuildXML ("QueryServerStatus", $API_SourceID, $OrderID, "null");
	$POST_Headers = SetPOSTHeaders ($XML_Post);
	$XML_Object = APIQuery ($SOAP_URL, $POST_Headers, $XML_Post);		// Query API for server status

	// For API method, we can pull the time zone from the QueryServerStatus response (connection test response)
	list ($TimeZone, $Today) = ParseXML ($XML_Object, "null", "null", "null", "null", "null");
}
elseif ($conn_method == "db")
{
	$conn = DBTest ($server, $db, $username, $password);				// Test DB Connection
	$conn -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	// Set PDO Error reporting level
}
else
{
    echo '<p><span style="color: Crimson;">Connection Error</span><br>Please ensure that conn_method is set in your config file.</p>';
    TheEnd ();
}

// Authenticate and look up data
if ($conn_method == "api")
{
	$XML_Post = BuildXML ("QueryOrder", $API_SourceID, $OrderID, "null");
	$POST_Headers = SetPOSTHeaders ($XML_Post);
	$XML_Object = APIQuery ($SOAP_URL, $POST_Headers, $XML_Post);		// Query API for order information

	// (For API method, authentication is called from within ParseXML() to keep subsequent functions sane)
	$TicketStack = ParseXML ($XML_Object, $TimeZone, $Today, $Email, "null");

	NoIssuedTicketsCheck ();						// If there aren't any issued tickets on the order, exit

	// Filter out invalid tickets by checking the status of each VID
	$XML_Post = BuildXML ("QueryTicket", $API_SourceID, "null", $TicketStack);
	$POST_Headers = SetPOSTHeaders ($XML_Post);
	$XML_Object = APIQuery ($SOAP_URL, $POST_Headers, $XML_Post);		// Query API for ticket status

	$TicketStack = ParseXML ($XML_Object, $TimeZone, $Today, $Email, $TicketStack);	// Filter out invalid tix
}
elseif ($conn_method == "db")
{
	$GxEmail = DBGetEmail ($conn, $OrderID);		// Query DB to get the email address associated with the order
	$Authorized = Authenticate ($Email, $GxEmail);	// Check email from DB against email provided by user (parameter)

	if ($Authorized == 1)
	{
		list ($TimeZone, $Today) = TimeSetup ($conn);	// Get timezone and date from the DB
		$result = TicketData ($conn, $OrderID);			// Query DB to get ticket data from Order #

		// Go through array of ticket data and process each ticket (row) of data
		$TicketStack = ProcessTickets ($result, $OrderID, $TimeZone, $Today);

		NoIssuedTicketsCheck ();					// If there aren't any issued tickets on the order, exit
	}
}

$TicketStack = Expire ($TicketStack, $Today);	// Filter out Expired Tickets (events that are over)
$TicketStack = StackCheck ($TicketStack);		// Check if $TicketStack still has anything in it
$TicketStack = CheckNames ($TicketStack);		// Check for blank names
WebLink ($TicketStack);							// Place web links

$_SESSION['AllTickets'] = $TicketStack;			// Load Ticket Stack as a session variable to hand off
TheEnd ();


// Renders the head of the web page
function StyleHead ()
{ ?>
	<html>
	<head>
	<meta name="robots" content="noindex, nofollow" />
	<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
	<link rel="shortcut icon" type="image/vnd.microsoft.icon" href="https://www.yourfavicon.ico"/>
	<link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,600" rel="stylesheet">
	<link href="css/font-awesome.min.css" rel="stylesheet">
	<link href="css/theme.css" rel="stylesheet">
	<script type="text/javascript" src="js/loadpass.js"></script>
	<title>Download Wallet Tickets</title>
	</head>

	<body id="awo-tix-wrapper">
	<div id="awo-tix-banner">
		<img src="gfx/Webpage/logo_horizontal.png" title="Absolutely Wonderful Organization" alt="Absolutely Wonderful Organization">
	</div>

	<div id="awo-tix-page">
	  	<div id="awo-tix-logo">
	  		<img src="gfx/Webpage/logo_vertical.png" title="Absolutely Wonderful Organization" alt="Absolutely Wonderful Organization">
	  	</div>

	<?php

	if ($GLOBALS['debug'] == 0) {
		echo '<ul id="awo-tix-list">';
	}

	// FIX: Maintenance code goes here.
    // echo '<p>We\'re sorry, but our Apple Wallet Service is currently down.<br><br>Please try the Apple Wallet link in your confirmation email again soon.</p>';
    // TheEnd ();
}


// Checks if required parameters are missing
function ParamCheck ($OrderID, $Email)
{
	if (!(isset ($OrderID) && isset ($Email)))
	{
		echo '<p>Loading Error<br>Please try the link in your email again.</p>';
		TheEnd ();
	}

	return array ($OrderID, $Email);
}


// Create XML POST Request
function BuildXML ($API_MessageType, $API_SourceID, $OrderID, $TicketStack)
{
	++$GLOBALS['API_MessageID'];
	date_default_timezone_set ("America/Los_Angeles");
	$API_TimeStamp = date ("Y-m-d H:i:s");				// Pull current date/time for TimeStamp field

	$XML_Post_Head = '
<?xml version="1.0"?>
<Envelope>
	<Header>
		<MessageID>' . $GLOBALS['API_MessageID'] . '</MessageID>
		<MessageType>' . $API_MessageType . '</MessageType>
		<SourceID>' . $API_SourceID . '</SourceID>
		<TimeStamp>' . $API_TimeStamp . '</TimeStamp>
	</Header>';

	if ($API_MessageType == "QueryServerStatus")		// Body for QueryServerStatus call
	{
		$XML_Post_Body = '
	<Body>
		<TestData>123456789</TestData>
	</Body>
</Envelope>';
	}
	elseif ($API_MessageType == "QueryOrder")			// Body for QueryOrder call
	{
		$XML_Post_Body = '
	<Body>
		<' . $API_MessageType . '>
			<Query>
				<OrderID>' . $OrderID . '</OrderID>
			</Query>
    	</' . $API_MessageType . '>
	</Body>
</Envelope>';
	}
	elseif ($API_MessageType == "QueryTicket")			// Body for QueryTicket call
	{
		$XML_Post_Body = '
	<Body>
		<' . $API_MessageType . '>
			<Queries>' . "\n";
				foreach ($TicketStack as $index=>$Ticket)
					$XML_Post_Body .= '				<Query><VisualID>' . $Ticket['VisualID']  . '</VisualID></Query>' . "\n";
						$XML_Post_Body .= '		</Queries>
		<DataRequest>
			<Field>Status</Field>
		</DataRequest>
		</' . $API_MessageType . '>
	</Body>
</Envelope>';
	}
    else
    {
			echo '<p><span style="color: Crimson;">Error: Unsupported API Call</span></p>';
            TheEnd ();
    }

	$XML_Post = $XML_Post_Head . $XML_Post_Body;

	 if ($GLOBALS['debug'] == 1)						// Debug: Show XML Request
		{
			echo '<h3>XML Request</h3>';
			echo '<pre>' . htmlentities ($XML_Post, true) . '</pre>';
		}

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
	$ch = curl_init ();										// Use cURL to POST SOAP to the API
	curl_setopt ($ch, CURLOPT_URL, $SOAP_URL);
	curl_setopt ($ch, CURLOPT_POST, true);
	curl_setopt ($ch, CURLOPT_POSTFIELDS, $XML_Post);
	curl_setopt ($ch, CURLOPT_HTTPHEADER, $POST_Headers);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt ($ch, CURLOPT_FAILONERROR,true);

	$XML_Response = curl_exec ($ch);

	if (curl_errno ($ch))									// Report any connection errors
	{
		echo '<p><span style="color: Crimson;">Error connecting to Ticketing System</span></p>';
        if ($GLOBALS['debug'] == 1)
		    echo curl_error ($ch);
		TheEnd ();
	}

	curl_close ($ch);
	$XML_Object = simplexml_load_string ($XML_Response);    // Load response XML into an Object

	 if ($GLOBALS['debug'] == 1)							// Debug: Show XML Response
		{
			echo '<h3>XML Response</h3>';
			echo '<pre>' . htmlentities ($XML_Response, true) . '</pre>';
			echo '<br><hr>';
		}

	return ($XML_Object);
}


// Tests the connection to the database
function DBTest ($server, $db, $username, $password)
{
	try
	{
		// Open DB connection (using dblib PDO driver [DBLIB/pdo_dblib.so])
		$conn = new PDO("dblib:host=$server;dbname=$db", "$username", "$password");

		// Open DB connection (using Microsoft's PDO driver [SQLSRV/php_pdo_sqlsrv_xxxxx.dll])
		//$conn = new PDO("sqlsrv:Server=$server;Database=$db", "$username", "$password");
	}
	catch (PDOException $e)		// Error message if unable to make DB connection
	{
		echo '<p><span style="color: Crimson;">Error connecting to DB</span></p>';
		if ($GLOBALS['debug'] == 1)
			echo $e->getMessage ();
		TheEnd ();
	}

	return ($conn);
}


// Process XML Response into required variables
function ParseXML ($XML_Object, $TimeZone, $Today, $Email, $CheckStack)
{
    if ($XML_Object->Header->MessageType == "QueryServerStatus")
    {
		// Get today's date, and calculate time zone based on local time vs. UTC time
		$Today = substr ($XML_Object->Body->ServerStatus->Computer->LocalTime, 0, 10);
        $LocalTime = strtotime ($XML_Object->Body->ServerStatus->Computer->LocalTime);
        $UTCTime = strtotime ($XML_Object->Body->ServerStatus->Computer->UTCTime);
        $difference = ($LocalTime - $UTCTime) / 3600;

        if ($difference > 0)									// Conform time zone result to required format
            $TimeZone = sprintf ("%02d", $difference) . ':00';
        else
            $TimeZone = sprintf ("%03d", $difference) . ':00';	// Show one more character if negative, as
																// ('-') takes up a slot.
        if ($GLOBALS['debug'] == 1)								// Debug: Show Timezone calculation
		{
			echo '<h3>Time Zone Calculation</h3>';
			echo '<span style="color: blue;">$Today = ' . $Today . '</span><br>';
	        echo 'LocalTime = ' . $LocalTime . '<br>';
	        echo 'UTCTime = ' . $UTCTime . '<br>';
	        echo 'Offset = ' . $difference . '<br>';
	        echo '<span style="color: blue;">$TimeZone = ' . $TimeZone . '</span><br>';
			echo '<br><hr>';
		}

        return array ($TimeZone, $Today);
    }
    elseif ($XML_Object->Header->MessageType == "QueryOrderResponse")
    {
		$GxEmail['Email'] = $XML_Object->Body->QueryOrderResponse->OrderContact->Contact->Email;

		$Authorized = Authenticate ($Email, $GxEmail);	// Check email from API against email provided by
														// user (parameter)
		if ($Authorized)
		{
			// Use XPath to show all Product elements that are decendants of 'Body/QueryOrderResponse/Products'
			// and have an attribute "Type" = "Ticket".  This will pick up all "Ticket" items under Products,
			// whether they are contained in a Package or not.
	        foreach ($XML_Object->xpath('Body/QueryOrderResponse/Products//Product[@Type=\'Ticket\']') as $product)
	        {
	            $Ticket['OrderID'] = (int) $XML_Object->Body->QueryOrderResponse->TransactionData->GalaxyOrderID;

				// If there is a date on it, it's a ticket.  Format the date for the pass.
	            if ($product->EventStartDate)
	            {
	                $Ticket['Type'] = "Ticket";
	                $TourDate = $product->EventStartDate;
	                $Ticket['TourDate'] = FormatDate ($TourDate, $TimeZone);
	            }

				// If there is no date on the ticket, then it's an item.  Keep the date blank, and set the Type
				// to "Item".
	            else
	            {
	                $TourDate = 0;
	                $Ticket['Type'] = "Item";
	                $Ticket['TourDate'] = (string) $product->EventStartDate;        // ('StartDateTime' = blank)
	            }

				$_SESSION['TicketQuantity']++;		// Keep track of the number of tickets (rows) in this order

				// Load the rest of the data from the query
		           $Ticket['Description'] = (string) $product->ItemDescription;
		           $Ticket['VisualID'] = (string) $product->VisualID;
		           $Ticket['Name'] = $product->Guest->FirstName . " " . $product->Guest->LastName;

				if ($GLOBALS['debug'] == 1)                 // Debug: Show data parsed from the query
					ShowTicketData ($Ticket);

				// Put all the ticket/item data in the order into one 2D array called $TicketStack
				// (Each $Ticket will go into $TicketStack as an element).
				// If there is already data in $TicketStack, push the next array onto it.
				if (isset ($TicketStack))	// If there's already data in the stack, push the next array onto it.
					array_push ($TicketStack, $Ticket);
				else		// If not, seed the mother array ($TicketStack) with the current array's data
					$TicketStack[1] = $Ticket;
			}

			if ($GLOBALS['debug'] == 1)         // Debug: Show entire $TicketStack array
				ShowTicketStack ($TicketStack);
		}

		return ($TicketStack);
    }
	elseif ($XML_Object->Header->MessageType == "QueryTicketResponse")
	{
		if ($GLOBALS['debug'] == 1)				// Debug: Show section header
			echo '<h3>Ticket Status Check</h3>';

		$reindex = 0;		// Flag will be set if there are invalid tickets to remove from the stack

		// Iterate through the Ticket Stack, and check every ticket (by Visual ID) against the Query Response
		// to see its status.
		foreach ($CheckStack as $index=>$Ticket)
		{
			if ($XML_Object->xpath('Body/QueryTicketResponse/Products/Product[@VisualID=' . $Ticket['VisualID'] . ']/DataRequestResponse/Status')[0] != 0)
			{
				if ($GLOBALS['debug'] == 1)		// Debug: Show invalid ticket
					echo '<span style="color: red;">' . $Ticket['VisualID'] . ' [' . $Ticket['Type'] . '] : Invalid (status: ' . $XML_Object->xpath('Body/QueryTicketResponse/Products/Product[@VisualID=' . $Ticket['VisualID'] . ']/DataRequestResponse/Status')[0] . ')</span><br>';

				unset ($CheckStack[$index]);
				$reindex = 1;
			}
			else
				if ($GLOBALS['debug'] == 1)		// Debug: Show valid ticket
					echo '<span style="color: green;">' . $Ticket['VisualID'] . ' [' . $Ticket['Type'] . '] : Valid (status: ' . $XML_Object->xpath('Body/QueryTicketResponse/Products/Product[@VisualID=' . $Ticket['VisualID'] . ']/DataRequestResponse/Status')[0] . ')</span><br>';
		}

		if ($reindex == 1)
			// 3-hit combo: Reindex the array (array_values() - which re-indexes it starting at 0), then create a
			// new array of the appropriate length (range() - but start it at 1 to rebuild our 1-based array),
			// and then combine the two arrays into one (array_combine) using the new array for the keys, and the
			// original reindexed array for the values.  Thanks Andrew Moore [stackoverflow].
			$CheckStack = array_combine (range (1, count ($CheckStack)), array_values ($CheckStack));

		if ($GLOBALS['debug'] == 1)				// Debug: Show modified stack
			ShowTicketStack ($CheckStack);

		return ($CheckStack);
	}
	else
	{
		echo '<p><span style="color: Crimson;">Error: Unsupported API Call</span></p>';
		TheEnd ();
	}
}


// Shows Ticket Data (Debug)
function ShowTicketData ($Ticket)
{
	echo '<span style="font-weight: bold;">Ticket Data</span>';
	echo '<p><span style="color: blue;">';
	echo 'Type: ';
	echo $Ticket['Type'] . '<br>';
	echo 'Parsed:<br>';
	echo $Ticket['OrderID'] . '<br>';
	echo $Ticket['Description'] . '<br>';
	echo $Ticket['TourDate'] . '<br>';
	echo $Ticket['VisualID'] . '<br>';
	echo $Ticket['Name'] . '</span></p>';
}


// Shows Ticket Stack (Debug)
function ShowTicketStack ($TicketStack)
{
	echo '<p style="color: blue;">$TicketStack:' . '<br><pre style="color: blue;">';
	print_r ($TicketStack);
	echo '</pre></p><hr>';
}


// Feeds the supplied order # to the DB, and gets/returns the email address associated with it
function DBGetEmail ($conn, $OrderID)
{
	// We're gonna use prepared statements for all of our variable-containing queries to prevent SQL
	// injection.
	$statement = $conn -> prepare ("SELECT Customer.Email

									FROM Orders
									INNER JOIN CustContacts Customer
									ON Orders.ContactID = Customer.CustContactID

									WHERE Orders.OrderID = :OrderID");

	if ($GLOBALS['debug'] == 1)		// Debug: Show SQL query
	{
		echo '<p><em>';
		var_dump ($statement);
		echo '</em></p><p><span style="color: blue;">' . '$OrderID = ' . $OrderID, '</span></p>';
	}

	try								// Execute the query
	{
		$statement -> execute (array ('OrderID' => $OrderID));
	}

	catch (PDOException $e)			// Error message if unable to make the query
	{
		echo '<p><span style="color: Crimson;">Error querying the DB</span></p>';
		if ($GLOBALS['debug'] == 1)
			echo $e->getMessage ();
		TheEnd ();
	}

	$GxEmail = $statement -> fetch ();

	if ($GLOBALS['debug'] == 1)		// Debug: Show query result
	{
		echo '<p>';
		print_r ($GxEmail);
		echo '</p>';
	}

	return ($GxEmail);
}


// Compares email address provided with email address associated with the order
function Authenticate ($Email, $GxEmail)
{
	if ($GLOBALS['debug'] == 1)
		echo '<h3>Authorization Check</h3>';
	if ($Email == $GxEmail['Email'])
	{
		$Authorized = 1;

		if ($GLOBALS['debug'] == 1)
			echo '<p><span style="color: green;">Authorized!</span></p><hr>';
	}
	else
	{
		$Authorized = 0;

		if ($GLOBALS['debug'] == 1)
			echo '<p><span style="color: red;">Rejected.</span></p>';

		echo '<p>Authentication Error.<br>Please try the link in your email again.</p>';
		TheEnd ();
	}

	return ($Authorized);
}


// Gets the DB's timezone and current date
function TimeSetup ($conn)
{
	$sql = "SELECT SYSDATETIMEOFFSET()";	// Query DB to get system date time offset (time zone)

	if ($GLOBALS['debug'] == 1)				// Debug: Show SQL query
		echo '<p><em>' . $sql . '</em></p>';

	foreach ($conn->query ($sql) as $row)	// Load SQL results into an array
	{
		if ($GLOBALS['debug'] == 1)
			print_r ($row);
	}

	// Load the time zone into a variable, trimming to what we need
	$TimeZone = substr ($row[0], -6);

	// Get today's date alone so that we don't present tickets from the past.
	$Today = substr ($row[0], 0, 10);

	if ($GLOBALS['debug'] == 1)				// Debug: Print $TimeZone variable
		echo '<p><span style="color: blue;">' . '$TimeZone = ' . $TimeZone . '<br>$Today = ' . $Today . '</span></p><hr>';

	return array ($TimeZone, $Today);
}


// Queries the DB and returns ticket details
function TicketData ($conn, $OrderID)
{
	$statement = $conn -> prepare ("SELECT	Ticket.OrderNo,
											Item.Name,
											Event.StartDateTime,
											Ticket.VisualID,
											Customer.FirstName,
											Customer.LastName

											FROM Tickets Ticket
											FULL JOIN CustContacts Customer
											ON Ticket.ContactID = Customer.CustContactID
											FULL JOIN RMEvents Event
											ON Ticket.EventNo = Event.EventID
											INNER JOIN Items Item
											ON Ticket.PLU = Item.PLU

											WHERE Ticket.OrderNo = :OrderID and Ticket.Status = 0");

	if ($GLOBALS['debug'] == 1)		// Debug: Show SQL query
	{
		echo '<p><em>';
		var_dump ($statement);
		echo '</em></p>';
	}

	try								// Execute the query
	{
		$statement -> execute (array ('OrderID' => $OrderID));
	}
	catch (PDOException $e)			// Error message if unable to make the query
	{
		echo '<p><span style="color: Crimson;">Error querying the DB</span></p>';
		if ($GLOBALS['debug'] == 1)
			echo $e->getMessage ();
		TheEnd ();
	}

	return ($statement);
}


// Goes through the array of ticket data and processes each ticket (row of data)
function ProcessTickets ($result, $OrderID, $TimeZone, $Today)
{
	while ($row = $result -> fetch ())
	{
		$_SESSION['TicketQuantity']++;	// Keep track of the number of tickets (rows) in this order

		if ($GLOBALS['debug'] == 1)		// Debug: Show SQL results
			print_r ($row);

		// Load data from query (current row result) into variables
		list ($Ticket, $TourDate) = ParseData ($row, $OrderID, $TimeZone);

		// Put all the ticket/item data in the order into one 2D array called $TicketStack
		// (Each $Ticket will go into $TicketStack as an element).
		// If there is already data in $TicketStack, push the next array onto it.
		if (isset ($TicketStack))	// If there's already data in the stack, push the next array onto it.
			array_push ($TicketStack, $Ticket);
		else		// If not, seed the mother array ($TicketStack) with the current array's data
			$TicketStack[1] = $Ticket;
	}

    if ($GLOBALS['debug'] == 1)			// Debug: Show entire $TicketStack array
		ShowTicketStack ($TicketStack);

	return ($TicketStack);
}


// Puts current row of ticket data into variables
function ParseData ($row, $OrderID, $TimeZone)
{
	$Ticket['OrderID'] = $OrderID;

	// If there is a date on it, it's a ticket.  Format the date for the pass.
	if ($row['StartDateTime'])
	{
		$Ticket['Type'] = "Ticket";

		$TourDate = $row['StartDateTime'];		// Get the date from the DB query
		$Ticket['TourDate'] = FormatDate ($TourDate, $TimeZone);
	}

	// If there is no date on the ticket, then it's an item.  Keep the date blank, and set the Type to "Item"
	else
	{
		$TourDate = 0;
		$Ticket['Type'] = "Item";
		$Ticket['TourDate'] = $row['StartDateTime'];		// ('StartDateTime' = blank)
	}

	// Load the rest of the data from the query
	$Ticket['Description'] = $row['Name'];
	$Ticket['VisualID'] = $row['VisualID'];
	$Ticket['Name'] = $row['FirstName'] . " " . $row['LastName'];

	if ($GLOBALS['debug'] == 1)					// Debug: Show data parsed from the query
		ShowTicketData ($Ticket);

	return array ($Ticket, $TourDate);
}


// Formats the date to Wallet spec
function FormatDate ($TourDate, $TimeZone)
{
	$DateSplit = explode (' ', $TourDate);				// Split the date string into the date and time
	$TourDate = $DateSplit[0];
	$TourTime = $DateSplit[1];

	$TourTime = substr ($TourTime, 0, 5);				// Trim down the time

	return ($TourDate . 'T' . $TourTime . $TimeZone);	// Re-combine into one string
}


// Exits if there aren't any issued tickets on the order
function NoIssuedTicketsCheck ()
{
	if ($_SESSION['TicketQuantity'] == 0)
	{
		echo '<p>No e-Tickets Available.<br>The tickets on this order may be for pickup.</p>';
		TheEnd ();
	}
}


// Prunes exipred tickets from the Ticket Stack
function Expire ($TicketStack, $Today)
{
	if ($GLOBALS['debug'] == 1)							// Debug: Show section header
		echo '<h3>Ticket Expiration Check</h3>';

	$reindex = 0;
	foreach ($TicketStack as $index=>$Ticket)
	{
		if ($Ticket['Type'] == "Ticket")
		{
			if ($Ticket['TourDate'] < $Today)	// If the ticket is in the past, remove it from the Stack
			{
				if ($GLOBALS['debug'] == 1)             // Debug: Show expired ticket
					echo '<span style="color: red;">' . $Ticket['VisualID'] . ' [' . $Ticket['Type'] . '] : Expired (date: ' . $Ticket['TourDate'] . ') - Pruning</span><br>';

				unset ($TicketStack[$index]);
				$reindex = 1;
			}
			else
				if ($GLOBALS['debug'] == 1)             // Debug: Show valid ticket
					echo '<span style="color: green;">' . $Ticket['VisualID'] . ' [' . $Ticket['Type'] . '] : Valid (status: ' . $Ticket['TourDate'] . ')</span><br>';
		}
	}

	if ($reindex == 1)		// Reindex the Stack if there were deletions.  See '3-hit combo' for details.
		$TicketStack = array_combine (range (1, count ($TicketStack)), array_values ($TicketStack));

	if ($GLOBALS['debug'] == 1)							// Debug: Show stack
		ShowTicketStack ($TicketStack);

	return ($TicketStack);
}


// Checks the stack (and shows it in debug)  If it's empty, exits.
function StackCheck ($TicketStack)
{
	if ($GLOBALS['debug'] == 1)		// Debug: Show section header
		echo '<h3>Stack Check</h3>';

	if (empty($TicketStack))
	{
		echo '<p>Sorry, there are no Apple Wallet tickets available on this order.</p>';

		if ($GLOBALS['debug'] == 1)
			echo '<span style="color: red;">$TicketStack is empty</span><br>';

		TheEnd ();
	}
	else
		if ($GLOBALS['debug'] == 1)
			echo '<span style="color: green;">$TicketStack is not empty</span><br>';

	if ($GLOBALS['debug'] == 1)		// Debug: Show section header
		ShowTicketStack ($TicketStack);

	return ($TicketStack);
}


// Iterates through Ticket Stack and creates web links (to pass generation)
function WebLink ($TicketStack)
{
	if ($GLOBALS['debug'] == 0)
	{
		foreach ($TicketStack as $index=>$Ticket)
		{
			if ($Ticket['Type'] == "Ticket")
				echo '<li id="PassLink' . $index . '"><a href="getpass.php?n=' . $index . '" onClick="DisableLink(' . $index . ');">Ticket for <strong>' . $Ticket['Name'] . '</strong></a></li>';
			elseif ($Ticket['Type'] == "Item")
				echo '<li id="PassLink' . $index . '"><a href="getpass.php?n=' . $index . '" onClick="DisableLink(' . $index . ');">Voucher for <strong>' . $Ticket['Description'] . '</strong></li></a>';
		}
	}
}


// Ends the web page
function TheEnd ()
{
	if ($GLOBALS['debug'] == 0) {
		echo '</ul>';
	}

	echo '</div>';
	include 'footer.php';
	exit ();
}

?>

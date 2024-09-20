<?php
/* Receives an Order # and email address, queries the database to verify that the email matches
   the order #, and if so, queries the database for unused tickets from that order, and
   generates links to download Wallet tickets.

by Luke Jozwiak

Last Update: 27 Mar 2017 */

setlocale (LC_MONETARY, 'en_US');
require 'Outside_Web_Root/config.php';

$debug = 0;
?>

<html>
<head>
<meta name="robots" content="noindex, nofollow" />
<link rel="shortcut icon" type="image/vnd.microsoft.icon" href="https://www.yourfavicon.ico"/>
<title>Download Wallet Tickets</title>

<style>
body
{
    font-size: 180%;	/* Responsive: Tablets & Desktops */
	width: 735px;
	margin-left: auto;
	margin-right: auto;
	text-align: center;
}

p
{
	font-size: 0.875em; /* If the baseline is 16px, this will scale the font to 14px */
	line-height: 1.7em; /* This keeps the line height proportional to the font size */
}

@media only screen and (max-device-width: 667px)	/* Responsive: Smartphones */
{
    body
    {
        font-size: 400%;
		width: 100%;
    }
}
</style>
</head>

<body>
<picture>
	<source srcset="gfx/Webpage/logo_vertical.png" media="(max-device-width: 667px)">
	<source srcset="gfx/Webpage/logo_horizontal.png">
	<img src="gfx/Webpage/logo_vertical.png" alt="Logo Vertical" style="width: auto;">
</picture>

<p>


<?php
$Authorized = 0;
session_start ();
$_SESSION ['TicketQuantity'] = 0;

// Check that incoming parameters are there (Order # & Email Address), and if so, receive them.
if (isset ($_GET ["o"]) && isset ($_GET ["e"]))
{
	$OrderID = $_GET ["o"];
	$Email = $_GET ["e"];
}
else
{
	echo "Loading Error<br>Please try the link in your email again.";
	include 'footer.php';
	exit ();
}


// Open DB connection (using Microsoft's PDO driver [SQLSRV32/php_pdo_sqlsrv_56_ts.dll] against PHP 5.6)
$conn = new PDO("sqlsrv:Server=$server;Database=$db", "$username", "$password");
$conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );


// AUTHENTICATION

// Query DB to get the email address associated with the order
$sql = "SELECT  Customer.Email

		FROM Orders
		INNER JOIN CustContacts Customer
		ON Orders.ContactID = Customer.CustContactID

		WHERE Orders.OrderID = $OrderID";

// Debug: Show SQL query
if ($debug == 1)
	echo "<em>", $sql, "</em><br><br>";

// Load SQL results into an array
foreach ($conn->query ($sql) as $row)
{
	if ($debug == 1)
	{
		print_r ($row);
		echo "<br><br>";
	}
}

// Authentication Test: Compare email address submitted against the DB's email address for this order
if ($Email == $row ['Email'])
{
	$Authorized = 1;

	if ($debug == 1)
		echo "<span style=\"color: green;\">Authorized!</span></p><hr><p>";
}
else
{
	if ($debug == 1)
		echo "<span style=\"color: red;\">Rejected.</span><br><br>";

	echo "Authentication Error.<br>Please try the link in your email again.";
	include 'footer.php';
	exit ();
}


if ($Authorized = 1)
{
	// Query DB to get system date time offset (time zone)
	$sql = "SELECT	SYSDATETIMEOFFSET()";

	// Debug: Show SQL query
	if ($debug == 1)
		echo "<em>", $sql, "</em><br><br>";

	// Load SQL results into an array
	foreach ($conn->query ($sql) as $row)
	{
		if ($debug == 1)
			print_r ($row);
	}

	// Load the time zone into a variable, trimming to what we need
	$TimeZone = substr ($row [0], -6);

	// Get today's date alone so that we don't present tickets from the past.
	$Today = substr ($row [0], 0, 10);

	// Debug: Print $TimeZone variable
	if ($debug == 1)
		echo "<br><br><span style=\"color: blue;\">", "\$TimeZone = ", $TimeZone, "</span></p><hr><p>";


	// RETRIEVAL OF TICKET DATA

	// Query DB to get ticket data from Order #
	$sql = "SELECT	Ticket.OrderNo,
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

			WHERE Ticket.OrderNo = $OrderID and Ticket.Status = 0";

	// Debug: Show SQL query
	if ($debug == 1)
		echo "<em>", $sql, "</em><br><br>";

	// Load SQL results into an array
	foreach ($conn->query ($sql) as $row)
	{
		$_SESSION ['TicketQuantity']++;	// Keep track of the number of tickets (rows) in this order.

		// Debug: Show SQL results
		if ($debug == 1)
			print_r ($row);

		// Load data from query (current row result) into variables
		$Ticket ['OrderID'] = $OrderID;

		// If there is a date on it, it's a ticket.  Format the date for the pass.
		if ($row ['StartDateTime'])
		{
			$Ticket ['Type'] = "Ticket";

			// Get the date from the DB query
			$TourDate = $row ['StartDateTime'];

			// Split the date string into the date and time
			$DateSplit = explode (' ', $TourDate);
			$TourDate = $DateSplit [0];
			$TourTime = $DateSplit [1];

			// Trim down the time
			$TourTime = substr ($TourTime, 0, 5);

			// Re-combine into one string
			$Ticket ['TourDate'] = $TourDate . 'T' . $TourTime . $TimeZone;
		}

		// If there is no date on the ticket, then it's an item.  Keep the date blank, and set the Type to "Item"
		else
		{
			$Ticket ['Type'] = "Item";
			$Ticket ['TourDate'] = $row ['StartDateTime'];		// ('StartDateTime' = blank)
		}

		// Load the rest of the data from the query
		$Ticket ['Description'] = $row ['Name'];
		$Ticket ['VisualID'] = $row ['VisualID'];
		$Ticket ['Name'] = $row ['FirstName'] . " " . $row ['LastName'];

		// Debug: Show data parsed from the query
		if ($debug == 1)
		{
			echo "<br><br><span style=\"color: blue;\">";
			echo "Type: ";
			echo $Ticket ['Type'], "<br>";
			echo "Parsed:<br>";
			echo $Ticket ['OrderID'], "<br>";
			echo $Ticket ['Description'], "<br>";
			echo $Ticket ['TourDate'], "<br>";
			echo $Ticket ['VisualID'], "<br>";
			echo $Ticket ['Name'], "<br><br></span>";
		}


		// Create webpage link to generate the pass for the current ticket/item.
		if ($debug == 0)
		{
			if ($Ticket ['Type'] == "Ticket")
			{
				if ($TourDate >= $Today)	// Only present ticket if it's for today's date or later.
					echo "<a href=\"getpass.php?n=", $_SESSION ['TicketQuantity'], "\">Ticket for ", $Ticket ['Name'], "</a>";
				else
					echo "Ticket for ", $Ticket ['Name'], " has expired";
			}
			elseif ($Ticket ['Type'] == "Item")		// Vouchers don't have dates, so they will show as long as unredeemed.
				echo "<a href=\"getpass.php?n=", $_SESSION ['TicketQuantity'], "\">Voucher for ", $Ticket ['Description'], "</a>";

			echo "<br>";
		}


		/* We're gonna put all the ticket/item data in the order into one 2D array called $TicketStack.
		   (Each $Ticket will go into $TicketStack as an element) */

		// If there is already data in $TicketStack, push the next array onto it.
		if (isset ($TicketStack))
			array_push ($TicketStack, $Ticket);

		// If not, seed the mother array ($TicketStack) with the current array's data.
		else
			$TicketStack [1] = $Ticket;
	}


	// Copy all tickets from this order ($TicketStack) to the session variable ('AllTickets').
	$_SESSION ['AllTickets'] = $TicketStack;

	// Debug: Show entire $TicketStack/['AllTickets'] array
	if ($debug == 1)
	{
		echo "</p><hr><p><span style=\"color: blue;\">\$AllTickets:", "<br>";
		print_r ($_SESSION ['AllTickets']);
		echo "</span>";
	}
}

include 'footer.php';
exit;
?>

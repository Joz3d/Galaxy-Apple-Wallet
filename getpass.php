<?php
/* Receives requested ticket info, validates it, and returns a PKPass :)

by Luke Jozwiak

Last Update: 20 Mar 2017 */

setlocale (LC_MONETARY, 'en_US');
require 'Outside_Web_Root/config.php';
require 'PKPass.php';
use PKPass\PKPass;

session_start ();

$debug = 0;


// DATA RECEPTION & VERIFICATION

// Check that incoming parameter is there (ticket # request), and if so, receive it.
if (isset ($_GET ["n"]))
	$TicketNumber = $_GET ["n"];
else
	exit ("Loading Error<br>Please try the link in your email again.");

// Verify $TicketNumber to be in range
if ($TicketNumber < 1 or $TicketNumber > $_SESSION ['TicketQuantity'])
	exit ("No such item exists.<br>Please try the link in your email again.");

// Debug: Show requested ticket
if ($debug == 1)
{
	echo "Requested Ticket:<br>&nbsp;&nbsp;&nbsp;&nbsp;";
	print_r ($_SESSION ['AllTickets'] [$TicketNumber]);
}

// Exit if any of the required variables aren't set
if (!isset($_SESSION ['AllTickets'] [$TicketNumber] ['OrderID']) || !isset($_SESSION ['AllTickets'] [$TicketNumber] ['Description']) || !isset($_SESSION ['AllTickets'] [$TicketNumber] ['VisualID']))
	exit ("Bad Data<br>Please try the link in your email again.");

// Tickets have additional variables to check for
if ($_SESSION ['AllTickets'] [$TicketNumber] ['Type'] == "Ticket")
	if (!isset($_SESSION ['AllTickets'] [$TicketNumber] ['TourDate']) || !isset($_SESSION ['AllTickets'] [$TicketNumber] ['Name']))
		exit ("Bad Data<br>Please try the link in your email again.");


// PASS PREPERATION

// Figure out which graphics to use (external function in config.php)
list ($foregroundColor, $backgroundColor, $labelColor, $GraphicsPath) = SetGraphics ($_SESSION ['AllTickets'] [$TicketNumber]  ['Description']);

// Debug: Show all other pass parameters
if ($debug == 1)
{
	echo "<br>";
	echo "<br>\$OrgName = ", $OrgName;
	echo "<br>\$TicketDescription = ", $TicketDescription;
	echo "<br>\$ItemDescription = ", $ItemDescription;

	echo "<br>\$TicketLongitude = ", $TicketLongitude;
	echo "<br>\$TicketLatitude = ", $TicketLatitude;
	echo "<br>\$ItemLongitude = ", $ItemLongitude;
	echo "<br>\$ItemLatitude = ", $ItemLatitude;

	echo "<br>\$Website = ", $Website;
	echo "<br>\$CustomerServiceNumber = ", $CustomerServiceNumber;

	echo "<br>\$TermsConditions = ", $TermsConditions;

	echo "<br>\$foregroundColor = ", $foregroundColor;
	echo "<br>\$backgroundColor = ", $backgroundColor;
	echo "<br>\$labelColor = ", $labelColor;
	echo "<br>\$GraphicsPath = ", $GraphicsPath;
}

// PASS GENERATION

// Declare a new pass and set certificate parameters
$pass = new PKPass();

$pass->setCertificate ($CertLocPass);			// Path to your Pass certificate (.p12 file)
$pass->setCertificatePassword ($AppleCertPass);	// Password for certificate
$pass->setWWDRcertPath ($CertLocWWDR);			// Path to WWDR certificate


// 2 Different types of passes: Tickets and Items

// JSON Pass for a Ticket
if ($_SESSION ['AllTickets'] [$TicketNumber] ['Type'] == "Ticket")
{
	$pass->setJSON ('
	{
		"formatVersion": 1,
		"passTypeIdentifier": "' . $PassTypeID . '",
		"teamIdentifier": "' . $TeamID . '",
		"serialNumber": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['VisualID'] . '",

		"locations":
		[
			{
				"longitude": ' . $TicketLongitude . ',
				"latitude": ' . $TicketLatitude . '
			}
		],

		"relevantDate": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['TourDate'] . '",

		"barcode":
		{
	        "format": "PKBarcodeFormatPDF417",
	        "message": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['VisualID'] . '",
	        "messageEncoding": "iso-8859-1"
	    },

		"organizationName": "' . $OrgName . '",
		"description": "' . $TicketDescription . '",

		"foregroundColor": "' . $foregroundColor . '",
	    "backgroundColor": "' . $backgroundColor . '",
		"labelColor": "' . $labelColor . '",


		"eventTicket": {
	        "secondaryFields":
	        [
	            {
	                "key": "event",
	                "label": "Type",
	                "value": "' . $_SESSION ['AllTickets'] [$TicketNumber]  ['Description'] . '"
	            },

	            {
	            	"dateStyle": "PKDateStyleMedium",
	                "key": "tour-date",
	                "label": "Time",
	                "timeStyle": "PKDateStyleShort",
	                "value": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['TourDate'] . '"
	            }
	        ],

			"auxiliaryFields":
			[
				{
					"key": "Name",
					"label": "Name",
					"value": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['Name'] . '"
				},

				{
					"key": "OrderID",
					"label": "Order #",
					"value": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['OrderID'] . '"
				}
			],

	        "backFields":
	        [
	            {
	                "key": "website",
	                "label": "Website",
	                "value": "' . $Website . '"
	            },

	            {
					"key": "customer-service",
					"label": "Customer Service",
					"value": "' . $CustomerServiceNumber . '"
				},

				{
					"key": "terms",
					"label": "Terms and Conditions Apply",
					"value": "' . $TermsConditions . '"
				}
	        ]
	    }
	}
	');
}

// JSON Pass for an Item
if ($_SESSION ['AllTickets'] [$TicketNumber] ['Type'] == "Item")
{
	$pass->setJSON ('
	{
		"formatVersion": 1,
		"passTypeIdentifier": "' . $PassTypeID . '",
		"teamIdentifier": "' . $TeamID . '",
		"serialNumber": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['VisualID'] . '",

		"locations":
		[
			{
				"longitude": ' . $ItemLongitude . ',
				"latitude": ' . $ItemLatitude . '
			}
		],

		"barcode":
		{
	        "format": "PKBarcodeFormatPDF417",
	        "message": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['VisualID'] . '",
	        "messageEncoding": "iso-8859-1"
		},

		"organizationName": "' . $OrgName . '",
		"description": "' . $ItemDescription . '",

		"foregroundColor": "' . $foregroundColor . '",
		"backgroundColor": "' . $backgroundColor . '",
		"labelColor": "' . $labelColor . '",


		"coupon":
		{
			"secondaryFields":
			[
				{
					"key": "event",
					"label": "Item",
					"value": "' . $_SESSION ['AllTickets'] [$TicketNumber]  ['Description'] . '"
				},

				{
					"key": "OrderID",
					"label": "Order #",
					"value": "' . $_SESSION ['AllTickets'] [$TicketNumber] ['OrderID'] . '"
				}
			],

	        "backFields":
	        [
	            {
	                "key": "website",
	                "label": "Website",
	                "value": "' . $Website . '"
	            },

	            {
					"key": "customer-service",
					"label": "Customer Service",
					"value": "' . $CustomerServiceNumber . '"
				}
	        ]
	    }
	}
	');
}

// Add resources to the PKPass package
$pass->addFile ($GraphicsPath . 'icon.png');
$pass->addFile ($GraphicsPath . 'icon@2x.png');
$pass->addFile ($GraphicsPath . 'icon@3x.png');
$pass->addFile ($GraphicsPath . 'logo.png');
$pass->addFile ($GraphicsPath . 'logo@2x.png');
$pass->addFile ($GraphicsPath . 'logo@3xpng');
$pass->addFile ($GraphicsPath . 'strip.png');
$pass->addFile ($GraphicsPath . 'strip@2x.png');
$pass->addFile ($GraphicsPath . 'strip@3x.png');


// Create and output the PKPass
if ($debug == 0)
{
	if (!$pass->create (true))
		echo 'Error: ' . $pass->getError();
}

exit;
?>
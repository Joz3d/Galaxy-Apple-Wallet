<?php
// Receives requested ticket info, validates it, and returns a PKPass :)
// by Luke Jozwiak
// Last Update: 10 Apr 2018

setlocale (LC_MONETARY, 'en_US');
require '../Outside_Web_Root/config.php';
require 'PKPass.php';
use PKPass\PKPass;

session_start ();

$debug = 0;


// *************** SETUP ***************
// Verify that required data (Ticket download number, session variables) are properly set
$TicketNumber = DataSanity ($_GET["n"], $debug);

// Figure out which graphics to use (external function in config.php)
list ($foregroundColor, $backgroundColor, $labelColor, $GraphicsPath) = SetGraphics ($_SESSION['AllTickets'][$TicketNumber]['Description']);

// Debug: Show all pass parameters
if ($debug == 1)
	ShowParameters ($foregroundColor, $backgroundColor, $labelColor, $GraphicsPath, $OrgName, $TicketDescription, $ItemDescription, $TicketLongitude, $TicketLatitude, $ItemLongitude, $ItemLatitude, $Website, $CustomerServiceNumber, $TermsConditions, $CertLocPass, $CertLocWWDR);


// *************** PASS GENERATION ***************
// Start a new pass and set certificate parameters
$pass = NewPass ($CertLocPass, $AppleCertPass, $CertLocWWDR);

// 2 Different types of passes: Tickets and Items
if ($_SESSION['AllTickets'][$TicketNumber]['Type'] == "Ticket")		// Set the JSON for a Ticket
	TicketJSON ($pass, $TicketNumber,$PassTypeID, $TeamID, $TicketLongitude, $TicketLatitude, $OrgName, $TicketDescription, $foregroundColor, $backgroundColor, $labelColor, $Website, $CustomerServiceNumber, $TermsConditions);

if ($_SESSION['AllTickets'][$TicketNumber]['Type'] == "Item")		// Set the JSON for an Item
	ItemJSON ($pass, $TicketNumber, $PassTypeID, $TeamID, $ItemLongitude, $ItemLatitude, $OrgName, $ItemDescription, $foregroundColor, $backgroundColor, $labelColor, $Website, $CustomerServiceNumber);

AddResources ($pass, $GraphicsPath);	// Add resources to the PKPass package

if ($debug == 0)	// Create and output the PKPass
	if (!$pass->create (true))
		echo 'Error: ' . $pass->getError();

LogDownload ($TicketNumber, $logname);	// Log the Download
exit ();


// Checks if the required data (Ticket download number, session variables) are properly set
function DataSanity ($TicketNumber, $debug)
{
	if (!(isset ($TicketNumber)))
		exit ("Loading Error<br>Please try the link in your email again.");

	// Verify $TicketNumber to be in range
	if ($TicketNumber < 1 or $TicketNumber > $_SESSION['TicketQuantity'])
		exit ("No such item exists.<br>Please try the link in your email again.");

	if ($debug == 1)	// Debug: Show requested ticket
	{
		echo 'Requested Ticket:<br>&nbsp;&nbsp;&nbsp;&nbsp;';
		print_r ($_SESSION['AllTickets'][$TicketNumber]);
	}

	// Exit if any of the required session variables aren't set
	if (!isset($_SESSION['AllTickets'][$TicketNumber]['OrderID']) || !isset($_SESSION['AllTickets'][$TicketNumber]['Description']) || !isset($_SESSION['AllTickets'][$TicketNumber]['VisualID']))
		exit ("Bad Data<br>Please try the link in your email again.");

	// Tickets have additional session variables to check for
	if ($_SESSION['AllTickets'][$TicketNumber]['Type'] == "Ticket")
		if (!isset($_SESSION['AllTickets'][$TicketNumber]['TourDate']) || !isset($_SESSION['AllTickets'][$TicketNumber]['Name']))
			exit ("Bad Data<br>Please try the link in your email again.");

	return $TicketNumber;
}


// Shows all parameters from config.php relevant to JSON creation
function ShowParameters ($foregroundColor, $backgroundColor, $labelColor, $GraphicsPath, $OrgName, $TicketDescription, $ItemDescription, $TicketLongitude, $TicketLatitude, $ItemLongitude, $ItemLatitude, $Website, $CustomerServiceNumber, $TermsConditions, $CertLocPass, $CertLocWWDR)
{
	echo '<br>';
	echo '<br>$OrgName = ' . $OrgName;
	echo '<br>$TicketDescription = ' . $TicketDescription;
	echo '<br>$ItemDescription = ' . $ItemDescription;

	echo '<br>$TicketLongitude = ' . $TicketLongitude;
	echo '<br>$TicketLatitude = ' . $TicketLatitude;
	echo '<br>$ItemLongitude = ' . $ItemLongitude;
	echo '<br>$ItemLatitude = ' . $ItemLatitude;

	echo '<br>$Website = ' . $Website;
	echo '<br>$CustomerServiceNumber = ' . $CustomerServiceNumber;

	echo '<br>$TermsConditions = ' . $TermsConditions;

	echo '<br>$foregroundColor = ' . $foregroundColor;
	echo '<br>$backgroundColor = ' . $backgroundColor;
	echo '<br>$labelColor = ' . $labelColor;

	echo '<br>$GraphicsPath = ' . $GraphicsPath;
	echo '<br>$CertLocPass = ' . $CertLocPass;
	echo '<br>$CertLocWWDR = ' . $CertLocWWDR;
}


// Starts creating a new pass, sets certificate parameters
function NewPass ($CertLocPass, $AppleCertPass, $CertLocWWDR)
{
	$pass = new PKPass();

	$pass->setCertificate ($CertLocPass);			// Path to your Pass certificate (.p12 file)
	$pass->setCertificatePassword ($AppleCertPass);	// Password for certificate
	$pass->setWWDRcertPath ($CertLocWWDR);			// Path to WWDR certificate

	return ($pass);
}


// Sets JSON for a Ticket
function TicketJSON ($pass, $TicketNumber, $PassTypeID, $TeamID, $TicketLongitude, $TicketLatitude, $OrgName, $TicketDescription, $foregroundColor, $backgroundColor, $labelColor, $Website, $CustomerServiceNumber, $TermsConditions)
{
	$pass->setJSON ('
	{
		"formatVersion": 1,
		"passTypeIdentifier": "' . $PassTypeID . '",
		"teamIdentifier": "' . $TeamID . '",
		"serialNumber": "' . $_SESSION['AllTickets'][$TicketNumber]['VisualID'] . '",

		"locations":
		[
			{
				"longitude": ' . $TicketLongitude . ',
				"latitude": ' . $TicketLatitude . '
			}
		],

		"relevantDate": "' . $_SESSION['AllTickets'][$TicketNumber]['TourDate'] . '",

		"barcode":
		{
	        "format": "PKBarcodeFormatPDF417",
	        "message": "' . $_SESSION['AllTickets'][$TicketNumber]['VisualID'] . '",
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
	                "value": "' . $_SESSION['AllTickets'][$TicketNumber]['Description'] . '"
	            },

	            {
	            	"dateStyle": "PKDateStyleMedium",
	                "key": "tour-date",
	                "label": "Time",
	                "timeStyle": "PKDateStyleShort",
	                "value": "' . $_SESSION['AllTickets'][$TicketNumber]['TourDate'] . '"
	            }
	        ],

			"auxiliaryFields":
			[
				{
					"key": "Name",
					"label": "Name",
					"value": "' . $_SESSION['AllTickets'][$TicketNumber]['Name'] . '"
				},

				{
					"key": "OrderID",
					"label": "Order #",
					"value": "' . $_SESSION['AllTickets'][$TicketNumber]['OrderID'] . '"
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


// Sets JSON for an Item
function ItemJSON ($pass, $TicketNumber, $PassTypeID, $TeamID, $ItemLongitude, $ItemLatitude, $OrgName, $ItemDescription, $foregroundColor, $backgroundColor, $labelColor, $Website, $CustomerServiceNumber)
{
	$pass->setJSON ('
	{
		"formatVersion": 1,
		"passTypeIdentifier": "' . $PassTypeID . '",
		"teamIdentifier": "' . $TeamID . '",
		"serialNumber": "' . $_SESSION['AllTickets'][$TicketNumber]['VisualID'] . '",

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
	        "message": "' . $_SESSION['AllTickets'][$TicketNumber]['VisualID'] . '",
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
					"value": "' . $_SESSION['AllTickets'][$TicketNumber]['Description'] . '"
				},

				{
					"key": "OrderID",
					"label": "Order #",
					"value": "' . $_SESSION['AllTickets'][$TicketNumber]['OrderID'] . '"
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


// Sets which graphics to use for the pass
function AddResources ($pass, $GraphicsPath)
{
	$pass->addFile ($GraphicsPath . 'icon.png');
	$pass->addFile ($GraphicsPath . 'icon@2x.png');
	$pass->addFile ($GraphicsPath . 'icon@3x.png');
	$pass->addFile ($GraphicsPath . 'logo.png');
	$pass->addFile ($GraphicsPath . 'logo@2x.png');
	$pass->addFile ($GraphicsPath . 'logo@3xpng');
	$pass->addFile ($GraphicsPath . 'strip.png');
	$pass->addFile ($GraphicsPath . 'strip@2x.png');
	$pass->addFile ($GraphicsPath . 'strip@3x.png');
}


// Logs the pass download
function LogDownload ($TicketNumber, $logname)
{
	date_default_timezone_set ("America/Los_Angeles");

	$TourDate = substr ($_SESSION['AllTickets'][$TicketNumber]['TourDate'], 0, -6);		// Trim off the time zone
	$TourDate = substr_replace ($TourDate, " ", -6, 1);					// Replace 'T' (for Time) with a space

	$log = date ("Y-m-d h:i:sa") . "," . $_SESSION['AllTickets'][$TicketNumber]['OrderID'] . "," . $_SESSION['AllTickets'][$TicketNumber]['Description'];

	if ($_SESSION['AllTickets'][$TicketNumber]['Type'] == "Ticket")	// If it's a Ticket, append the event date
		$log = $log . "," . $TourDate;

	$log = $log . PHP_EOL;
	file_put_contents ($logname, $log, FILE_APPEND);
}

?>

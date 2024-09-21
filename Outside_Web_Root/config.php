<?php
// GENERAL SETUP

// Dashboard Credentials
$stats_pass = '##########';
$web_stats_link = 'awstats/awstats.pl?config=wallet';
$week_start_day = 'sunday';
$logname = 'wallet.log';

// Galaxy Access Method: Set to either api or db
$conn_method = 'api';

// API Credentials
$SOAP_URL = 'https://your.egalaxy.server.com';
$API_SourceID = 'Website';

// DB Credentials
$server = 'server.address.com\SQLinstance';
$db = 'Database_Name';
$username = 'gxuser';
$password = '##########';

// Apple Credentials
$PassTypeID = 'pass.com.yourco.attraction';
$TeamID = '##########';
$CertLocPass = 'Outside_Web_Root/YourPassSigningCertificateGoesInHere.p12';
$CertLocWWDR = 'wwdr.pem';
$AppleCertPass = '##########';


// PASS INFO

// Desriptions
$OrgName = "Your Organization Name";
$TicketDescription = "Tour Ticket for Your Attraction";
$ItemDescription = "Item for Your Attraction";

// Location Coordinates
$TicketLongitude = -118.336610;
$TicketLatitude = 34.151960;

$ItemLongitude = -118.336755;
$ItemLatitude = 34.147655;

// Backside Info
$Website = "http://yoursite.com";
$CustomerServiceNumber = "(555) 555-5555";

$TermsConditions = "Ticket is valid only for one person on the date and at the time of the tour listed. Ticket is nonrefundable and nontransferable. No smoking permitted during the tour. All tour guests, bags and other items are subject to screening and security checks on attarction premises.  All tour guests must follow all policies and procedures when on premises.  The organization shall not be responsible for lost, stolen or damaged property or tickets. The organization reserves the right to deny admission or to require a tour guest already admitted to leave at any time for any reason. Limited photography may be permitted as determined solely by tour guides. Tour services and areas of visitation may change or be discounted without notice. Entry onto premises constitutes consent for the organization to use any film, video or reproduction of your image and/or voice for any purpose whatsoever without payment.";


/* Set your pass colors and graphics here.  Modify these cases to fit your items and tickets,
   and if necessary you can add cases for additional items or tickets you may have.  Make sure
   to leave all braces, "break;" lines, and other coding in!  Just change the item name, color
   codes, and graphics path. */

function SetGraphics ($Description)		// Ignore this line (do not modify!)
{
	switch ($Description)				// Ignore this line (do not modify!)
	{
		case 'Official Guidebook':
			// Set pass colors
			$foregroundColor = "rgb(255, 255, 255)";
			$backgroundColor = "rgb(206, 62, 62)";
			$labelColor = "rgb(215, 215, 215)";

			$GraphicsPath = "gfx/Guidebook/";
			break;

		case 'Photo + Video':
			$foregroundColor = "rgb(255, 255, 255)";
			$backgroundColor = "rgb(206, 62, 62)";
			$labelColor = "rgb(215, 215, 215)";

			$GraphicsPath = "gfx/Photo+Video/";
			break;

		case 'DELUXE TOUR':
			$foregroundColor = "rgb(255, 255, 255)";
			$backgroundColor = "rgb(173, 140, 86)";
			$labelColor = "rgb(215, 215, 215)";

			$GraphicsPath = "gfx/Ticket/";
			break;

		default: // (Regular Tour)
			$foregroundColor = "rgb(255, 255, 255)";
			$backgroundColor = "rgb(138, 42, 43)";
			$labelColor = "rgb(215, 215, 215)";

			$GraphicsPath = "gfx/Ticket/";
	}
	return array ($foregroundColor, $backgroundColor, $labelColor, $GraphicsPath);	// Ignore this line (do not modify!)
}
?>

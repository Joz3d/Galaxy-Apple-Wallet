// JS for Pass Download Page
// by Luke Jozwiak
// Last Update: 13 Apr 2018

// When the ticket download link is hit, this will change the link to say "Loading..." and point to nowhere,
// so people see that something is happening (pass is being generated), and can't re-start the process during
// that time.
function DisableLink (number)		// 'number' is the link # sent over
{
	var Duration = 4000;			// Duration of time (in milliseconds) to display "Loading..."
	var PassLinkExact = "PassLink" + number;	// 'PassLink' is the element ID + link #
	var PassLink = document.getElementById (PassLinkExact).innerHTML;		// Pull the current link
	document.getElementById (PassLinkExact).innerHTML = "<em><strong><a href=\"\">Loading...</a></strong></em>";
	setTimeout (
		function ()
		{
			document.getElementById (PassLinkExact).innerHTML = PassLink;	// Put original link back
		},
		Duration);
}

// Live updates the log via SSE (Server Sent-Events)
// by Luke Jozwiak
// Last Update: 13 Apr 2018

function UpdateLog ()
{
	if (typeof (EventSource) !== "undefined")			// Stream if they can
	{
		var LogLoad = document.getElementById ("log");	// Sweep up the static data from the log
														// window (created by PHP)
		var LogLines = LogLoad.innerHTML.split ("</tr>");		// Split the swept up string into an
																// array on the line breaks

		var AllTime = SweepNumbers ("TDAllTime");		// Sweep up number cells
		var Today = SweepNumbers ("TDToday");
		var ThisWeek = SweepNumbers ("TDThisWeek");
		var ThisMonth = SweepNumbers ("TDThisMonth");
		var ThisYear = SweepNumbers ("TDThisYear");

		var audio = new Audio ('update.mp3');
		sound = 1;

		var source = new EventSource ("logstream.php");	// Create the source  object, and give it the
														// name and location of the server-side script

		source.onmessage = function (event)				// Detect message receipt
		{
			if (sound == 1)
			{
				audio.load ();
				audio.play ();
			}

			IncWriteNumCell (AllTime);					// Increment and rewrite the number cells
			IncWriteNumCell (Today);
			IncWriteNumCell (ThisWeek);
			IncWriteNumCell (ThisMonth);
			IncWriteNumCell (ThisYear);

			var NewLine = '<tr><td id="NewLine">' + event.data + '</td>';	// Update log window
			LogLines.unshift (NewLine);			// Pop the new event (log line) onto the array
			document.getElementById ("log").innerHTML = " ";	// Clear the log window so that we
																// can loop the array cleanly
			for (index = 0; index < 10; index++)		// Output the 10 array slots (log lines)
				document.getElementById ("log").innerHTML += LogLines[index] + "</tr>";

			flash ("TDAllTime", "UpdateAllTime");		// Flash cells after updating
			flash ("TDToday", "UpdateToday");
			flash ("TDThisWeek", "UpdateThisWeek");
			flash ("TDThisMonth", "UpdateThisMonth");
			flash ("TDThisYear", "UpdateThisYear");
			flash ("NewLine", "UpdateLog");
		};
	}
}


// Sweeps up the conents of a number cell and returns it split into label and value
function SweepNumbers (element)
{
	var raw_content = document.getElementById (element);	// Sweep up static data of passed element
	var content = raw_content.innerHTML.split ("<br>");		// Split it into an array on line breaks

	content[1] = content[1].replace (/,/g, "");				// Remove any commas in the number
	content[1] = parseInt (content[1]);						// Convert the string to an int
	return content;
}


// Increments Number Cell value and writes it back the Number Cell (Label<br>number)
function IncWriteNumCell (cell)
{
	var element = "TD" + cell[0].replace (/-|\s/g, "");		// Get the element name based off the
	++cell[1];												// cell header name.  Remove dashes & spaces
	document.getElementById (element).innerHTML = cell[0] + "<br>" + cell[1].toLocaleString();
}


// Flashes an element via CSS animation by removing and re-adding the classname specifying the animation
// (At the moment, removing the class and adding it back is an easy hack to get CSS animations to replay.)
function flash (element, classname)
{
	document.getElementById (element).classList.remove (classname);
	void document.getElementById (element).offsetWidth;		// Trigger the reflow
	document.getElementById (element).classList.add (classname);
}


// Toggles sound on/off an changes the speaker icon accordingly
function ToggleSound ()
{
	sound = !sound;

	if (sound == 0)
		document.getElementById ("speaker").src = "gfx/Webpage/speaker_off.png";
	else
		document.getElementById ("speaker").src = "gfx/Webpage/speaker.png";
}

<?php
// Provides an SSE (Server Sent-Events) stream whenever the download log is updated.
// by Luke Jozwiak
// Last Update: 29 Dec 2017

header ('Content-Type: text/event-stream');	// Set the header for streaming
header ('Cache-Control: no-cache');

require '../Outside_Web_Root/config.php';

session_start ();

$LastLine = '';

$logfile = fopen ($logname, "r") or die ("Error: Unable to open log file");
$cursor = -1;

// Read the last line from the log, without loading the whole file into memory, with help from
// Ionuț G. Stan [stackoverflow]
fseek ($logfile, $cursor, SEEK_END);
$char = fgetc ($logfile);

while ($char === "\n" || $char === "\r")	// Trim trailing newline chars of the file
{
	fseek ($logfile, $cursor--, SEEK_END);
    $char = fgetc ($logfile);
}

// Read until the start of file or first newline char
while ($char !== false && $char !== "\n" && $char !== "\r")
{
	$LastLine = $char . $LastLine;			// Prepend the new char
	fseek ($logfile, $cursor--, SEEK_END);
	$char = fgetc ($logfile);
}

if ($LastLine != $_SESSION['LastLine'])		// If the last line of the log has changed, send it over.
{
	echo "data: $LastLine\n\nretry: 500\n\n";
	$_SESSION['LastLine'] = $LastLine;
}

flush();
?>

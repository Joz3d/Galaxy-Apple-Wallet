Galaxy Apple Wallet
===================
[About](#about)

[Requirements](#requirements)

[Installation Instructions](#installation-instructions)

[Security Guidelines](#security-guidelines)

[Troubleshooting](#troubleshooting)


About
-----
This PHP app will create a web page with Apple Wallet passes to download for the tickets and item vouchers that are on the order you provide to it.

In more detail, this is accomplished by receiving an order number and email address from a confirmation email, connecting to the Galaxy database to verify that the email address is linked to the order number (verification), and if so, it retrieving all items on the order which have a Visual ID, and generating a web page of links to download Apple Wallet passes for these items.  Apple Wallet passes are generated using the [PHP-PKPass](https://github.com/tschoffelen/PHP-PKPass) library.


Requirements
------------
#### PHP 5.6

\+ PHP support for connecting to MSSQL Server (`loadpass.php` is setup by default to use Microsoft's PDO driver [`SQLSRV32/php_pdo_sqlsrv_56_ts.dll`] against PHP 5.6.  If using a different driver, please modify code accordingly ~line 85 in `loadpass.php`).

#### Apple Credentials

From your Apple Developer portal, you need the following:

	Pass Type ID
	Team Identifier
	Pass Signing Certificate (.p12)
	Pass Signing Certificate password
	WWDR Certificate

It is recommended that these are placed outside of web root (see #2 of [Security Guidelines](#security-guidelines) below).

#### Apple Wallet Pass Graphics

Ensure that you have already created the graphics for your ticket(s) and/or item(s).


Installation Instructions
-------------------------
1. Place your Wallet graphics in the `gfx` folder.  This current package comes with folders for `Ticket`, `Photo+Video`, `Guidebook`, and `Webpage`.  You may delete or add folders here as you need, with the exception of `Webpage`, which contains the responsive graphics `logo_horizontal.png` and `logo_vertical.png` used for the web page header logo.

   Guidelines on graphics sizes for Wallet can be found in the 'Creating Pass Packages' -> 'Pass Design and Creation' section of Apple's [Wallet Developer Guide](https://developer.apple.com/wallet/)


2. In the folder `Outside_Web_Root`, open the file `config.php`.  This is where all of your setup and Wallet ticket/item configuration is done.  In this file, set your database, Apple, and Pass (ticket or item) information.  The bottom portion of `config.php` is where you set the RGB color codes for your different passes, as well as the path to the graphics folder for the pass (from step 1).

   Also place your Apple Developer pass signing certificate (.p12 file) in `Outside_Web_Root`.


3. Place your Apple WWDR intermediate certificate (`wwdr.pem`) into this app root folder, where this README.md file and the .php files are.


4. Place all files into a folder on the web server with write privileges (this app creates temp folders/files), apart from those files in the `Outside_Web_Root` folder.  Place this folder outside of your web root, so that it is not publicly accessible or otherwise accessible to your web server.  Update the location of the `Outside_Web_root` folder near the top of `loadpass.php` and `getpass.php`, updating the line:

		require 'Outside_Web_Root/config.php';


5. Provide URL to loadpass.php to your Galaxy Sys Admin, who will then update the Galaxy confirmation email to create an Apple Wallet link.  This link will provide the Order ID and Email Address to `loadpass.php`, and will look like:

		<a href="http://domain/loadpass.php?o=<% write (Order.GetField ('OrderID')) %>&e=
		<% write (Order.GetField ('Contact.Email')) %>">
		<img src="http://domain/link/to/walleticon.png"></a>

   For details about Apple requirements for graphics/text when distributing Wallet passes, see Apple's [Add to Apple Wallet Guidelines](https://developer.apple.com/wallet/).


Security Guidelines
-------------------
1. In your Galaxy database, you should either:

	a. Use the same user that eGalaxy (the Galaxy Webstore) uses to connect to the database, that being 'gxuser'.

	b. Create a new database user specifically for this application, who is restricted to connecting to the database from only the web server hosting this application.  This user should have READ-ONLY access to the following tables and columns:

		Orders (ContactID, OrderID)
		CustContacts (Email, CustContactID, FirstName, LastName)
		Tickets (OrderNo, VisualID, ContactID, EventNo, Status)
		RMEvents (EventName, StartDateTime, EventID)

	as well as access to the function `SYSDATETIMEOFFSET()`

	  There is already a Database Role in the Galaxy database called `db_datareader` that this user can be assigned to, or based off of.

2. Database and pass signing credentials are stored in `config.php`.  This file should be stored OUTSIDE of web root, so that it is not otherwise accessible by/via the web server.  `config.php` and/or its folder may also be restricted to be accessible only by the web server.  Please update the path to `config.php` in `loadpass.php` and `getpass.php`, updating the line:


Troubleshooting
---------------
If you are having trouble or just want to see what is happening under the hood, there is fairly extensive debugging available by setting the 'debug' variables to "1", near the top of `loadpass.php` and `getpass.php`

If you have any questions, contact the author at lukejoz at the most popular email service.

Galaxy Apple Wallet
===================
[About](#about)

[Requirements](#requirements)

[Installation Instructions](#installation-instructions)

[Security Guidelines](#security-guidelines)

[Troubleshooting](#troubleshooting)


About
-----
This PHP app will create a web page with Apple Wallet passes to download for the tickets and item
vouchers that are on the order you provide to it.

In more detail, this is accomplished by receiving an order number and email address from a confirmation email, connecting to _eGalaxy_ to verify that the email address is linked to the order number (verification), and if so, it retrieving all items on the order which have a Visual ID, and generating a web page of links to download Apple Wallet passes for these items.  Apple Wallet passes are generated using the [PHP-PKPass](https://github.com/tschoffelen/PHP-PKPass) library.


Requirements
------------
#### PHP (5.6 or 7)

If utilizing the direct-to-database connection model (don't), then you additionally need:

\+ PHP support for connecting to MSSQL Server 
* This app by default uses the PDO_DBLIB driver (`pdo_dblib.so`)
* If you are running this PHP on Windows, you will need to instead use the Microsoft PHP Driver for SQL Server (SqlSrv).  You will need to modify the code accordingly ~line 80 in `loadpass.php`

#### Apple Credentials

From your Apple Developer portal, you need the following:

	Pass Type ID
	Team Identifier
	Pass Signing Certificate (.p12)
	Pass Signing Certificate password
	WWDR Certificate

It is recommended that these are placed outside of web root (see #1 of [Security Guidelines](#security-guidelines) below).

#### Apple Wallet Pass Graphics

Ensure that you have already created the graphics for your ticket(s) and/or item(s).


Installation Instructions
-------------------------
1. Place your Wallet graphics in the `gfx` folder.  This current package comes with folders for `Ticket`, `Photo+Video`, `Guidebook`, and `Webpage`.  You may delete or add folders here as you need, with the exception of `Webpage`, as those are the graphics used for the web page.

   Guidelines on graphics sizes for Wallet can be found in the 'Creating Pass Packages' -> 'Pass Design and Creation' section of Apple's [Wallet Developer Guide](https://developer.apple.com/wallet/)


2. In the folder `Outside_Web_Root`, open the file `config.php`.  This is where all of your setup and Wallet ticket/item configuration is done.  In this file, set your _eGalaxy_, Apple, and Pass (ticket or item) information.  

   The bottom portion of `config.php` is where you set the RGB color codes for your different passes, as well as the path to the graphics folder for the pass (from step 1).

   Also place your Apple Developer pass signing certificate (.p12 file) in `Outside_Web_Root`.


3. Place your Apple WWDR intermediate certificate (`wwdr.pem`) into this app root folder, where this README.md file and the .php files are.


4. Place all files into a folder on the web server with write privileges (this app creates temp folders/files), apart from those files in the `Outside_Web_Root` folder.  You can rename 'Outside_Web_Root' if you'd like, but place it outside of your web root, so that it is not publicly accessible or otherwise accessible to your web server.  Update the location of the `Outside_Web_root` folder near the top of `loadpass.php` and `getpass.php`, updating the line:

		require '../Outside_Web_Root/config.php';


5. Provide URL to `loadpass.php` to your:

	A. Web development team so that they may place a link to download Apple Wallet tickets on the order confirmation page.
	   
	B. Your _Galaxy_ Sys Admin, who will then update the _Galaxy_ confirmation email template to create an Apple Wallet link.  This link will provide the Order ID and Email Address to `loadpass.php`, and should look like:

		<a href="http://domain/loadpass.php?o=<% write (Order.GetField ('OrderID')) %>&e=
		<% write (Order.GetField ('Contact.Email')) %>">
		<img src="http://domain/link/to/walleticon.png"></a>

   For details about Apple requirements for graphics/text when distributing Wallet passes, see Apple's [Add to Apple Wallet Guidelines](https://developer.apple.com/wallet/).


Security Guidelines
-------------------
1. _eGalaxy_ and pass signing credentials are stored in `config.php`.  This file should be stored OUTSIDE of web root, so that it is not otherwise accessible by/via the web server.  `config.php` and/or its folder may also be restricted to be accessible only by the web server.  Please update the path to `config.php` in `loadpass.php` and `getpass.php`, updating the line:

		require '../Outside_Web_Root/config.php';

   ...near the top of `loadpass.php` and `getpass.php`

2. If you are for some reason using the deprecated direct-db model:

	A. Use the same user that _eGalaxy_ (the _Galaxy_ Webstore) uses to connect to the database.

	B. Create a new database user specifically for this application, who is restricted to connecting to the database from only the web server hosting this application.  This user should have READ-ONLY access to the following tables and columns:

		Orders (ContactID, OrderID)
		CustContacts (Email, CustContactID, FirstName, LastName)
		Tickets (OrderNo, VisualID, ContactID, EventNo, Status, PLU)
		RMEvents (EventName, StartDateTime, EventID)
		Items (Name, PLU)

	as well as access to the function `SYSDATETIMEOFFSET()`

	  There is already a Database Role in the _Galaxy_ database called `db_datareader` that this user can be assigned to, or based off of.


Troubleshooting
---------------
If you are having trouble or just want to see what is happening under the hood, there is fairly extensive debugging available by setting the `debug` variables to `1`, near the top of `loadpass.php` and `getpass.php`

If you have any questions, contact the author at lukejoz at the most popular email service.

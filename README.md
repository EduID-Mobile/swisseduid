Moodle authentication plugin for Swiss edu-ID 
=========================================================================

This is a [Moodle](http://moodle.org) authentication plugin aimed at mobile 
applications compatible with the Swiss edu-ID authentication.

This is just a draft and it not possible to use in production yet.

Installation
------------

To install please proceed as follows:

1. Decompress the eduidauth archive and move the rename the folder to eduidauth.

   You also can use this command: git clone git://github.com/BLC-HTWChur/oauth2.git oauth2

2. Move the folder to MOODLEROOT/auth

3. Authenticate as administrator on your Moodle installation and click on Notifications.

4. Click on Ok and finish the installation

Once the installation is complete you should have a authentication plugin under
Settings-Plugins-Authentication-Manage Authentication named "Swiss edu-ID auth".

Usage
-----
Use the get_service_access.php to generate a moodle access token.
The accepted parameters are grant_type and authorization_code.

Use the get_app_token.php to generate the webservice token.
The accepted parameters are access_token and service_shortname.

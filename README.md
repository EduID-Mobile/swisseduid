Moodle authentication plugin for OAuth2 and OpenID Connect
=========================================================================

This is a [Moodle](http://moodle.org) authentication plugin aimed at mobile
applications compatible with the Swiss edu-ID authentication.

This is just a draft and it not possible to use in production yet.

Installation
------------

To install please proceed as follows:

 1a. Decompress the eduidauth archive and move the rename the folder to eduidauth.

 1b. You are brave and install directly from github use these commands:

 ```
 $ git clone git://github.com/BLC-HTWChur/swisseduid.git swisseduid
 $ cd swisseduid
 $ composer install
 ```

Note that when cloning from github, you need to have composer installed on your
system.

 2. Move the folder to MOODLEROOT/auth

 4. Authenticate as administrator on your Moodle installation and click on Notifications.

 5. Click on Ok and finish the installation

Once the installation is complete you should have a authentication plugin under
Settings-Plugins-Authentication-Manage Authentication named "Swiss edu-ID auth".

In order to use this plugin, you must configure a remote authorization service.
This plugin will try to configure itself as much as possible. 

History
-------

This plugin has been developed for supporting the [Swiss Edu-ID services](http://eduid.ch).

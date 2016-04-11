eduidauth: manual authentication plugin for Swiss edu-ID authentication
=========================================================================

This is a [Moodle](http://moodle.org) authentication plugin aimed at mobile 
applications. It is compatible with the Moodle 2.x branch.

Installation
------------

To install please proceed as follows:

1. Decompress the eduidauth archive and move the rename the folder to eduidauth.

   You also can use this command: git clone git://github.com/arael/eduidauth.git eduidauth

2. Move the folder to MOODLEROOT/auth

3. Authenticate as administrator on your Moodle installation and click on Notifications.

4. Click on Ok and finish the installation

Once the installation is complete you should have a authentication plugin under
Settings-Plugins-Authentication-Manage Authentication named "edu-ID auth".

Usage
-----

The usage of this course should be a secure request structured as follows.

https://yourmoodleinstallation.ch/auth/eduidauth/authenticate.php?username=myuser&password=mypassword

If the user has been authenticated the response will be:
{
	"user": {
		id: 3,
		username: 'foo',
		lastname: 'fooo',
		....
	},
	"courses" {
		"5":{
			"name":"My first course",
			"token":"caad14a34dc3582e1b0d9a83be6ae68b",
			"startdate": date
			....
		},
		"7":{
		  ....
		}
	}
}

As you can see from the example for every course there one token as fields. 
Every request creates the new tokens and deletes the old ones. 
The duration of the tokens and the association with the webservice is set 
in the plugin settings: 

	Site Administration->Plugins->Authentication->Swiss edu-ID.

You may develop your own webservices set or use/extend the uniappws. In any 
case the correct functioning and the generation of the tokens requires a valid 
webservice association.

# capstone
This is a repository for my BS - Cybersecurity Capstone project.

!!! This is a draft.

# Requirements
* Smart Card Reader
* Apache2 with php
* my/maria db

# To install
* Place the repository on an apache webserver with mod_php (further instructions to follow later)
* From an unprivileged user, run lib/composer/install.sh (the install hash may have changed so update the composer install commands if needed)
* Set a password in create_database.sql, run it to create new database
* Place a file at /var/www/.creds/vote.php containing mysql connection details (aka username and password)
* Setup a python environment for the card processing (this has only been tested on FreeBSD using the install script provided in the daemon directory)
* Set the server name in voted.py, run it.
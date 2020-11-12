![QUIQQER Frontend Users Cleanup](bin/images/Readme.png)

QUIQQER Frontend Users Cleanup
========

Console tool and cron for mass-deleting users who meet certain criteria.

Package Name:

    quiqqer/frontend-users-cleanup

Features
--------
* Automatically delete users that meet a variety of criteria, including:
  * Creation date
  * Account age (days)
  * Not logged in since
  * Activation status
  * Group membership
  * Email verification status
* Cronjob
* Console tool

Installation
------------
The Package Name is: quiqqer/frontend-users-cleanup

Console tool usage example
--------------------------
```bash
$ cd {QUIQQER_ROOT}

# Delete all users that were created from 12.11.2019 until now that have an unverified e-mail address
$ php quiqqer.php frontend-users:cleanup --createDateFrom=2019-11-12 --emailVerified=0
```

Contribute
----------
- Project: https://dev.quiqqer.com/quiqqer/frontend-users-cleanup
- Issue Tracker: https://dev.quiqqer.com/quiqqer/frontend-users-cleanup/issues
- Source Code: https://dev.quiqqer.com/quiqqer/frontend-users-cleanup/tree/master


Support
-------
If you have found errors, wishes or suggestions for improvement,
you can contact us by email at support@pcsg.de.

We will try to meet your needs or send them to the responsible developers
of the project.

License
-------
GPL-3.0+

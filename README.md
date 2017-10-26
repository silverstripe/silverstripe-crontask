SilverStripe CronTask
==========================

[![Build Status](http://img.shields.io/travis/silverstripe/silverstripe-crontask.svg?style=flat)](https://travis-ci.org/silverstripe/silverstripe-crontask)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-crontask/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-crontask/?branch=master)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-crontask/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-crontask)

Gives developers an ability to configure cron-like tasks through the code.

This module intentionally doesn't surface any of the configuration or logs
to the CMS, being of an opinion these tasks belong with developers and sysadmins.
If you want that, see the "CMS-driven scheduler" section below.

What problem does module solve?
-------------------------------

Developers don't always have access to the server to configure cronjobs,
and instead they have to rely on server administrators to do it for them.
This can slow down development cycles, and can lead to misunderstandings and
misconfigurations if cronjobs are set up by hand.

This module solves this by getting the sysadmin to set up a single generic
cronjob on the server side, and delegate the actual job definition to the
PHP code.

CMS-driven scheduler
-------------------------------

If you are looking for CMS-controllable scheduler, please check out
the [queuedjobs module](https://github.com/silverstripe-australia/silverstripe-queuedjobs/).
Here are some examples of how to implement recurring jobs with that module:

* [GenerateGoogleSitemapJob](https://github.com/silverstripe-australia/silverstripe-queuedjobs/blob/570bae301c09d4c4367144be260a7213341a0020/code/jobs/GenerateGoogleSitemapJob.php#L184)
* [LDAPMemberSyncJob](https://github.com/silverstripe/silverstripe-activedirectory/blob/master/code/jobs/LDAPMemberSyncJob.php#L52)

Installing
----------

Add the following to your project's composer.json:

	{
		"require": {
			"silverstripe/crontask": "*"
		}
	}

Run `composer update` (this will also install needed 3rd party libs in ./vendor)

Usage
-----

Implement the `CronTask` interface on a new or already existing class:

	class TestCron implements CronTask {

		/**
		 * run this task every 5 minutes
		 *
		 * @return string
		 */
		public function getSchedule() {
			return "*/5 * * * *";
		}

		/**
		 *
		 * @return void
		 */
		public function process() {
			echo 'hello';
		}
	}

Run `./framework/sake dev/build flush=1` to make SilverStripe aware of the new
module.

Then execute the crontask controller, it's preferable you do this via the CLI
since that is how the server will execute it.

	./framework/sake dev/cron

Server configuration
--------------------

Linux and Unix servers often comes installed with a cron daemon that are running
commands according to a schedule. How to configure these can vary a lot but the
most common way is by adding a file to the `/etc/cron.d/` directory.

First find the correct command to execute, for example:

```
/usr/bin/php /path/to/silverstripe/docroot/framework/cli-script.php dev/cron
```

Then find out which user the webserver is running on, for example `www-data`.

Then create / edit the cron definition:

```
sudo vim /etc/cron.d/silverstripe-crontask
```

The content of that file should be:

```
* * * * * www-data /usr/bin/php /path/to/silverstripe/docroot/framework/cli-script.php dev/cron
```

This will run every minute as the www-data user and check if there are any
outstanding tasks that needs to be executed.

**Warning**: Observe that the crontask module doesn't to any scheduling. If the
run time is more than one minute, it might start another process that interferes
with the still running process. You can either trigger the `dev/cron` task less
often or use something like [sera](https://github.com/silverstripe-labs/sera).

For more information on how to debug and troubleshoot cronjobs, see
[http://serverfault.com/a/449652](http://serverfault.com/a/449652).

The getSchedule() method
----------------------

The crontask controller expects that the getSchedule returns a string as a cron
expression.

Some examples:

- `* * * * *` - every time
- `*/5 * * * *` - every five minute (00:05, 00:10, 00:15 etc)
- `0 1 * * *` - every day at 01:00
- `0 0 2 * *` - the 2nd of every month at 00:00
- `0 0 0 ? 1/2 FRI#2 *` - Every second Friday of every other month at 00:00

Example:

```
public function getSchedule() {
    return "0 1 * * *";
}
```

The process() method
----------------------

The `process` method will be executed only when it's time for a task to run
(according to the getSchedule method). What you do in here is up to you. You can
either do work in here or for example execute BuildTasks run() methods.

```
public function process() {
    $task = FilesystemSyncTask::create();
    $task->run(null);
}
```

CRON Expressions
----------------

A CRON expression is a string representing the schedule for a particular command to execute.  The parts of a CRON schedule are as follows:

    *    *    *    *    *    *
    -    -    -    -    -    -
    |    |    |    |    |    |
    |    |    |    |    |    + year [optional]
    |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
    |    |    |    +---------- month (1 - 12)
    |    |    +--------------- day of month (1 - 31)
    |    +-------------------- hour (0 - 23)
    +------------------------- min (0 - 59)

For more information about what cron expression is allowed, see the
[Cron-Expression](http://mtdowling.com/blog/2012/06/03/cron-expressions-in-php/)
post from the creator of the 3rd party library.

Contribute
----------

Do you want to contribute? Great, please see the [CONTRIBUTING.md](CONTRIBUTING.md)
guide.

License
-------

This module is released under the BSD 3-Clause License, see [LICENSE](LICENSE).

Code of conduct
---------------

When having discussions about this module in issues or pull request please
adhere to the [SilverStripe Community Code of Conduct](https://docs.silverstripe.org/en/contributing/code_of_conduct).

Thanks
------

Thanks to [Michael Dowling](http://mtdowling.com/blog/2012/06/03/cron-expressions-in-php/)
for doing the actual job of parsing cron expressions.

This module is just a thin wrapper around his code.


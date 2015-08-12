# 0.2.7 (August 12, 2015)

New:
* Environment variables
* Restart Manager exposed as new tab on Domains Configuration page
* jxos.ini for custom version appliance

Changes:
* Better download module (error checking, rollback)


# 0.2.6 (May 12, 2015)

New:
* JXcore 0.3.0.0 support

Changes:
* fix for /jxcore_logs/ access
* added root definition for domain's nginx conf file
* improved nginx reload timing

# 0.2.5 (February 12, 2015)

Changes:
* fix for submitting multiline nginx directives (domain form)
* nginx config files for domains do not use upstream any more
* improved monitor management of crashed applications

# 0.2.4 (never published)

New:
* providing command-line args for user's application (domain form)

Changes:
* improved spawner's error handling and file watcher


# 0.2.3 (November 12, 2014)

New:
* added reload nginx on monitor start
* removing applications' nginx config files on monitor start. they will be recreated on each app start.

Changes:
* fixed status messages workaround to be applied **only** for Plesk 12.0.18 and update < v8
* minor warning fixes
* security improvements for accessing subpages before JXcore is installed


# 0.2.2 (October 31, 2014)

Changes:
* status messages workaround to be applied only for any Plesk 12 and update < v8


#0.2.1 (October 5, 2014)

* Plesk 12 compatibility


# 0.2.0 (September 29, 2014)

* Enable / Disable JXcore support per subscription
* Improvements for file watcher (automatic application start / stop)
* Improvements for monitoring (serve packaged apps on Plesk)


# 0.1.6 (September 8, 2014)


# 0.1.5 (July 10, 2014)


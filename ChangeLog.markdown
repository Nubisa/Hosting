
2.0.6 (08-05-2015)

New:
* JXcore 0.3.0.0 support

Changes:
* fix for /jxcore_logs/ access
* added root definition for domain's nginx conf file
* improved nginx reload timing

2.0.5 (12-02-2015)

Changes:
* fix for submitting multiline nginx directives (domain form)
* nginx config files for domains do not use upstream any more
* improved monitor management of crashed applications

2.0.4

New:
* providing command-line args for user's application (domain form)

Changes:
* improved spawner's error handling and file watcher


2.0.3 (10-11-2014)

New:
* added reload nginx on monitor start
* removing applications' nginx config files on monitor start. they will be recreated on each app start.


Changes:
* fixed status messages workaround to be applied **only** for Plesk 12.0.18 and update < v8
* minor warning fixes
* security improvements for accessing subpages before JXcore is installed


2.0.2 (09-07-2014)

Changes:
* status messages workaround to be applied only for any Plesk 12 and update < v8

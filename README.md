# 🔄 Nextcloud Updater

🔄 The built-in Updater keeps your Nextcloud Server installation up-to-date. In many cases it can be used in place of the manual update process.

![image](https://github.com/nextcloud/updater/assets/1731941/42cb00b1-806d-4b7d-953e-f3d4abf0c9e7)

## Background

* [*How to upgrade*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/upgrade.html) in the [*Maintenance*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/index.html) chapter of the [Nextcloud Administration Manual](https://docs.nextcloud.com/server/latest/admin_manual/index.html)
* [*What does the updater do?*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/update.html#what-does-the-updater-do)

> [!NOTE]
> The built-in Updater is primarily applicable to manual/Archive (aka: "tarball") installations. Most other installation methods (such as Docker images and Snaps) utilize their own officially supported processes for keeping Nextcloud Server up-to-date.
> **Please follow their respective documented approaches rather than trying to run the Updater yourself.**

## Configuration

No special configuration parameters are generally required for the Updater. There are some optional parameters which may be of interest in some environments:

* [`updatedirectory`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#updatedirectory)
* [`update.disable-web`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#upgrade-disable-web)
* [`update.cli-upgrade-link`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#upgrade-cli-upgrade-link)

## Usage

The built-in Updater can be used in two ways: via the Web UI and via the command-line. The former is more convenient, but the latter is more reliable.

> [!WARNING]
> Please make a [backup](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/backup.html) and familiarize yourself with the [restore](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/restore.html) process before proceeding with using the Updater.

### Web

Go to *Administration settings*. It's in the *Overview* (under the *Update* heading). If the `updatenotifications` app is enabled you will also receive notifications when new versions of Server are published.

See [*Using the web based updater*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/update.html#using-the-web-based-updater) in the Nextcloud Administration Manual.

### Command-line

See [*Using the command line based updater*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/update.html#using-the-command-line-based-updater)

`updater.phar`

Parameters:

```
--no-backup              Don't create a backup of the application code (note: the Updater's backup *never* backs up data or databases contents)
--no-upgrade             Don't automatically run `occ upgrade` when the Updater finishes (note: `occ upgrade` is required after Updater updates the application code in order to push out any database changes in the newly deployed version of Nextcloud)
```

## Troubleshooting

### Logging

* The Updater operates independently from Server so it has it's own log file called `updater.log` which is located in the configured `datadirectory` for the instance.

### Unable to use the built-in Updater

If the built-in Updater does not function reliably for your environment, the old reliable (albeit admittedly tedious) [manual update](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/manual_upgrade.html) process may be your best alternative.
This was the primary way of keeping Nextcloud Server up-to-date before the automated Updater was developed. In addition, if Updater does not work in your environment, report the details of your situation to https://github.com/nextcloud/updater/issues so that 
consideration can be given to adapting Updater to a wider variety of environments.

### Updater != `occ upgrade`

The `occ upgrade` command runs the database migrations which adapt your existing database to the updated version Nextcloud Server that is deployed by the Updater (or via a manual update).

Despite the confusing naming - which makes sense technically, but in hindsight may not have been the best to avoid confusion - the Updater *must* run (and completely successfully) before `occ upgrade` will have anything to do. 

## Getting Help

https://help.nextcloud.com

## Suggesting enhancement ideas or reporting possible bugs:

https://github.com/nextcloud/updater/issues

> [!TIP]
> Since bug reports are not for technical support, you may not receive a personalized or timely response. If you suspect you may have encountered a previously unknown bug, please try to troubleshoot it in the [Help Forum](https://help.nextcloud.com) first.

## Contributing

If you have found a possible solution to a reported bug or an enhancement idea, please submit a PR.

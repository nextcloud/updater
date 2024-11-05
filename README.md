<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-FileCopyrightText: 2014 ownCloud, Inc.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# 🔄 Nextcloud Updater

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/updater)](https://api.reuse.software/info/github.com/nextcloud/updater)

🔄 The built-in Updater makes it easier to keep your Nextcloud Server installation up-to-date. In many cases it can be used in place of the manual update process.

![image](https://github.com/nextcloud/updater/assets/1731941/42cb00b1-806d-4b7d-953e-f3d4abf0c9e7)

The Updater can be accessed via the Web UI as well as the command line. It may be used both to update to the latest patch level (i.e. security/bug fixes) as well as to update to a completely new major release (i.e. new features/functionality).

## Known issues

* The `createBackup` step, which is **not** intended to backup user data, currently can slow down the larger the `datadirectory` gets (nextcloud/updater#507) 
* The `deleteOldFiles` step, which does not actually touch user data, currently can slow down the larger the `datadirectory` gets (nextcloud/updater#397)
* Multiple `*.config.php` files are currently not supported / ignored (nextcloud/updater#384)
* In some environments, the current iterator implementation may fail (nextcloud/updater#519)

## Background

* [*How to upgrade*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/upgrade.html) in the [*Maintenance*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/index.html) chapter of the [Nextcloud Administration Manual](https://docs.nextcloud.com/server/latest/admin_manual/index.html)
* [*What does the updater do?*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/update.html#what-does-the-updater-do)

> [!NOTE]
> The built-in Updater is primarily applicable to manual/Archive (aka: "tarball / zip") installations. Most other installation methods (such as Docker images and Snaps) utilize their own officially supported processes for keeping Nextcloud Server up-to-date.
> **Please follow their respective documented approaches rather than trying to run the Updater yourself.**

## Installation

### Requirements

* An Archive based installation (i.e. **not** Docker, Snap, AIO, etc. unless you're the packager of said method and utilize the Updater internally or permit your users to utilize it).
* The app is bundled with the installation Archive for Nextcloud Server. No additional steps are necessary to install.

Note:

* The Updater "app" is not a standard Nextcloud Server app so it is **not** managed via the app store.
* The Web UI mode of Updater will not be active unless the `updatenotification` app is enabled.

## Releases and CHANGELOGs

As a shipped/bundled app:

- changes are posted within the [Nextcloud Server changelog](https://nextcloud.com/changelog/).
- releases are **not** posted in this GitHub repository, but they are [tagged](https://github.com/nextcloud/updater/tags) for code perusal.
- it is automatically kept up-to-date with each Nextcloud Server release.

## Configuration

No special configuration parameters are generally required for the Updater. There are some optional parameters which may be of interest in some environments:

* [`updatedirectory`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#updatedirectory)
* [`upgrade.disable-web`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#upgrade-disable-web)
* [`upgrade.cli-upgrade-link`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#upgrade-cli-upgrade-link)
* [`updater.secret`]
* [`updater.server.url`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#updater-server-url)
* [`updater.release.channel`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#updater-release-channel)
* [`maintenance`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#id1)

There are also some which are set automatically and not generally meant to be manually adjusted:

* [`version`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#version)

The following are standard parameters already set in any functioning Server installation, but which the Updater requires:

* [`datadirectory`]
* [`instanceid`]

Other update related parameters:

* [`logfile`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#logfile)
* [`updatechecker`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#updatechecker)
* [`appcodechecker`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#appcodechecker)

## Usage

Using the Updater via the Web UI is more convenient, but using it from the command line is more reliable.

> [!WARNING]
> Please make a [backup](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/backup.html) of your data and database and familiarize yourself with the [restore](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/restore.html) process before proceeding with using the Updater.

### Web UI mode

Go to *Administration settings*. It can be reached in the *Overview* under the *Update* heading.

See [*Using the web based updater*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/update.html#using-the-web-based-updater) in the Nextcloud Administration Manual.

### Command line mode

See [*Using the command line based updater*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/update.html#using-the-command-line-based-updater)

`updater.phar`

Parameters:

```
--no-backup              Don't create a backup of the application code (note: the Updater's backup *never* backs up data or databases contents)
--no-upgrade             Don't automatically run `occ upgrade` when the Updater finishes (note: `occ upgrade` is required after Updater updates the application code in order to push out any database changes in the newly deployed version of Nextcloud)
```

## How it works

Keeping Nextcloud Server up-to-date, at a high level, consists of three things:

1. Updating the code
2. Upgrading the database
3. Updating all shipped and locally deployed apps

The Updater app handles step #1 (see [*What does the updater do?*](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/update.html#what-does-the-updater-do)) and prepares the environment for steps #2 and #3 (which are handled by `occ upgrade` *after* the Updater successfully completes).

The Updater app needs to function while the Nextcloud Server is offline for code updates. It is therefore designed to operate as independently as possible from the rest of Server. This permits Server to be updated safely and the Updater to proceed without interruption during code swaps. The trade-off is that none of the typical Nextcloud classes/APIs are available to serve the needs of the Updater. The Updater, fortunately, has a relatively narrowly defined mission and set of needs.

### Theory of Operation

* The Updater runs from either `/updater/index.php` (Web mode) or `/updater/updater.phar` (Command line mode), which are
  provided by the previous (last/current) Server installation.
* The Updater maintains state in `.step` files/folders
* Connecting clients are provided with temporary failure HTTP codes to minimize end user interruptions

Some historical more in-depth context can be found in nextcloud/updater#1 and nextcloud/updater#2.

### Components

#### Updater app components

- `/updater/updater.phar` (CLI Updater)
- `/updater/index.php` (Web Updater)

#### Server components

- `occ upgrade` (Database Upgrader)
- Web `occ upgrade` trigger/monitor
- `updatenotification` app which provides the *Update* overvew under *Administration settings* and handles notifying admins of the availability of updates
- The legacy update availability notifier (only activated if the `updatenotification` is disabled and does not provide an Overview screen; currently generates an indefinite "nag")

### Update methods

Since there are multiple ways of triggering and using the Updater as well as the later database upgrades and app updates, it is useful to have an overview of the different work flows. This is particularly useful if you're doing development related to the Nextcloud Server update/upgrade process. It also further shows how steps 1-3 above are coupled together.

#### Semi-Manual

Consists of:

* Manually replacing installation folder code contents except for config, data, and locally installed apps (i.e. does **not** use the Updater app at all)
* Running `occ upgrade` from the command line

The [full procedure for keeping Nextcloud Server up-to-date manually](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/manual_upgrade.html#upgrade-manually) is documented in the Nextcloud Admin Manual.

#### Pure CLI Update/Upgrade:

Consists of:

* Running `/updater/updater.phar` from the command line
* Running `occ upgrade` from the command line

#### Hybrid CLI/Web Update/Upgrade

Consists of:

* Running `/updater/updater.phar` from the command line
* Triggering and monitoring `occ upgrade` from the Web UI (this is the default behavior after a successful Updater run if you do not opt to let `updater.phar` trigger the database upgrades and app updates at the end of its run)

#### Hybrid Web/CLI Update/Upgrade

Consists of:

* Accessing `/updater/index.php` via *Administration settings->Overview* *Update* section.
* Running `occ upgrade` from the command line

#### Full Web Update/Upgrade

Consists of:

* Accessing `/updater/index.php` via *Administration settings->Overview* *Update* section.
* Triggering and monitoring `occ upgrade` from the Web UI (this is the default behavior after a successful Updater run if you opt to let the Web Updater trigger the database upgrades and app updates at the end of its run)

Implementation:

* *Admin settings->Version->Open updater*
  - Implemented in https://github.com/nextcloud/server/blob/master/apps/updatenotification/src/components/UpdateNotification.vue
	- Clicking on *Open updater* triggers (runs) the independent Updater app (`/updater/index.php`)
	- The independent Updater app provides the Web UI independent of the code in the running instance of Server
	- When the Updater app finishes updating the code, the user is given the option to either have Updater trigger the database upgrade and app updates (by internally executing the equivalent of `occ upgrade` or to leave maintenance mode on so that the user can complete those from the command line by executing `occ upgrade` on their own)

## Development/Making Changes

Described here are both relevant aspects of the Updater itself as well as surrounding components that are integrated into Server directly.

### Updater components

For each mode the Updater supports - Web and command line - a dedicated artifact is generated. However, all common operations are located in shared code. Since the code is not shared in all cases at runtime, it's important to understand where various changes should go during development so that they end up in the appropriate places at build or check-in time.

Changes should be made to the following places in the `updater` repo:

* Shared aspects of the Updater:
	- `/Makefile`
		- `make updater.phar`
		- `make index.php`
	- `/lib/LogException.php`
	- `/lib/RecursiveDirectoryIteratorWithoutDate.php`
	- `/lib/UpdateException.php`
	- `/lib/Updater.php`	<-- core of the Updater functionality for both Web and CLI modes is implemented here
* Aspects specific to the Web Updater:
	- `/index.web.php`
	- `/Makefile`
		- `make index.php`
* Aspects specific to the CLI Updater:
	- `/updater.php`
	- `/buildVersionFile.php`
	- `/lib/CommandApplication.php`
	- `/lib/UpdateCommand.php`
	- `/Makefile`
	  - `make updater.phar`

#### Server components

Keep in mind that for the update/upgrade process there are some additional components that aren't part of the Updater app (nor necessarily part of 
`occ upgrade` itself):

* The Web-based update overview/notification page:
	- Implemented via:
		- https://github.com/nextcloud/server/blob/master/apps/updatenotification/src/components/UpdateNotification.vue
  - Populates the *Admin settings->Overview* screen with the update options available
  - Notifies when updates are available
* The CLI-based database upgrade migrations and app update handler:
	- Implemented in the `occ upgrade` command maintained as part of `server`:
		- https://github.com/nextcloud/server/blob/master/core/Command/Upgrade.php
		- https://github.com/nextcloud/server/blob/master/lib/private/Updater.php
* The Web-based upgrade page (starts automatically whenever Server has been updated if there are database migrations to run or apps to update):
	- equivalent to running `occ upgrade` directly from the command-line
	- Implemented via:
		- https://github.com/nextcloud/server/blob/master/core/templates/update.admin.php
		- https://github.com/nextcloud/server/blob/master/core/ajax/update.php
		- https://github.com/nextcloud/server/blob/master/lib/private/Updater.php
	- Handles database upgrade migrations
	- Handles app updates (i.e. for compatibility with a new major version of Server)

### Dependences needed for building

#### box

Install box: https://github.com/box-project/box/blob/main/doc/installation.md#composer

#### Tests

If you want to run the tests locally, you'll need to run them in an environment that has Nextcloud's required PHP modules installed. The various test scenarios are all available via the `make test*` (see Makefile for specifics).

### Build artifacts / What to check in

#### For Distribution

- `/updater.phar`
- `/index.php`

#### For Check-in (same + implementation changes)

- `/updater.phar`
- `/index.php`

Plus whatever has been changed in the implementation in:

- `/lib/*`
- `/index.web.php`
- `/updater.php`

#### Transient

Used during the build process but not checked in:

* Specific to the CLI Updater:
  - `/lib/Version.php`

### Testing

#### Check same code base test keeps failing

If it keeps failing on your PR, confirm your local version of `composer` is the same version in-use in the workflow runner. You can check the details of the test run and find the version currently being used (and therefore required locally) under "Setup Tools". (Hint: distro versions are typically too outdated. Remove that version and see https://getcomposer.org/download/ to install your own version).

#### CI

(to be filled in)

#### Unit/other

(to be filled in)

#### Locally/manually

(to be filled in)

## Troubleshooting

### Logging

* The Updater operates independently from Server so it has it's own log file called `updater.log` which is located in the configured `datadirectory` for the instance.
* The database upgrade migrations and app updates are not handled directly by the Updater so they're logged in the standard Nextcloud log (`nextcloud.log`)

### Web-based Updater isn't completely successful or reporting on what is going on

* Check the `updater.log`
* Try using the command line mode of Updater rather than the Web mode

### Unable to use the built-in Updater

If the built-in Updater does not function reliably for your environment, the old reliable (albeit admittedly tedious) [manual update](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/manual_upgrade.html) process may be your best alternative.
This was the primary way of keeping Nextcloud Server up-to-date before the automated Updater was developed. In addition, if Updater does not work in your environment, report the details of your situation to https://github.com/nextcloud/updater/issues so that 
consideration can be given to adapting Updater to a wider variety of environments.

### Updater != `occ upgrade`

The `occ upgrade` command runs the database migrations which adapt your existing database to the updated version Nextcloud Server that is deployed by the Updater (or via a manual update).

Despite the confusing naming - which makes sense technically, but in hindsight may not have been the best to avoid confusion - the Updater *must* run (and completely successfully) before `occ upgrade` will have anything to do. 

## Help & Contributing

- Bug reports: https://github.com/nextcloud/updater/issues (*not* for general troubleshooting assistance)
- Enhancement ideas: https://github.com/nextcloud/updater/issues
- Pull requests: https://github.com/nextcloud/updater/pulls
- Troubleshooting assistance or advice: https://help.nextcloud.com
- Code: https://github.com/nextcloud/updater/tree/master

> [!TIP]
> Since bug reports are not for technical support, you may not receive a personalized or timely response. If you suspect you may have encountered a previously unknown bug, please try to troubleshoot it in the [Help Forum](https://help.nextcloud.com) first to confirm or to uncover workarounds.

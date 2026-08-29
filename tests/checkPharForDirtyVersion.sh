#!/bin/sh
#
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

grep --text "class Version {" -A3 updater.phar | grep -v dirty

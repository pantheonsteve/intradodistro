#!/bin/sh
#
# Cloud Hook: Reinstall Standard with Lightning Layout

site="$1"
target_env="$2"

/usr/local/bin/drush9 @$site.$target_env site-install standard --account-pass=admin --yes
/usr/local/bin/drush9 @$site.$target_env pm-enable lightning_landing_page lightning_banner_block lightning_map_block --yes

# CMS Content Sync

## Configuration
Please install the module and visit the Configuration > Web Services > CMS Content Sync page for an introduction into the setup.

## Views integration - Dynamic Entity Reference
To provide a views integration, the Dynamic Entity Reference (https://www.drupal.org/project/dynamic_entity_reference) module is required.
This is required since we store references to multiple entity types within one table. The views integration can be enabled by installing
the submodule "CMS Content Sync Views (cms_content_sync_views)".

## Manual Import Dashboard - Images
To be able to show images within the Manual Import Dashboard, we recommend to use the module: Image URL Formatter (https://www.drupal.org/project/image_url_formatter).
The module allows you to use absolute URLs for the images within the Manual Import Dashboard.
By doing this it is possible to create previews of images which are actually provided by another Drupal site.

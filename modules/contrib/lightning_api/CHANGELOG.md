## 4.2.0
* Updated Simple OAuth to 3.16.
* Many changes to underlying testing infrastructure, but nothing
  that should affect users to Lightning API.

## 4.1.0
* Fixed an issue in the module info file dependencies that could lead to the
  contrib JSON:API module being used instead of the one provided by core in 8.7.
  (Issue #3052073)

## 4.0.0
* Updated Lightning Core to 4.0.0, which requires Drupal core 8.7.0 and replaces
  the contributed JSON:API module with the core version.

## 3.6.0
* There are no user-facing changes in this version.

## 3.5.0
* Updated Consumers module to 1.9 and unpin its Composer constraint.
* Allow Lightning Core 4.x and Drupal core 8.7.x.

## 3.4.0
* Updated Lightning Core to 2.13 or 3.7, which security update Drupal core to
  8.5.11 and 8.6.10, respectively.
* Security updated JSON API to 2.3 (SA-CONTRIB-2019-019).

## 3.3.0
* Updated JSON:API to 2.1, which includes support for revisions and file uploads.
  JSON:API 2.1 release notes are available at https://www.drupal.org/project/jsonapi/releases/8.x-2.1
  (Issue #2957014)

## 3.2.0
* Updated Lightning Core to 2.12 or 3.5, which security update Drupal core to
  8.5.9 and 8.6.6, respectively.
* Changes were made to the internal testing infrastructure, but nothing that 
  will affect users of Lightning API.

## 3.1.0
* Security updated JSON API to 2.0-rc4.

## 3.0.0
* Updated JSON API to 2.0.

## 2.5.0
* Allow Lightning Core 3.x and Drupal core 8.6.x.

## 2.4.0
* Updated and unpinned JSON API to ^1.22.0.
* Updated Simple OAuth to 3.8.0.
* Updated and unpinned Open API to ^1.0.0-beta1.

## 2.3.0
* Updated Simple OAuth to 3.6.

## 2.2.0
* Security updated JSON API to 1.16 (SA-CONTRIB-2018-021)

## 2.1.0
* Security updated JSON API to 1.14 (Issue #2955026 and SA-CONTRIB-2018-016)

## 2.0.0
* Updated JSON API to 1.12.
* Updated core to 8.5.x and patched Simple OAuth to make it compatible.

## 1.0.0-rc3
* Lightning API will only set up developer-specific settings when our internal
  developer tools are installed.
* Our internal Entity CRUD test no longer tries to write to config entities via
  the JSON API because it is insecure and unsupported, at least for now.

## 1.0.0-rc2
* Security updated JSON API to version 1.10.0. (SA-CONTRIB-2018-15)  
  **Note:** This update has caused parts of our Config Entity CRUD test to fail
  so you might have trouble interacting with config entities via tha API.  

## 1.0.0-rc1
* Update JSON API to 1.7.0 (Issue #2933279)

## 1.0.0-alpha1
* Initial release

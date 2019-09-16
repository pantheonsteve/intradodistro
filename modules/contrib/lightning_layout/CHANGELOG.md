## 2.1.0
* Added the Layout Builder Symmetric Translations module to provide basic
  translation support for landing pages.
* Updated Background Image Formatter to 1.9.
* Updated Layout Builder Library to 1.0-beta1.
* Updated Layout Builder Restrictions to 2.1.
* Updated Panels to 4.4.

## 2.0.0
* Panels and Panelizer have been replaced with Layout Builder. (Issue #2952620)

## 1.7.0
* Lightning Layout now supports Lightning Core 4.x (Drupal core 8.7.x).
* Added a description to an administrative link. (Issue #3034041)

## 1.6.0
* Updated Lightning Core to 2.12 or 3.5, which security update to Drupal core to
  8.5.9 and 8.6.6, respectively.
* Changes were made to the internal testing infrastructure, but nothing that
  will affect users of Lightning Layout.

## 1.5.0
* Many internal changes to testing infrastructure, but nothing that affects
  users of Lightning Layout.

## 1.4.0
* Fixed a bug which could cause Behat test failures due to a conflict with
  Content Moderation. (Issue #2989369)

## 1.3.0
* Allow Lightning Core 3.x and Drupal core 8.6.x.
* Updated logic to check for the null value in PanelizerWidget (Issue #2966924)
* Lightning Landing Page now checks for the presence of Lightning Workflow, not
  Content Moderation when opting into moderation. (Issue #2984739)

## 1.2.0
* Updated to Panelizer 4.1 and Panels 4.3.

## 1.1.0
* Entity Blocks was updated to its latest stable release and is no longer
  patched by Lightning Layout.
* Behat contexts bundled with Lightning Layout were moved into the
  `Acquia\LightningExtension\Context` namespace.

## 1.0.0
* No changes since last release.

## 1.0.0-rc1
* Fixed a configuration problem that caused an unneeded dependency on the
  Lightning profile. (Issue #2933445)

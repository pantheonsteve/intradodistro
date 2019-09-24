<?php

namespace Drupal\Tests\file_mdm_exif\Kernel;

use Drupal\file_mdm\FileMetadataInterface;
use Drupal\Tests\file_mdm\Kernel\FileMetadataManagerTestBase;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelEntrySRational;

/**
 * Tests that File Metadata EXIF plugin works properly.
 *
 * @group File Metadata
 */
class FileMetadataExifTest extends FileMetadataManagerTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'simpletest',
    'file_mdm',
    'file_mdm_exif',
    'file_test',
    'image_effects',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installConfig(['file_mdm_exif']);
  }

  /**
   * Test EXIF plugin.
   */
  public function testExifPlugin() {
    // Prepare a copy of test files.
    file_unmanaged_copy(drupal_get_path('module', 'simpletest') . '/files/image-test.jpg', 'public://', FILE_EXISTS_REPLACE);
    file_unmanaged_copy(drupal_get_path('module', 'simpletest') . '/files/image-test.png', 'public://', FILE_EXISTS_REPLACE);
    file_unmanaged_copy(drupal_get_path('module', 'file_mdm') . '/tests/files/test-exif.jpeg', 'public://', FILE_EXISTS_REPLACE);
    file_unmanaged_copy(drupal_get_path('module', 'file_mdm') . '/tests/files/test-exif.jpeg', 'temporary://', FILE_EXISTS_REPLACE);
    // The image files that will be tested.
    $image_files = [
      [
        // Pass a path instead of the URI.
        'uri' => drupal_get_path('module', 'file_mdm') . '/tests/files/test-exif.jpeg',
        'count_keys' => 48,
        'test_keys' => [
          ['Orientation', 8],
          ['orientation', 8],
          ['OrIeNtAtIoN', 8],
          ['ShutterSpeedValue', [106, 32]],
          ['ApertureValue', [128, 32]],
          [['exif', 'aperturevalue'], [128, 32]],
          [[2, 'aperturevalue'], [128, 32]],
          [['exif', 0x9202], [128, 32]],
          [[2, 0x9202], [128, 32]],
        ],
      ],
      [
        // Pass a URI.
        'uri' => 'public://test-exif.jpeg',
        'count_keys' => 48,
        'test_keys' => [
          ['Orientation', 8],
          ['ShutterSpeedValue', [106, 32]],
        ],
      ],
      [
        // Remote storage file. Let the file be copied to a local temp copy.
        'uri' => 'dummy-remote://test-exif.jpeg',
        'copy_to_temp' => TRUE,
        'count_keys' => 48,
        'test_keys' => [
          ['Orientation', 8],
          ['ShutterSpeedValue', [106, 32]],
        ],
      ],
      [
        // JPEG Image with GPS data.
        'uri' => drupal_get_path('module', 'file_mdm') . '/tests/files/1024-2006_1011_093752.jpg',
        'count_keys' => 59,
        'test_keys' => [
          ['Orientation', 1],
          ['FocalLength', [8513, 256]],
          ['GPSLatitudeRef', 'S'],
          ['GPSLatitude', [[33, 1], [51, 1], [2191, 100]]],
          ['GPSLongitudeRef', 'E'],
          ['GPSLongitude', [[151, 1], [13, 1], [1173, 100]]],
        ],
      ],
      [
        // JPEG Image with no EXIF data.
        'uri' => 'public://image-test.jpg',
        'count_keys' => 0,
        'test_keys' => [],
      ],
      [
        // TIFF image.
        'uri' => drupal_get_path('module', 'file_mdm') . '/tests/files/sample-1.tiff',
        'count_keys' => 11,
        'test_keys' => [
          ['Orientation', 1],
          ['BitsPerSample', [8, 8, 8, 8]],
        ],
      ],
      [
        // PNG should not have any data.
        'uri' => 'public://image-test.png',
        'count_keys' => 0,
        'test_keys' => [],
      ],
    ];

    $fmdm = $this->container->get('file_metadata_manager');

    // Walk through test files.
    foreach ($image_files as $image_file) {
      $file_metadata = $fmdm->uri($image_file['uri']);
      if (!$file_metadata) {
        $this->fail("File not found: {$image_file['uri']}");
        continue;
      }
      if (isset($image_file['copy_to_temp'])) {
        $file_metadata->copyUriToTemp();
      }
      $this->assertEqual($image_file['count_keys'], $this->countMetadataKeys($file_metadata, 'exif'));
      foreach ($image_file['test_keys'] as $test) {
        $entry = $file_metadata->getMetadata('exif', $test[0]);
        $this->assertEqual($test[1], $entry ? $entry['value'] : NULL);
      }
    }

    // Test loading metadata from an in-memory object.
    $file_metadata_from = $fmdm->uri($image_files[0]['uri']);
    $metadata = $file_metadata_from->getMetadata('exif');
    $new_file_metadata = $fmdm->uri('public://test-output.jpeg');
    $new_file_metadata->loadMetadata('exif', $metadata);
    $this->assertEqual($image_files[0]['count_keys'], $this->countMetadataKeys($new_file_metadata, 'exif'));
    foreach ($image_files[0]['test_keys'] as $test) {
      $entry = $file_metadata->getMetadata('exif', $test[0]);
      $this->assertEqual($test[1], $new_file_metadata->getMetadata('exif', $test[0])['value']);
    }

    // Test removing metadata.
    $fmdm->release($image_files[0]['uri']);
    $this->assertFalse($fmdm->has($image_files[0]['uri']));
    $file_metadata = $fmdm->uri($image_files[0]['uri']);
    $this->assertEqual($image_files[0]['count_keys'], $this->countMetadataKeys($file_metadata, 'exif'));
    $this->assertTrue($file_metadata->removeMetadata('exif', 'shutterspeedValue'));
    $this->assertTrue($file_metadata->removeMetadata('exif', 'apertureValue'));
    $this->assertFalse($file_metadata->removeMetadata('exif', 'bar'));
    $this->assertEqual($image_files[0]['count_keys'] - 2, $this->countMetadataKeys($file_metadata, 'exif'));
    $this->assertNull($file_metadata->getMetadata('exif', 'shutterspeedValue'));
    $this->assertNull($file_metadata->getMetadata('exif', 'apertureValue'));
    $this->assertNotNull($file_metadata->getMetadata('exif', 'orientation'));
  }

  /**
   * Test writing metadata to JPEG file.
   */
  public function testJpegExifSaveToFile() {
    $fmdm = $this->container->get('file_metadata_manager');

    // Copy test file to public://.
    file_unmanaged_copy(drupal_get_path('module', 'image_effects') . '/tests/images/portrait-painting.jpg', 'public://', FILE_EXISTS_REPLACE);
    $file_uri = 'public://portrait-painting.jpg';
    $file_metadata = $fmdm->uri($file_uri);

    // Check values via exif_read_data before operations.
    $data = @exif_read_data($file_uri);
    $this->assertEqual(8, $data['Orientation']);
    $this->assertFalse(isset($data['Artist']));
    $this->assertEqual('Canon', $data['Make']);
    $this->assertEqual(800, $data['ISOSpeedRatings']);

    // Change the Orientation tag from IFD0.
    $this->assertEqual(8, $file_metadata->getMetadata('exif', 'orientation')['value']);
    $this->assertTrue($file_metadata->setMetadata('exif', 'orientation', 4));
    $this->assertEqual(4, $file_metadata->getMetadata('exif', 'orientation')['value']);
    // Add the Artist tag to IFD0.
    $this->assertEqual(48, $this->countMetadataKeys($file_metadata, 'exif'));
    $this->assertNull($file_metadata->getMetadata('exif', 'artist'));
    $artist_tag = $this->container->get('file_mdm_exif.tag_mapper')->resolveKeyToIfdAndTag('artist');
    $artist = new PelEntryAscii($artist_tag['tag'], 'shot by foo!');
    $file_metadata->setMetadata('exif', 'artist', $artist);
    $this->assertEqual('shot by foo!', $file_metadata->getMetadata('exif', 'artist')['value']);
    $this->assertEqual(49, $this->countMetadataKeys($file_metadata, 'exif'));
    // Setting an unknown tag leads to failure.
    $this->assertFalse($file_metadata->setMetadata('exif', 'bar', 'qux'));
    // Remove the Make tag from IFD0.
    $this->assertEqual('Canon', $file_metadata->getMetadata('exif', 'make')['value']);
    $this->assertTrue($file_metadata->removeMetadata('exif', 'make'));
    $this->assertNull($file_metadata->getMetadata('exif', 'make'));
    $this->assertEqual(48, $this->countMetadataKeys($file_metadata, 'exif'));

    // Add the ImageDescription tag to IFD1.
    $this->assertNull($file_metadata->getMetadata('exif', [1, 'imagedescription']));
    $desc_tag = $this->container->get('file_mdm_exif.tag_mapper')->resolveKeyToIfdAndTag([1, 'imagedescription']);
    $desc = new PelEntryAscii($desc_tag['tag'], 'awesome!');
    $file_metadata->setMetadata('exif', [1, 'imagedescription'], $desc);
    $this->assertEqual('awesome!', $file_metadata->getMetadata('exif', [1, 'imagedescription'])['value']);
    $this->assertEqual(49, $this->countMetadataKeys($file_metadata, 'exif'));
    // Remove the Compression tag from IFD1.
    $this->assertEqual(6, $file_metadata->getMetadata('exif', [1, 'compression'])['value']);
    $this->assertTrue($file_metadata->removeMetadata('exif', [1, 'compression']));
    $this->assertNull($file_metadata->getMetadata('exif', [1, 'compression']));
    $this->assertEqual(48, $this->countMetadataKeys($file_metadata, 'exif'));

    // Add the BrightnessValue tag to EXIF.
    $this->assertNull($file_metadata->getMetadata('exif', ['exif', 'brightnessvalue']));
    $brightness_tag = $this->container->get('file_mdm_exif.tag_mapper')->resolveKeyToIfdAndTag(['exif', 'brightnessvalue']);
    $brightness = new PelEntrySRational($brightness_tag['tag'], [12, 4]);
    $file_metadata->setMetadata('exif', ['exif', 'brightnessvalue'], $brightness);
    $this->assertEqual('12/4', $file_metadata->getMetadata('exif', ['exif', 'brightnessvalue'])['text']);
    $this->assertEqual(49, $this->countMetadataKeys($file_metadata, 'exif'));
    // Remove the ISOSpeedRatings tag from EXIF.
    $this->assertEqual(800, $file_metadata->getMetadata('exif', ['exif', 'isospeedratings'])['value']);
    $this->assertTrue($file_metadata->removeMetadata('exif', ['exif', 'isospeedratings']));
    $this->assertNull($file_metadata->getMetadata('exif', ['exif', 'isospeedratings']));
    $this->assertEqual(48, $this->countMetadataKeys($file_metadata, 'exif'));

    // Add the RelatedImageFileFormat tag to INTEROP.
    $this->assertNull($file_metadata->getMetadata('exif', ['interop', 'RelatedImageFileFormat']));
    $ff_tag = $this->container->get('file_mdm_exif.tag_mapper')->resolveKeyToIfdAndTag(['interop', 'RelatedImageFileFormat']);
    $ff = new PelEntryAscii($ff_tag['tag'], 'qux');
    $file_metadata->setMetadata('exif', ['interop', 'RelatedImageFileFormat'], $ff);
    $this->assertEqual('qux', $file_metadata->getMetadata('exif', ['interop', 'RelatedImageFileFormat'])['text']);
    $this->assertEqual(49, $this->countMetadataKeys($file_metadata, 'exif'));
    // Remove the InteroperabilityIndex tag from INTEROP.
    $this->assertEqual('R98', $file_metadata->getMetadata('exif', ['interop', 'InteroperabilityIndex'])['value']);
    $this->assertTrue($file_metadata->removeMetadata('exif', ['interop', 'InteroperabilityIndex']));
    $this->assertNull($file_metadata->getMetadata('exif', ['interop', 'InteroperabilityIndex']));
    $this->assertEqual(48, $this->countMetadataKeys($file_metadata, 'exif'));

    // Add Longitude/Latitude tags to GPS.
    $this->assertNull($file_metadata->getMetadata('exif', 'GPSLatitudeRef'));
    $this->assertNull($file_metadata->getMetadata('exif', 'GPSLatitude'));
    $this->assertNull($file_metadata->getMetadata('exif', 'GPSLongitudeRef'));
    $this->assertNull($file_metadata->getMetadata('exif', 'GPSLongitude'));
    $atr_tag = $this->container->get('file_mdm_exif.tag_mapper')->resolveKeyToIfdAndTag('GPSLatitudeRef');
    $at_tag = $this->container->get('file_mdm_exif.tag_mapper')->resolveKeyToIfdAndTag('GPSLatitude');
    $otr_tag = $this->container->get('file_mdm_exif.tag_mapper')->resolveKeyToIfdAndTag('GPSLongitudeRef');
    $ot_tag = $this->container->get('file_mdm_exif.tag_mapper')->resolveKeyToIfdAndTag('GPSLongitude');
    $atr = new PelEntryAscii($atr_tag['tag'], 'N');
    $at = new PelEntryRational($at_tag['tag'], [46, 1], [37, 1], [59448, 10000]);
    $otr = new PelEntryAscii($otr_tag['tag'], 'E');
    $ot = new PelEntryRational($ot_tag['tag'], [12, 1], [17, 1], [488112, 10000]);
    $file_metadata->setMetadata('exif', 'GPSLatitudeRef', $atr);
    $file_metadata->setMetadata('exif', 'GPSLatitude', $at);
    $file_metadata->setMetadata('exif', 'GPSLongitudeRef', $otr);
    $file_metadata->setMetadata('exif', 'GPSLongitude', $ot);
    $this->assertEqual('N', $file_metadata->getMetadata('exif', 'GPSLatitudeRef')['text']);
    $this->assertNotNull($file_metadata->getMetadata('exif', 'GPSLatitude')['text']);
    $this->assertEqual('E', $file_metadata->getMetadata('exif', 'GPSLongitudeRef')['text']);
    $this->assertNotNull($file_metadata->getMetadata('exif', 'GPSLongitude')['text']);
    $this->assertEqual(52, $this->countMetadataKeys($file_metadata, 'exif'));

    // Save metadata to file.
    $this->assertTrue($file_metadata->saveMetadataToFile('exif'));

    // Check results via exif_read_data.
    $data = @exif_read_data($file_uri);
    $this->assertEqual(4, $data['Orientation']);
    $this->assertEqual('shot by foo!', $data['Artist']);
    $this->assertFalse(isset($data['Make']));
    $this->assertEqual('12/4', $data['BrightnessValue']);
    $this->assertFalse(isset($data['ISOSpeedRatings']));
    $this->assertEqual('qux', $data['RelatedFileFormat']);
    $this->assertFalse(isset($data['InterOperabilityIndex']));
    $this->assertEqual('N', $data['GPSLatitudeRef']);
    $this->assertEqual(['46/1', '37/1', '59448/10000'], $data['GPSLatitude']);
    $this->assertEqual('E', $data['GPSLongitudeRef']);
    $this->assertEqual(['12/1', '17/1', '488112/10000'], $data['GPSLongitude']);

    // Test writing metadata to a file that has no EXIF info.
    file_unmanaged_copy(drupal_get_path('module', 'simpletest') . '/files/image-2.jpg', 'public://', FILE_EXISTS_REPLACE);
    $test_2 = $fmdm->uri('public://image-2.jpg');
    $this->assertEqual(0, $this->countMetadataKeys($test_2, 'exif'));
    // Load EXIF metadata from previous file processed.
    $test_2->loadMetadata('exif', $file_metadata->getMetadata('exif'));
    // Save metadata to file.
    $this->assertTrue($test_2->saveMetadataToFile('exif'));
    $this->assertEqual(52, $this->countMetadataKeys($test_2, 'exif'));
    // Check results via exif_read_data.
    $data = @exif_read_data('public://image-2.jpg');
    $this->assertEqual(4, $data['Orientation']);
    $this->assertEqual('shot by foo!', $data['Artist']);
    $this->assertEqual('12/4', $data['BrightnessValue']);
    $this->assertEqual('qux', $data['RelatedFileFormat']);
    $this->assertEqual('N', $data['GPSLatitudeRef']);
    $this->assertEqual(['46/1', '37/1', '59448/10000'], $data['GPSLatitude']);
    $this->assertEqual('E', $data['GPSLongitudeRef']);
    $this->assertEqual(['12/1', '17/1', '488112/10000'], $data['GPSLongitude']);

    // Check that after save, file metadata is retrieved from file first time,
    // then from cache in further requests.
    $file_metadata = NULL;
    $this->assertTrue($fmdm->release($file_uri));
    $file_metadata = $fmdm->uri($file_uri);
    $this->assertEqual(52, $this->countMetadataKeys($file_metadata, 'exif'));
    $this->assertIdentical(FileMetadataInterface::LOADED_FROM_FILE, $file_metadata->isMetadataLoaded('exif'));
    $file_metadata = NULL;
    $this->assertTrue($fmdm->release($file_uri));
    $file_metadata = $fmdm->uri($file_uri);
    $this->assertEqual(52, $this->countMetadataKeys($file_metadata, 'exif'));
    $this->assertIdentical(FileMetadataInterface::LOADED_FROM_CACHE, $file_metadata->isMetadataLoaded('exif'));
  }

  /**
   * Test writing metadata to TIFF file.
   */
  public function testTiffExifSaveToFile() {
    $fmdm = $this->container->get('file_metadata_manager');

    // Copy test file to public://.
    file_unmanaged_copy(drupal_get_path('module', 'file_mdm') . '/tests/files/sample-1.tiff', 'public://', FILE_EXISTS_REPLACE);
    $file_uri = 'public://sample-1.tiff';
    $file_metadata = $fmdm->uri($file_uri);

    // Check values via exif_read_data before operations.
    $data = @exif_read_data($file_uri);
    $this->assertEqual(1, $data['Orientation']);
    $this->assertEqual(2, $data['PhotometricInterpretation']);

    // Change tags from IFD0.
    $this->assertEqual(1, $file_metadata->getMetadata('exif', 'orientation')['value']);
    $this->assertTrue($file_metadata->setMetadata('exif', 'orientation', 4));
    $this->assertEqual(4, $file_metadata->getMetadata('exif', 'orientation')['value']);
    $this->assertEqual(2, $file_metadata->getMetadata('exif', 'PhotometricInterpretation')['value']);
    $this->assertTrue($file_metadata->setMetadata('exif', 'PhotometricInterpretation', 4));
    $this->assertEqual(4, $file_metadata->getMetadata('exif', 'PhotometricInterpretation')['value']);

    // Save metadata to file.
    $this->assertTrue($file_metadata->saveMetadataToFile('exif'));

    // Check results via exif_read_data.
    $data = @exif_read_data($file_uri);
    $this->assertEqual(4, $data['Orientation']);
    $this->assertEqual(4, $data['PhotometricInterpretation']);
  }

}

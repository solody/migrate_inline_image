<?php

namespace Drupal\migrate_inline_image\Plugin\migrate\process;

use Drupal\file\Entity\File;
use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Image;

/**
 * Provides a 'SaveInlineImage' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "save_inline_image"
 * )
 */
class SaveInlineImage extends ProcessPluginBase {

  private $batId;
  private $savePath;

  public function __construct(array $configuration, $plugin_id, $plugin_definition)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!isset($this->configuration['image_file_source_path'])) {
      throw new MigrateException('"image_file_source_path" must be configured.');
    }

    if (!isset($this->configuration['image_file_save_destination'])) {
      throw new MigrateException('"image_file_save_destination" must be configured.');
    }

    $this->batId = \Drupal::service('uuid')->generate();
    $this->savePath = $this->configuration['image_file_save_destination'].'-'.$this->batId;

    $this->createPath($this->savePath);
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    // 从html中分析img标签
    $baseUri = 'http://localhost';
    $crawler = new Crawler($value, $baseUri);

    /** @var Image[] $imgs */
    $imgs = $crawler->filter('img')->images();

    foreach ($imgs as $img) {
      $image_url = str_replace($baseUri, '', $img->getUri());
      $node = $img->getNode();
      $file = $this->saveImage($this->configuration['image_file_source_path'] . '/' . $image_url);
      $node->setAttribute('src', $file->createFileUrl());
      $node->setAttribute('data-entity-type', 'file');
      $node->setAttribute('data-entity-uuid', $file->uuid());
    }

    return $crawler->filter('body')->html();
  }

  /**
   * @param $source_path
   * @return File
   */
  private function saveImage($source_path) {

    $info = pathinfo($source_path);
    $file = file_save_data(file_get_contents($source_path),$this->savePath.'/'.$info['basename']);

    if ($file === false) throw new MigrateException('Fail on saving file ' . $source_path);

    return $file;
  }

  private function createPath($path) {
    if (!file_exists($path)) {
      mkdir($path, 0777, true);
    }
  }
}

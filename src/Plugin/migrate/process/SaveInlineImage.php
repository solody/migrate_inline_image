<?php

namespace Drupal\migrate_inline_image\Plugin\migrate\process;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\Exception\FileException;
use Drupal\file\FileRepositoryInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Provides a 'SaveInlineImage' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "save_inline_image"
 * )
 */
class SaveInlineImage extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The unique bat id.
   *
   * @var string
   */
  private string $batId;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuidService;

  /**
   * The UUID service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected FileRepositoryInterface $fileRepository;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    UuidInterface $uuid_service,
    FileRepositoryInterface $file_repository
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!isset($this->configuration['image_file_source_path'])) {
      throw new MigrateException('"image_file_source_path" must be configured.');
    }

    if (!isset($this->configuration['image_file_save_destination'])) {
      throw new MigrateException('"image_file_save_destination" must be configured.');
    }

    $this->uuidService = $uuid_service;
    $this->fileRepository = $file_repository;

    $this->batId = $this->uuidService->generate();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uuid'),
      $container->get('file.repository')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    // Find all img tag from the html.
    $baseUri = 'http://localhost';
    $crawler = new Crawler($value, $baseUri);

    $images = $crawler->filter('img')->images();

    foreach ($images as $img) {
      $image_url = str_replace($baseUri, '', $img->getUri());
      $node = $img->getNode();

      $file = $this->saveImage(
        $row->get($this->configuration['image_file_source_path']) . $image_url,
        $row->get($this->configuration['image_file_save_destination']));
      $node->setAttribute('src', $file->createFileUrl());
      $node->setAttribute('data-entity-type', 'file');
      $node->setAttribute('data-entity-uuid', $file->uuid());
    }

    return $crawler->filter('body')->html();
  }

  /**
   * Copy the image to the drupal system.
   *
   * @param string $source_path
   *   Source file.
   * @param string $save_path
   *   Copy destination.
   *
   * @return \Drupal\file\Entity\File
   *   The copied file.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function saveImage(string $source_path, string $save_path): File {
    $save_path = $save_path . 'bat-' . $this->batId;
    $this->createPath($save_path);

    $info = pathinfo($source_path);

    try {
      return $this->fileRepository->writeData(
        file_get_contents($source_path),
        $save_path . '/' . $info['basename']
      );
    }
    catch (FileException $exception) {
      throw new MigrateException('Fail on saving file ' . $source_path . ': ' . $exception->getMessage());
    }

  }

  /**
   * Create a path.
   */
  private function createPath($path) {
    if (!file_exists($path)) {
      mkdir($path, 0777, TRUE);
    }
  }

}

<?php
namespace TYPO3\Flow\Flysystem\Resource;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Resource\Collection;
use TYPO3\Flow\Resource\CollectionInterface;
use TYPO3\Flow\Resource\Resource;
use TYPO3\Flow\Resource\ResourceMetaDataInterface;
use TYPO3\Flow\Resource\Target\Exception;

/**
 * A target which publishes resources to a specific directory in a file system and can provide a public URI.
 * This is meant to be extended because the generic Flysystem API does not provide means to aquire a public URI.
 */
abstract class Target implements \TYPO3\Flow\Resource\Target\TargetInterface {

	/**
	 * @var array
	 */
	protected $options = array();

	/**
	 * Name which identifies this publishing target
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The path (in a filesystem) where resources are published to
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Sets the flysystem driver. A specific implementation can set this to a default.
	 *
	 * @var string
	 */
	protected $driver;

	/**
	 * Driver specific options for the flysystem connection. Please refer to the factories and flysystem documentation for details.
	 *
	 * @var array
	 */
	protected $driverOptions;

	/**
	 * Publicly accessible web URI which points to the root path of this target.
	 * Can be relative to website's base Uri, for example "_Resources/MySpecialTarget/"
	 *
	 * @var string
	 */
	protected $baseUri = '';

	/**
	 * If the generated URI path segment containing the sha1 should be divided into multiple segments
	 *
	 * @var boolean
	 */
	protected $subdivideHashPathSegment = TRUE;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Resource\ResourceRepository
	 */
	protected $resourceRepository;

	/**
	 * @var \League\Flysystem\Filesystem
	 */
	protected $filesystem;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Flysystem\FilesystemFactoryInterface
	 */
	protected $filesystemFactory;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Log\SystemLoggerInterface
	 */
	protected $systemLogger;


	/**
	 * Constructor
	 *
	 * @param string $name Name of this target instance, according to the resource settings
	 * @param array $options Options for this target
	 */
	public function __construct($name, array $options = array()) {
		$this->name = $name;
		$this->options = $options;
	}

	/**
	 * Set the Flysystem Filesystem for this target. Generally this is meant to be done via a factory.
	 *
	 * @param \League\Flysystem\Filesystem $filesystem
	 */
	public function setFilesystem(\League\Flysystem\Filesystem $filesystem) {
		$this->filesystem = $filesystem;
		$this->filesystem->createDir($this->path);
	}

	/**
	 * Initializes this resource publishing target
	 *
	 * @return void
	 * @throws \TYPO3\Flow\Resource\Exception
	 */
	public function initializeObject() {
		foreach ($this->options as $key => $value) {
			switch ($key) {
				case 'path':
				case 'driver':
				case 'driverOptions':
					$this->$key = $value;
					break;
				case 'subdivideHashPathSegment':
					$this->subdivideHashPathSegment = (boolean)$value;
					break;
			}
		}

		if ($this->driver === NULL || !is_array($this->driverOptions)) {
			throw new Exception(sprintf('The target "%s", needs a "driver" and "driverOptions" set to be initialized', $this->name));
		}

		$this->setFilesystem($this->filesystemFactory->create($this->driver, $this->options['driverOptions']));
	}

	/**
	 * Returns the name of this target instance
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Publishes the whole collection to this target
	 *
	 * @param \TYPO3\Flow\Resource\Collection $collection The collection to publish
	 * @return void
	 */
	public function publishCollection(Collection $collection) {
		foreach ($collection->getObjects() as $object) {
			/** @var \TYPO3\Flow\Resource\Storage\Object $object */
			$this->publishFile($object->getDataUri(), $this->getRelativePublicationPathAndFilename($object));
		}
	}

	/**
	 * Publishes the given persistent resource from the given storage
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource The resource to publish
	 * @param CollectionInterface $collection The collection the given resource belongs to
	 * @return void
	 * @throws Exception
	 */
	public function publishResource(Resource $resource, CollectionInterface $collection) {
		$this->publishStream($resource->getStream(), $this->getRelativePublicationPathAndFilename($resource));
	}

	/**
	 * Unpublishes the given persistent resource
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource The resource to unpublish
	 * @return void
	 */
	public function unpublishResource(Resource $resource) {
		$resources = $this->resourceRepository->findSimilarResources($resource);
		if (count($resources) > 1) {
			return;
		}
		$this->unpublishFile($this->getRelativePublicationPathAndFilename($resource));
	}

	/**
	 * Returns the web accessible URI pointing to the given static resource, to be implemented by specific implementations.
	 *
	 * @param string $relativePathAndFilename Relative path and filename of the static resource
	 * @return string The URI
	 */
	public function getPublicStaticResourceUri($relativePathAndFilename) {}

	/**
	 * Returns the web accessible URI pointing to the specified persistent resource, to be implemented by specific implementations.
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource Resource object
	 * @return string The URI
	 * @throws Exception
	 */
	public function getPublicPersistentResourceUri(Resource $resource) {}

	/**
	 * Publishes the specified source file to this target, with the given relative path.
	 *
	 * @param resource $sourceHandle An URI or path / filename pointing to the data to publish
	 * @param string $relativeTargetPathAndFilename relative path and filename in the target directory
	 * @return void
	 * @throws Exception
	 * @throws \Exception
	 * @throws \TYPO3\Flow\Utility\Exception
	 */
	protected function publishStream($sourceHandle, $relativeTargetPathAndFilename) {
		$targetPathAndFilename = $this->path . $relativeTargetPathAndFilename;
		if (!$this->filesystem->has($targetPathAndFilename)) {
			$this->filesystem->writeStream($targetPathAndFilename, $sourceHandle);
			$this->systemLogger->log(sprintf('FileSystemTarget: Published file. (target: %s, file: %s)', $this->name, $relativeTargetPathAndFilename), LOG_DEBUG);
		}
	}

	/**
	 * Removes the specified target file from the public directory
	 *
	 * This method fails silently if the given file could not be unpublished or already didn't exist anymore.
	 *
	 * @param string $relativeTargetPathAndFilename relative path and filename in the target directory
	 * @return void
	 */
	protected function unpublishFile($relativeTargetPathAndFilename) {
		$targetPathAndFilename = $this->path . $relativeTargetPathAndFilename;

		if (!$this->filesystem->has($targetPathAndFilename)) {
			return;
		}
		$this->filesystem->delete($targetPathAndFilename);
		// TODO: remove leftover empty directories. We cannot be sure that they are empty and on a remote storage it might be costly to check...
	}

	/**
	 * Determines and returns the relative path and filename for the given Storage Object or Resource. If the given
	 * object represents a persistent resource, its own relative publication path will be empty. If the given object
	 * represents a static resources, it will contain a relative path.
	 *
	 * No matter which kind of resource, persistent or static, this function will return a sub directory structure
	 * if no relative publication path was defined in the given object.
	 *
	 * @param ResourceMetaDataInterface $object Resource or Storage Object
	 * @return string The relative path and filename, for example "c828d/0f88c/e197b/e1aff/7cc2e/5e86b/12442/41ac6/MyPicture.jpg" (if subdivideHashPathSegment is on) or "c828d0f88ce197be1aff7cc2e5e86b1244241ac6/MyPicture.jpg" (if it's off)
	 */
	protected function getRelativePublicationPathAndFilename(ResourceMetaDataInterface $object) {
		if ($object->getRelativePublicationPath() !== '') {
			$pathAndFilename = $object->getRelativePublicationPath() . $object->getFilename();
		} else {
			if ($this->subdivideHashPathSegment) {
				$pathAndFilename = wordwrap($object->getSha1(), 5, '/', TRUE) . '/' . $object->getFilename();
			} else {
				$pathAndFilename = $object->getSha1() . '/' . $object->getFilename();
			}
		}
		return $pathAndFilename;
	}
}

<?php
namespace TYPO3\Flow\Flysystem\Resource\Storage;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Resource\Storage\StorageInterface;
use TYPO3\Flow\Resource\Storage\WritableStorageInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Resource\Resource;
use TYPO3\Flow\Flysystem\Exception;

/**
 * A resource storage based on flysystem abstraction.
 * You probably will want to use a specific implementation like the one in TYPO3.Flow.Dropbox
 *
 */
class GenericWritableStorage implements StorageInterface, WritableStorageInterface {

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var array
	 */
	protected $options;

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
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Flysystem\FilesystemFactoryInterface
	 */
	protected $filesystemFactory;

	/**
	 * @var \League\Flysystem\Filesystem
	 */
	protected $filesystem;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Utility\Environment
	 */
	protected $environment;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Resource\ResourceRepository
	 */
	protected $resourceRepository;

	/**
	 * Constructor
	 *
	 * @param string $name Name of this storage instance, according to the resource settings
	 * @param array $options Options for this storage
	 */
	public function __construct($name, array $options = array()) {
		$this->name = $name;
		$this->options = $options;
	}

	/**
	 * Set the Flysystem Filesystem for this storage. Generally this is meant to be done via a factory.
	 *
	 * @param \League\Flysystem\Filesystem $filesystem
	 */
	public function setFilesystem(\League\Flysystem\Filesystem $filesystem) {
		$this->filesystem = $filesystem;
	}

	/**
	 * Initializes this resource storage.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function initializeObject() {
		foreach ($this->options as $key => $value) {
			switch ($key) {
				case 'driver':
				case 'driverOptions':
					$this->$key = $value;
					break;
			}
		}

		if ($this->driver === NULL || !is_array($this->driverOptions)) {
			throw new Exception(sprintf('The storage "%s", needs a "driver" and "driverOptions" set to be initialized', $this->name));
		}

		$this->setFilesystem($this->filesystemFactory->create($this->driver, $this->options['driverOptions']));
	}

	/**
	 * Imports a resource (file) from the given URI or PHP resource stream into this storage.
	 *
	 * On a successful import this method returns a Resource object representing the newly
	 * imported persistent resource.
	 *
	 * @param string | resource $source The URI (or local path and filename) or the PHP resource stream to import the resource from
	 * @param string $collectionName Name of the collection the new Resource belongs to
	 * @throws Exception
	 * @return Resource A resource object representing the imported resource
	 */
	public function importResource($source, $collectionName) {
		$temporaryTargetPathAndFilename = $this->environment->getPathToTemporaryDirectory() . uniqid('TYPO3_Flow_ResourceImport_');

		if (is_resource($source)) {
			try {
				$target = fopen($temporaryTargetPathAndFilename, 'wb');
				while (!feof($target)) {
					stream_copy_to_stream($source, $target);
				}
				fclose($target);
			} catch (\Exception $e) {
				throw new Exception(sprintf('Could import the content stream to temporary file "%s".', $temporaryTargetPathAndFilename), 1380880079);
			}
		} else {
			$pathInfo = pathinfo($source);
			$filename = $pathInfo['basename'];
			try {
				copy($source, $temporaryTargetPathAndFilename);
			} catch (\Exception $e) {
				throw new Exception(sprintf('Could not copy the file from "%s" to temporary file "%s".', $source, $temporaryTargetPathAndFilename), 1375198876);
			}
		}

		$sha1Hash = sha1_file($temporaryTargetPathAndFilename);
		$filesize = filesize($temporaryTargetPathAndFilename);
		$md5Hash = md5_file($temporaryTargetPathAndFilename);
		$finalTargetPathAndFilename = $this->getStoragePathAndFilenameByHash($sha1Hash);
		$this->moveFileToStorage($temporaryTargetPathAndFilename, $finalTargetPathAndFilename);

		if (!isset($filename)) {
			$filename = '';
		}
		return $this->createResource($filename, $filesize, $collectionName, $sha1Hash, $md5Hash);
	}

	/**
	 * Imports a resource from the given string content into this storage.
	 *
	 * On a successful import this method returns a Resource object representing the newly
	 * imported persistent resource.
	 *
	 * The specified filename will be used when presenting the resource to a user. Its file extension is
	 * important because the resource management will derive the IANA Media Type from it.
	 *
	 * @param string $content The actual content to import
	 * @return Resource A resource object representing the imported resource
	 * @param string $collectionName Name of the collection the new Resource belongs to
	 * @param string $filename The filename to use for the newly generated resource
	 * @return Resource A resource object representing the imported resource
	 * @throws Exception
	 */
	public function importResourceFromContent($content, $collectionName, $filename) {
		$temporaryTargetPathAndFilename = $this->environment->getPathToTemporaryDirectory() . uniqid('TYPO3_Flow_ResourceImport_');
		try {
			file_put_contents($temporaryTargetPathAndFilename, $content);
		} catch (\Exception $e) {
			throw new Exception(sprintf('Could import the content stream to temporary file "%s".', $temporaryTargetPathAndFilename), 1381156098);
		}

		$sha1Hash = sha1_file($temporaryTargetPathAndFilename);
		$filesize = filesize($temporaryTargetPathAndFilename);
		$md5Hash = md5_file($temporaryTargetPathAndFilename);
		$finalTargetPathAndFilename = $this->getStoragePathAndFilenameByHash($sha1Hash);
		$this->moveFileToStorage($temporaryTargetPathAndFilename, $finalTargetPathAndFilename);

		return $this->createResource($filename, $filesize, $collectionName, $sha1Hash, $md5Hash);
	}

	/**
	 * Imports a resource (file) as specified in the given upload info array as a
	 * persistent resource.
	 *
	 * On a successful import this method returns a Resource object representing
	 * the newly imported persistent resource.
	 *
	 * @param array $uploadInfo An array detailing the resource to import (expected keys: name, tmp_name)
	 * @param string $collectionName Name of the collection this uploaded resource should be part of
	 * @return Resource A resource object representing the imported resource
	 * @throws \Exception
	 */
	public function importUploadedResource(array $uploadInfo, $collectionName) {
		$pathInfo = pathinfo($uploadInfo['name']);
		$temporaryTargetPathAndFilename = $uploadInfo['tmp_name'];

		if (!file_exists($temporaryTargetPathAndFilename)) {
			throw new \Exception(sprintf('The temporary file "%s" of the file upload does not exist (anymore).', $temporaryTargetPathAndFilename), 1375198998);
		}

		$openBasedirEnabled = (boolean)ini_get('open_basedir');
		if ($openBasedirEnabled === TRUE) {
			// Move uploaded file to a readable folder before trying to read sha1 value of file
			$newTemporaryTargetPathAndFilename = $this->environment->getPathToTemporaryDirectory() . 'ResourceUpload.' . uniqid() . '.tmp';
			if (move_uploaded_file($temporaryTargetPathAndFilename, $newTemporaryTargetPathAndFilename) === FALSE) {
				throw new \Exception(sprintf('The uploaded file "%s" could not be moved to the temporary location "%s".', $temporaryTargetPathAndFilename, $newTemporaryTargetPathAndFilename), 1375199056);
			}
			$temporaryTargetPathAndFilename = $newTemporaryTargetPathAndFilename;
		}

		$sha1Hash = sha1_file($temporaryTargetPathAndFilename);
		$filesize = filesize($temporaryTargetPathAndFilename);
		$md5Hash = md5_file($temporaryTargetPathAndFilename);
		$finalTargetPathAndFilename = $this->getStoragePathAndFilenameByHash($sha1Hash);
		$this->moveFileToStorage($temporaryTargetPathAndFilename, $finalTargetPathAndFilename);

		return $this->createResource($pathInfo['basename'], $filesize, $collectionName, $sha1Hash, $md5Hash);
	}

	/**
	 * Moves a given local (temporary) file to the storage.
	 *
	 * @param string $temporaryTargetPathAndFilename
	 * @param string $finalTargetPathAndFilename
	 * @return boolean
	 * @throws \Exception
	 */
	protected function moveFileToStorage($temporaryTargetPathAndFilename, $finalTargetPathAndFilename) {
		if (!$this->filesystem->has($finalTargetPathAndFilename)) {
			$this->filesystem->createDir(dirname($finalTargetPathAndFilename));
			$stream = fopen($temporaryTargetPathAndFilename, 'r');
			if (!$this->filesystem->writeStream($finalTargetPathAndFilename, $stream)) {
				throw new \Exception(sprintf('The temporary file of the file upload could not be moved to the final target "%s".', $finalTargetPathAndFilename), 1375199110);
			}
		}

		return TRUE;
	}

	/**
	 * Creates a resource object with the basic properties needed.
	 *
	 * @param string $filename
	 * @param integer $filesize
	 * @param string $collectionName
	 * @param string $sha1Hash
	 * @param string $md5Hash
	 * @return Resource
	 */
	protected function createResource($filename, $filesize, $collectionName, $sha1Hash, $md5Hash) {
		$resource = new Resource();
		$resource->setFilename($filename);
		$resource->setFileSize($filesize);
		$resource->setCollectionName($collectionName);
		$resource->setSha1($sha1Hash);
		$resource->setMd5($md5Hash);

		return $resource;
	}

	/**
	 * Deletes the storage data related to the given Resource object
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource The Resource to delete the storage data of
	 * @return boolean TRUE if removal was successful
	 */
	public function deleteResource(Resource $resource) {
		$pathAndFilename = $this->getStoragePathAndFilenameByHash($resource->getSha1());
		try {
			$result = $this->filesystem->delete($pathAndFilename);
		} catch (\Exception $e) {
			return FALSE;
		}

		return $result;
	}

	/**
	 * Returns the instance name of this storage
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Returns a stream handle which can be used internally to open / copy the given resource
	 * stored in this storage.
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource The resource stored in this storage
	 * @return resource | boolean A stream (for example the full path and filename) leading to the resource file or FALSE if it does not exist
	 */
	public function getStreamByResource(Resource $resource) {
		$filepath = $this->getStoragePathAndFilenameByHash($resource->getSha1());
		return $this->filesystem->readStream($filepath);
	}

	/**
	 * Returns a stream handle which can be used internally to open / copy the given resource
	 * stored in this storage.
	 **
	 * @param string $relativePath A path relative to the storage root, for example "MyFirstDirectory/SecondDirectory/Foo.css"
	 * @return resource | boolean A URI (for example the full path and filename) leading to the resource file or FALSE if it does not exist
	 */
	public function getStreamByResourcePath($relativePath) {
		return $this->filesystem->readStream($relativePath);
	}

	/**
	 * Retrieve all Objects stored in this storage.
	 *
	 * @return array<\TYPO3\Flow\Resource\Storage\Object>
	 * @api
	 */
	public function getObjects() {
		// TODO: Implement
		$objects = array();
		$contents = $this->filesystem->listWith(['mimetype', 'size', 'timestamp'], '', TRUE);
		foreach ($contents as $file) {
			$object = new \TYPO3\Flow\Resource\Storage\Object();
			$object->setFilename(basename($file['path']));
			$object->setFileSize($file['size']);
			$objects[] = $object;
		}

		return $objects;
	}

	/**
	 * Retrieve all Objects stored in this storage, filtered by the given collection name
	 *
	 * @param \TYPO3\Flow\Resource\CollectionInterface $collection
	 * @return array<\TYPO3\Flow\Resource\Storage\Object>
	 * @api
	 */
	public function getObjectsByCollection(\TYPO3\Flow\Resource\CollectionInterface $collection) {
		$objects = array();
		foreach ($this->resourceRepository->findByCollection($collection) as $resource) {
			/** @var \TYPO3\Flow\Resource\Resource $resource */
			$object = new \TYPO3\Flow\Resource\Storage\Object();
			$object->setFilename($resource->getFilename());
			$object->setSha1($resource->getSha1());
			$object->setMd5($resource->getMd5());
			$object->setFileSize($resource->getFileSize());
			$object->setDataUri($this->getStoragePathAndFilenameByHash($resource->getSha1()));
			$objects[] = $object;
		}
		return $objects;
	}

	/**
	 * Determines and returns the absolute path and filename for a storage file identified by the given SHA1 hash.
	 *
	 * This function assures a nested directory structure in order to avoid thousands of files in a single directory
	 * which may result in performance problems in older file systems such as ext2, ext3 or NTFS.
	 *
	 * This specialized version for the Writable File System Storage will automatically migrate resource data
	 * stored in a legacy structure from applications based on Flow < 2.1.
	 *
	 * @param string $sha1Hash The SHA1 hash identifying the stored resource
	 * @return string The path and filename, for example "/var/www/mysite.com/Data/Persistent/c828d/0f88c/e197b/e1aff/7cc2e/5e86b/12442/41ac6/c828d0f88ce197be1aff7cc2e5e86b1244241ac6"
	 * @throws Exception
	 */
	protected function getStoragePathAndFilenameByHash($sha1Hash) {
		return wordwrap($sha1Hash, 5, '/', TRUE) . '/' . $sha1Hash;
	}
}


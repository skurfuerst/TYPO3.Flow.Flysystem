<?php
namespace TYPO3\Flow\Flysystem\Resource\Target;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use League\Flysystem\AwsS3v3\AwsS3Adapter;
use TYPO3\Flow\Flysystem\Resource\Target;
use TYPO3\Flow\Resource\Resource;

class S3Target extends Target {

	protected $driver = 'S3';

	protected $subdivideHashPathSegment = FALSE;

	public function initializeObject() {
		parent::initializeObject();

		// S3 does not support a "path".
		$this->path = '';
	}

	/**
	 * Returns the web accessible URI pointing to the given static resource, to be implemented by specific implementations.
	 *
	 * @param string $relativePathAndFilename Relative path and filename of the static resource
	 * @return string The URI
	 */
	public function getPublicStaticResourceUri($relativePathAndFilename) {
		/* @var $adapter AwsS3Adapter */
		$adapter = $this->filesystem->getAdapter();
		$adapter->getClient()->getObjectUrl($this->driverOptions['s3bucket'], $this->driverOptions['s3prefix'] .'/' . $relativePathAndFilename);
	}

	/**
	 * Returns the web accessible URI pointing to the specified persistent resource, to be implemented by specific implementations.
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource Resource object
	 * @return string The URI
	 * @throws Exception
	 */
	public function getPublicPersistentResourceUri(Resource $resource) {
		/* @var $adapter AwsS3Adapter */
		$adapter = $this->filesystem->getAdapter();
		return $adapter->getClient()->getObjectUrl($this->driverOptions['s3bucket'], $this->driverOptions['s3prefix'] .'/' . $this->getRelativePublicationPathAndFilename($resource));
	}
}
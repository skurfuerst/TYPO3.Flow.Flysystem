<?php
namespace TYPO3\Flow\Flysystem;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use League\Flysystem\Filesystem;
use TYPO3\Flow\Annotations as Flow;

/**
 * A factory for flysystem integrations. This basic factory will only take care of simple adapters like "local" and "ftp".
 * Everything else should be extended in a separate package, see TYPO3.Flow.Dropbox for an example. Either you just implement the
 * interface and do what you like in the create method or you extend this class and create a method in the
 * form of "create{Drivername}Connection" that will get the options configured (like createDropboxConnection).
 * The matching driverName would be "dropbox", first character is uppercased.
 *
 * @Flow\Scope("singleton")
 */
class FilesystemFactory implements FilesystemFactoryInterface {

	/**
	 * A simple map of existing connections for reuse.
	 *
	 * @var array
	 */
	protected $connections = array();

	/**
	 * @param string $driverName
	 * @param array $options
	 *
	 * @return Filesystem
	 */
	public function create($driverName, array $options) {
		$connectionIdentifier = $this->getConnectionIdentifier($driverName, $options);

		if (!isset($this->connections[$connectionIdentifier])) {
			$createConnectionMethod = 'create' . ucfirst($driverName) . 'Connection';
			if (is_callable(array($this, $createConnectionMethod))) {
				$this->connections[$connectionIdentifier] = $this->$createConnectionMethod($options);
			}
		}

		return $this->connections[$connectionIdentifier];
	}

	/**
	 * Creates a local filesystem connection, in a flow context this is usually not necessary. Only option is "path".
	 *
	 * @param array $options
	 * @return Filesystem
	 * @throws Exception
	 */
	protected function createLocalConnection($options) {
		if (!isset($options['path'])) {
			throw new Exception('The local flysystem connection needs the "path" option to be set.', 1416233336);
		}
		$filesystem = new Filesystem(new \League\Flysystem\Adapter\Local($options['path']));
		return $filesystem;
	}

	/**
	 * Creates an FTP connection, for a list of keys in $options refer to the Flysystem documentation at:
	 * http://flysystem.thephpleague.com/adapter/ftp/
	 *
	 * @param array $options
	 * @return Filesystem
	 */
	protected function createFtpConnection($options) {
		$filesystem = new Filesystem(new \League\Flysystem\Adapter\Ftp($options));

		return $filesystem;
	}

	protected function createS3Connection($options) {
		$client = \Aws\S3\S3Client::factory([
			'credentials' => [
				'key'    => $options['s3key'],
				'secret' => $options['s3secret'],
			],
			'region' => $options['s3region'],
			'version' => 'latest',
		]);

		$filesystem = new Filesystem(new \League\Flysystem\AwsS3v3\AwsS3Adapter($client, $options['s3bucket'], $options['s3prefix']));
		return $filesystem;
	}

	/**
	 * Generates a unique string for this connection definition to reuse existing connections with the same options.
	 *
	 * @param string $driverName
	 * @param array $options
	 * @return string
	 */
	protected function getConnectionIdentifier($driverName, array $options) {
		return sha1($driverName . '-' . json_encode($options));
	}
}
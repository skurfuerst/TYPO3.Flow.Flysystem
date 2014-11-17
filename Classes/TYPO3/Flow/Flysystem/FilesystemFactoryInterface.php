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

/**
 * Class FilesystemFactory
 */
interface FilesystemFactoryInterface {

	/**
	 * @param string $driverName
	 * @param array $options
	 *
	 * @return Filesystem
	 */
	public function create($driverName, array $options);
}
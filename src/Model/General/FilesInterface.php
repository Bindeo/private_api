<?php

namespace Api\Model\General;

use Bindeo\DataModel\FileAbstract;
use Psr\Log\LoggerInterface;

interface FilesInterface
{
    public function __construct(LoggerInterface $logger, $basePath, $baseUrl);

    /**
     * Save the file
     *
     * @param FileAbstract $file
     *
     * @return string file name
     */
    public function save(FileAbstract $file);

    /**
     * Get the file
     *
     * @param int    $idClient
     * @param string $name
     *
     * @return string full path
     */
    public function get($idClient, $name);

    /**
     * Get the file hash
     *
     * @param int    $idClient
     * @param string $name
     *
     * @return string hash code
     */
    public function getHash($idClient, $name);

    /**
     * Delete a file
     *
     * @param int    $idClient
     * @param string $name
     *
     * @return bool
     */
    public function delete($idClient, $name);
}
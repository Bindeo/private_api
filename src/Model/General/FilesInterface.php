<?php

namespace Api\Model\General;

use Bindeo\DataModel\StorableFileInterface;
use Psr\Log\LoggerInterface;

interface FilesInterface
{
    public function __construct(LoggerInterface $logger, $basePath, $baseUrl);

    /**
     * Save the file
     *
     * @param StorableFileInterface $file
     */
    public function save(StorableFileInterface $file);

    /**
     * Get the file
     *
     * @param StorableFileInterface $file
     *
     * @return string full path
     */
    public function get(StorableFileInterface $file);

    /**
     * Get the file hash
     *
     * @param StorableFileInterface $file
     *
     * @return string hash code
     */
    public function getHash(StorableFileInterface $file);

    /**
     * Delete a file
     *
     * @param StorableFileInterface $file
     *
     * @return bool
     */
    public function delete(StorableFileInterface $file);
}
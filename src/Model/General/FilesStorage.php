<?php

namespace Api\Model\General;

use Bindeo\DataModel\FileAbstract;
use Psr\Log\LoggerInterface;

class FilesStorage implements FilesInterface
{
    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * Internal path
     * @var string
     */
    private $basePath;

    /**
     * Public path
     * @var string
     */
    private $baseUrl;

    /**
     * Folders name length
     * @var int
     */
    private $chunkSize;

    public function __construct(LoggerInterface $logger, $basePath, $baseUrl)
    {
        $this->logger = $logger;
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
        $this->chunkSize = 2;
    }

    /**
     * Save the file
     *
     * @param FileAbstract $file
     *
     * @return string
     * @throws \Exception
     */
    public function save(FileAbstract $file)
    {
        // Get the subpath based on the id
        $path = $this->createPath($file->getIdUser());
        // Generate a file name with the uploaded extension
        $ext = [];
        $ext = preg_match('/\.[a-zA-Z]+$/', $file->getFileOrigName(), $ext) ? strtolower($ext[0]) : '';
        $name = md5($file->getIdUser() . $file->getFileOrigName() . time()) . $ext;

        // Move to the final folder
        if (!rename($file->getPath(), $path . '/' . $name)) {
            $this->logger->addError('Error moving uploaded file', [$file->getPath(), $path . '/' . $name]);
            throw new \RuntimeException(Exceptions::CANNOT_MOVE, 503);
        }

        $file->setFileName($name);

        return $name;
    }

    /**
     * Get the file
     *
     * @param int    $idClient
     * @param string $name
     *
     * @return string public path
     */
    public function get($idClient, $name)
    {
        // Get the subpath
        return $this->baseUrl . $this->getSubPath($idClient) . '/' . $name;
    }

    /**
     * Get the file hash
     *
     * @param int    $idClient
     * @param string $name
     *
     * @return string hash code
     */
    public function getHash($idClient, $name)
    {
        // Get the subpath
        return hash_file('sha256', $this->basePath . $this->getSubPath($idClient) . '/' . $name);
    }

    /**
     * Delete a file
     *
     * @param int    $idClient
     * @param string $name
     *
     * @return bool
     */
    public function delete($idClient, $name)
    {
        $file = $this->basePath . $this->getSubPath($idClient) . '/' . $name;

        return unlink($file);
    }

    /**
     * Generate the subpath based on the received id, fragmenting it into small pieces.
     * Example: for a given 1129 id and chunking value of 2 the result will be /29/11
     *
     * @param string $id Id number
     *
     * @return string $subPath
     */
    private function getSubPath($id)
    {
        $path = '';
        // Generate necessary folders
        do {
            // We take the last chunking value digits, if we have less digits we prefix it with 0
            if (strlen($id) > ($this->chunkSize - 1)) {
                $path .= "/" . substr($id, (-1 * $this->chunkSize));
            } else {
                $path .= "/" . str_pad($id, $this->chunkSize, '0', STR_PAD_LEFT);
            }
            // Remove last id digits
            $id = substr($id, 0, (-1 * $this->chunkSize));
        } while ($id != "");

        return $path;
    }

    /**
     * Create the subpath based on the received id
     *
     * @param int $id
     *
     * @return string
     * @throws \Exception
     */
    private function createPath($id)
    {
        if (!is_dir($this->basePath)) {
            throw new \Exception('', 500);
        }

        // Generate the path
        $path = $this->basePath . $this->getSubPath($id);

        // If the path doesn't exist we create it
        if (!is_dir($path) and !mkdir($path, 0777, true)) {
            throw new \Exception('', 500);
        } else {
            return $path;
        }
    }
}
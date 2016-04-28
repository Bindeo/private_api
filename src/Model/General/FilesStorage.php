<?php

namespace Api\Model\General;

use Bindeo\DataModel\Exceptions;
use Bindeo\DataModel\SignableInterface;
use Bindeo\DataModel\StorableFileInterface;
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
     * @param StorableFileInterface $file
     *
     * @throws \Exception
     */
    public function save(StorableFileInterface $file)
    {
        // Get the subpath based on the id
        $path = $this->createPath($file);
        // Generate a file name with the uploaded extension
        $ext = [];
        $ext = preg_match('/\.[a-zA-Z]+$/', $file->getFileOrigName(), $ext) ? strtolower($ext[0]) : '';
        $name = md5($file->getClientType() . $file->getIdClient() . $file->getFileOrigName() . time()) . $ext;

        // Move to the final folder
        if (!rename($file->getPath(), $path . '/' . $name)) {
            $this->logger->addError('Error moving uploaded file', [$file->getPath(), $path . '/' . $name]);
            throw new \Exception(Exceptions::CANNOT_MOVE, 503);
        }

        $file->setFileName($name);
    }

    /**
     * Get the file
     *
     * @param StorableFileInterface $file
     *
     * @return string public path
     */
    public function get(StorableFileInterface $file)
    {
        // Get the subpath
        return $this->basePath . $this->getSubPath($file) . '/' . $file->getFileName();
    }

    /**
     * Get the file hash
     *
     * @param StorableFileInterface $file
     *
     * @return string hash code
     */
    public function getHash(StorableFileInterface $file)
    {
        // Get the subpath
        return hash_file('sha256', $this->basePath . $this->getSubPath($file) . '/' . $file->getFileName());
    }

    /**
     * Delete a file
     *
     * @param StorableFileInterface $file
     *
     * @return bool
     */
    public function delete(StorableFileInterface $file)
    {
        $file = $this->basePath . $this->getSubPath($file) . '/' . $file->getFileName();

        return unlink($file);
    }

    /**
     * Generate the subpath based on the received id, fragmenting it into small pieces.
     * Example: for a given 1129 id and chunking value of 2 the result will be /29/11
     *
     * @param StorableFileInterface $file
     *
     * @return string $subPath
     */
    private function getSubPath(StorableFileInterface $file)
    {
        if ($file->getStorageType() == 'bulk') {
            $path = '/bulk';
        } elseif ($file->getStorageType() == 'sign') {
            $path = '/sign';
        } else {
            $path = '/notarize';
        }

        $id = $file->getIdClient();
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
     * @param StorableFileInterface $file
     *
     * @return string
     * @throws \Exception
     */
    private function createPath(StorableFileInterface $file)
    {
        if (!is_dir($this->basePath)) {
            throw new \Exception('', 500);
        }

        // Generate the path
        $path = $this->basePath . $this->getSubPath($file);

        // If the path doesn't exist we create it
        if (!is_dir($path) and !mkdir($path, 0777, true)) {
            throw new \Exception('', 500);
        } else {
            return $path;
        }
    }

    /**
     * Get array of pages previews of the document
     *
     * @param SignableInterface $file
     *
     * @return array Pages images path
     */
    public function pagesPreview(SignableInterface $file)
    {
        // File must be storable and signable
        $pages = 0;
        if ($file instanceof StorableFileInterface) {
            // Find folder name
            $matches = [];
            preg_match('/^([a-z0-9]+)\./', $file->getFileName(), $matches);
            $folder = $this->basePath . $this->getSubPath($file) . '/' . $matches[1];

            // check if folder exists and how many png pages are inside
            if (is_dir($folder)) {
                $pages = scandir($folder);
                // Remove '.' and '..' directories and set path for the rest
                $newPages = [];
                foreach ($pages as $page) {
                    if ($page != '.' and $page != '..') {
                        $newPages[] = $folder . '/' . $page;
                    }
                }
                $pages = $newPages;
            }
        }

        return $pages;
    }
}
<?php

namespace Api\Model\General;

use Psr\Http\Message\UploadedFileInterface;

class FilesStorage implements FilesInterface
{
    /**
     * Internal path
     * @var string
     */
    private $_basePath;

    /**
     * Public path
     * @var string
     */
    private $_baseUrl;

    /**
     * Folders name length
     * @var int
     */
    private $_chunkSize;

    public function __construct($basePath, $baseUrl)
    {
        $this->_basePath = $basePath;
        $this->_baseUrl = $baseUrl;
        $this->_chunkSize = 2;
    }

    /**
     * Save the file
     *
     * @param int                   $idClient
     * @param UploadedFileInterface $file
     *
     * @return string
     */
    public function save($idClient, UploadedFileInterface $file)
    {
        // Get the subpath based on the id
        $path = $this->_createPath($idClient);
        // Generate a file name with the uploaded extension
        $ext = [];
        $ext = preg_match('/\.[a-zA-Z]+$/', $file->getClientFilename(), $ext) ? strtolower($ext[0]) : '';
        $name = md5($idClient . $file->getClientFilename() . time()) . $ext;

        // Move to the final folder
        $file->moveTo($path . '/' . $name);

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
        return $this->_baseUrl . $this->_getSubPath($idClient) . '/' . $name;
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
        return hash_file('sha256', $this->_basePath . $this->_getSubPath($idClient) . '/' . $name);
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
        $file = $this->_basePath . $this->_getSubPath($idClient) . '/' . $name;

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
    private function _getSubPath($id)
    {
        $path = '';
        // Generate necessary folders
        do {
            // We take the last chunking value digits, if we have less digits we prefix it with 0
            if (strlen($id) > ($this->_chunkSize - 1)) {
                $path .= "/" . substr($id, (-1 * $this->_chunkSize));
            } else {
                $path .= "/" . str_pad($id, $this->_chunkSize, '0', STR_PAD_LEFT);
            }
            // Remove last id digits
            $id = substr($id, 0, (-1 * $this->_chunkSize));
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
    private function _createPath($id)
    {
        if (!is_dir($this->_basePath)) {
            throw new \Exception('', 500);
        }

        // Generate the path
        $path = $this->_basePath . $this->_getSubPath($id);

        // If the path doesn't exist we create it
        if (!is_dir($path) and !mkdir($path, 0777, true)) {
            throw new \Exception('', 500);
        } else {
            return $path;
        }
    }
}
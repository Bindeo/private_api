<?php

namespace Api\Repository;

use Api\Entity\File;
use Api\Entity\ResultSet;
use Bindeo\DataModel\SignableInterface;
use Bindeo\Filter\FilesFilter;
use Bindeo\DataModel\Exceptions;
use \MaxMind\Db\Reader;
use Api\Entity\BlockChain;

class StoreData extends RepositoryLocatableAbstract
{
    /**
     * Find a transaction by id
     *
     * @param BlockChain $blockchain
     *
     * @return \Api\Entity\BlockChain
     * @throws \Exception
     */
    public function findTransaction(BlockChain $blockchain)
    {
        if (!$blockchain->getTransaction()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT TRANSACTION, NET, CONFIRMED, FK_ID_USER, FK_ID_IDENTITY, HASH, JSON_DATA, CTRL_DATE, CTRL_IP,
                  TYPE, FK_ID_ELEMENT, STATUS_ELEMENT, ID_GEONAMES, LATITUDE, LONGITUDE FROM BLOCKCHAIN WHERE TRANSACTION = :id';
        $params = [':id' => $blockchain->getTransaction()];

        $data = $this->db->query($sql, $params, 'Api\Entity\BlockChain');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
    }

    /**
     * Find a file by id
     *
     * @param SignableInterface $file
     *
     * @return \Api\Entity\File
     * @throws \Exception
     */
    public function findFile(SignableInterface $file)
    {
        if (!($file instanceof File) or !$file->getIdElement()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT ID_FILE, FK_ID_USER, FK_ID_TYPE, FK_ID_MEDIA, NAME, FILE_NAME, FILE_ORIG_NAME, HASH, SIZE,
                  CTRL_DATE, TAG, DESCRIPTION, TRANSACTION, CONFIRMED, STATUS, ID_GEONAMES, LATITUDE, LONGITUDE
                FROM FILES WHERE ID_FILE = :id';
        $params = [':id' => $file->getIdElement()];

        $data = $this->db->query($sql, $params, 'Api\Entity\File');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
    }

    /**
     * Create a new file
     *
     * @param File $file
     *
     * @throws \Exception
     */
    public function createFile(File $file)
    {
        $file->clean();
        // Check the received data
        if (!$file->getIdUser() or !$file->getIdType() or !$file->getIdMedia() or !$file->getName() or !$file->getFileName() or !$file->getFileOrigName() or !$file->getIp() or !$file->getHash()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Look for another file with the same hash and same user
        $sql = "SELECT A.NUM FORBIDDEN, B.NUM WARNING FROM
                (SELECT COUNT(*) NUM FROM FILES WHERE STATUS = 'A' AND HASH = :hash AND FK_ID_USER = :id_user) A,
                (SELECT COUNT(*) NUM FROM FILES WHERE STATUS = 'A' AND HASH = :hash AND FK_ID_USER != :id_user) B";
        $res = $this->db->query($sql, ['id_user' => $file->getIdUser(), ':hash' => $file->getHash()]);

        if ($res->getRows()[0]['FORBIDDEN'] > 0) {
            throw new \Exception(Exceptions::DUPLICATED_FILE, 409);
        } elseif ($res->getRows()[0]['WARNING'] > 0) {
            // TODO Insert into the db logger
        }

        // Geolocalize the file
        $this->geolocalize($file);

        $this->db->beginTransaction();
        // Prepare query and mandatory data
        $sql = 'UPDATE USERS SET STORAGE_LEFT = CASE WHEN TYPE > 1 THEN STORAGE_LEFT - :size ELSE 0 END WHERE ID_USER = :id_user;
                INSERT INTO FILES(FK_ID_USER, FK_ID_TYPE, FK_ID_MEDIA, NAME, FILE_NAME, FILE_ORIG_NAME, HASH, SIZE,
                  CTRL_DATE, CTRL_IP, TAG, DESCRIPTION, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:id_user, :id_type, :id_media, :name, :file_name, :file_orig, :hash, :size, SYSDATE(), :ip, :tag,
                  :description, :id_geonames, :latitude, :longitude);';

        $data = [
            ':id_user'     => $file->getIdUser(),
            ':id_type'     => $file->getIdType(),
            ':id_media'    => $file->getIdMedia(),
            ':name'        => $file->getName(),
            ':file_name'   => $file->getFileName(),
            ':file_orig'   => $file->getFileOrigName(),
            ':hash'        => $file->getHash(),
            ':size'        => $file->getSize(),
            ':ip'          => $file->getIp(),
            ':tag'         => $file->getTag() ? $file->getTag() : null,
            ':description' => $file->getDescription() ? $file->getDescription() : null,
            ':id_geonames' => $file->getIdGeonames() ? $file->getIdGeonames() : null,
            ':latitude'    => $file->getLatitude() ? $file->getLatitude() : null,
            ':longitude'   => $file->getLongitude() ? $file->getLongitude() : null
        ];

        // Execute query
        $this->db->action($sql, $data);
        if (!$this->db->getError()) {
            $file->setIdFile($this->db->lastInsertId());
            $this->db->commit();
        } else {
            $this->db->rollBack();
            throw $this->dbException();
        }
    }

    /**
     * Delete a file o send it to trash
     *
     * @param File $file
     *
     * @return File
     * @throws \Exception
     */
    public function deleteFile(File $file)
    {
        if (!$file->getIdFile() or !$file->getIp() or !in_array($file->getStatus(), ['T', 'D'])) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Fetch the file to delete
        $sql = 'SELECT ID_FILE, FILE_NAME, FK_ID_USER, SIZE, STATUS FROM FILES WHERE ID_FILE = :id';
        $res = $this->db->query($sql, [':id' => $file->getIdFile()], 'Api\Entity\File');
        if ($res->getNumRows() != 1) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        } else {
            /** @var \Api\Entity\File $res */
            $res = $res->getRows()[0];
        }

        // Send to trash or delete the file
        $sql = "UPDATE FILES SET STATUS = :status, CTRL_IP_DEL = :ip, CTRL_DATE_DEL = SYSDATE()
                WHERE ID_FILE = :id";

        $data = [':id' => $file->getIdFile(), 'status' => $file->getStatus(), ':ip' => $file->getIp()];
        if (!$this->db->action($sql, $data)) {
            throw $this->dbException();
        }

        // If the file has been permanently deleted, we free the space
        if ($file->getStatus() == 'D') {
            $sql = 'DELETE FROM FILES WHERE ID_FILE = :id;
                    UPDATE USERS SET STORAGE_LEFT = CASE WHEN TYPE > 1 THEN STORAGE_LEFT + :size ELSE 0 END WHERE ID_USER = :id_user;';
            $data = [':id' => $file->getIdFile(), ':id_user' => $res->getIdUser(), ':size' => $res->getSize()];
            if (!$this->db->action($sql, $data)) {
                throw $this->dbException();
            }
        }

        return $res;
    }

    /**
     * Get a paginated list of files from one user
     *
     * @param FilesFilter $filter
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function fileList(FilesFilter $filter)
    {
        if (!is_numeric($filter->getIdUser()) or !is_numeric($filter->getPage())) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $filter->clean();

        // Build the query
        $data = [':id_user' => $filter->getIdUser(), ':status' => $filter->getStatus()];
        $where = '';

        // Media filter
        if ($filter->getMediaType()) {
            $data[':id_media'] = $filter->getMediaType();
            $where .= ' AND FK_ID_MEDIA = :id_media';
        }

        // Special filter
        if ($filter->getSpecialFilter()) {
            switch ($filter->getSpecialFilter()) {
                case FilesFilter::SPECIAL_NOTARIZED:
                    $where .= ' AND CONFIRMED = 1 AND TRANSACTION IS NOT NULL';
                    break;
                case FilesFilter::SPECIAL_NOTARIZING:
                    $where .= ' AND CONFIRMED = 0 AND TRANSACTION IS NOT NULL';
                    break;
            }
        }

        // Name
        if ($filter->getName()) {
            $data[':name'] = $filter->getName();
            if ($filter->getStatus() != 'D') {
                $where .= ' AND MATCH(NAME, TAG, DESCRIPTION) AGAINST(:name  IN NATURAL LANGUAGE MODE)';
            } else {
                $where .= ' AND MATCH(NAME) AGAINST(:name  IN NATURAL LANGUAGE MODE)';
            }
        }

        // Orders
        switch ($filter->getOrder()) {
            case FilesFilter::ORDER_DATE_DESC:
                $order = 'CTRL_DATE DESC';
                break;
            case FilesFilter::ORDER_DATE_ASC:
                $order = 'CTRL_DATE ASC';
                break;
            case FilesFilter::ORDER_NAME_ASC:
                $order = 'NAME ASC';
                break;
            case FilesFilter::ORDER_NAME_DESC:
                $order = 'NAME DESC';
                break;
            case FilesFilter::ORDER_SIZE_ASC:
                $order = 'SIZE ASC';
                break;
            case FilesFilter::ORDER_SIZE_DESC:
                $order = 'SIZE DESC';
                break;
        }

        // Get the paginated list
        $from = $filter->getStatus() != 'D' ? 'FILES' : 'FILES_DELETED';

        $sql = "SELECT ID_FILE, FK_ID_USER, FK_ID_TYPE, FK_ID_MEDIA, NAME, FILE_NAME, FILE_ORIG_NAME, HASH, SIZE, CTRL_DATE,
                  TRANSACTION, CONFIRMED, STATUS, TAG, DESCRIPTION, ID_GEONAMES, LATITUDE, LONGITUDE
                FROM " . $from . " WHERE FK_ID_USER = :id_user" . $where . " AND STATUS = :status ORDER BY " . $order;

        return $this->db->query($sql, $data, 'Api\Entity\File', $filter->getPage(), $filter->getNumRows());
    }

    /**
     * Store a transaction from a signed asset
     *
     * @param BlockChain $blockchain
     *
     * @return BlockChain
     * @throws \Exception
     */
    public function signAsset(BlockChain $blockchain)
    {
        if (!$blockchain->getIdElement() or !$blockchain->getIdUser() or !$blockchain->getIp() or !$blockchain->getNet() or !$blockchain->getTransaction() or !$blockchain->getHash() or !$blockchain->getJsonData() or !in_array($blockchain->getType(),
                ['F', 'T', 'B'])
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the $blockChain
        $this->geolocalize($blockchain);

        $this->db->beginTransaction();
        // Insert the transaction
        $sql = "INSERT INTO BLOCKCHAIN(TRANSACTION, NET, FK_ID_USER, FK_ID_IDENTITY, HASH, JSON_DATA, CTRL_DATE,
                  CTRL_IP, TYPE, FK_ID_ELEMENT, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:txid, :net, :id_user, :id_identity, :hash, :json_data, SYSDATE(), :ip, :type, :id_element,
                  :id_geonames, :latitude, :longitude)";
        $data = [
            ':txid'        => $blockchain->getTransaction(),
            ':net'         => $blockchain->getNet(),
            ':id_user'     => $blockchain->getIdUser(),
            ':id_identity' => $blockchain->getIdIdentity(),
            ':hash'        => $blockchain->getHash(),
            ':json_data'   => $blockchain->getJsonData(),
            ':ip'          => $blockchain->getIp(),
            ':type'        => $blockchain->getType(),
            ':id_element'  => $blockchain->getIdElement(),
            ':id_geonames' => $blockchain->getIdGeonames() ? $blockchain->getIdGeonames() : null,
            ':latitude'    => $blockchain->getLatitude() ? $blockchain->getLatitude() : null,
            ':longitude'   => $blockchain->getLongitude() ? $blockchain->getLongitude() : null
        ];

        if ($this->db->action($sql, $data)) {
            if ($blockchain->getType() == 'F') {
                // Update the file
                $sql = 'UPDATE FILES SET HASH = :hash, TRANSACTION = :txid WHERE ID_FILE = :id';
            } elseif ($blockchain->getType() == 'F') {
                $sql = 'UPDATE EMAILS SET HASH = :hash, TRANSACTION = :txid WHERE ID_EMAIL = :id';
            } elseif ($blockchain->getType() == 'B') {
                $sql = 'UPDATE BULK_TRANSACTION SET HASH = :hash, TRANSACTION = :txid WHERE ID_BULK_TRANSACTION = :id';
            }
            $data = [
                ':id'   => $blockchain->getIdElement(),
                ':hash' => $blockchain->getHash(),
                ':txid' => $blockchain->getTransaction()
            ];

            if (!$this->db->action($sql, $data)) {
                $this->db->rollBack();
                throw $this->dbException();
            }
        } else {
            $this->db->rollBack();
            throw $this->dbException();
        }

        $this->db->commit();

        return $blockchain;
    }

    /**
     * Calculate file media type by its extension
     *
     * @param File $file
     *
     * @return int
     * @throws \Exception
     */
    public function calculateMediaType(File $file)
    {
        if (!$file->getFileOrigName()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Extract the extension
        $matches = [];
        if (!preg_match('/.+\.([a-z0-9]+)$/i', $file->getFileOrigName(), $matches) or !isset($matches[1])) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Query for the media type
        $sql = 'SELECT T.ID_TYPE FROM MEDIA_TYPES T, MEDIA_EXTENSIONS E
                WHERE T.ID_TYPE = E.FK_ID_TYPE AND E.EXT = :ext';
        $res = $this->db->query($sql, [':ext' => strtolower($matches[1])]);

        if ($res->getNumRows() != 1) {
            return General::MEDIA_TYPE_OTHERS;
        } else {
            return $res->getRows()[0]['ID_TYPE'];
        }
    }
}
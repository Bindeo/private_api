<?php

namespace Api\Repository;

use Api\Entity\File;
use Api\Entity\ResultSet;
use Api\Model\General\Exceptions;
use \MaxMind\Db\Reader;
use Api\Entity\BlockChain;

class StoreData extends RepositoryLocatableAbstract
{
    /**
     * Find a transaction by id

*
*@param BlockChain $blockchain


*
*@return \Api\Entity\BlockChain
     * @throws \Exception
     */
    public function findTransaction(BlockChain $blockchain)
    {
        if (!$blockchain->getTransaction()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT TRANSACTION, NET, FK_ID_CLIENT, HASH, CTRL_DATE, CTRL_IP, TYPE, FK_ID_ELEMENT, ID_GEONAMES,
                LATITUDE, LONGITUDE FROM BLOCKCHAIN WHERE TRANSACTION = :id';
        $params = [':id' => $blockchain->getTransaction()];

        $data = $this->_db->query($sql, $params, 'Api\Entity\BlockChain');

        if (!$data or $this->_db->getError()) {
            throw new \Exception($this->_db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : [];
    }

    /**
     * Find a file by id

*
*@param File $file


*
*@return \Api\Entity\File
     * @throws \Exception
     */
    public function findFile(File $file)
    {
        if (!$file->getIdFile()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT ID_FILE, FK_ID_CLIENT, TYPE, NAME, HASH, SIZE, CTRL_DATE, TRANSACTION, STATUS, ID_GEONAMES,
                LATITUDE, LONGITUDE FROM FILES WHERE ID_FILE = :id';
        $params = [':id' => $file->getIdFile()];

        $data = $this->_db->query($sql, $params, 'Api\Entity\File');

        if (!$data or $this->_db->getError()) {
            throw new \Exception($this->_db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
    }

    /**
     * Create a new file

*
*@param \Api\Entity\File $file
     *
*@return array
     * @throws \Exception
     */
    public function createFile(File $file)
    {
        $file->clean();
        // Check the received data
        if (!$file->getIdClient() or !$file->getType() or !$file->getName() or !$file->getIp() or !$file->getHash()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the client
        /** @var \Api\Entity\File $file */
        $file = $this->_geolocalize($file);

        $this->_db->beginTransaction();
        // Prepare query and mandatory data
        $sql = 'UPDATE CLIENTS SET STORAGE_LEFT = CASE WHEN TYPE > 1 THEN STORAGE_LEFT - :size ELSE 0 END WHERE ID_CLIENT = :id_client;
                INSERT INTO FILES(FK_ID_CLIENT, TYPE, NAME, HASH, SIZE, CTRL_DATE, CTRL_IP, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:id_client, :type, :name, :hash, :size, SYSDATE(), :ip, :id_geonames, :latitude, :longitude);';

        $data = [
            ':id_client'   => $file->getIdClient(),
            ':type'        => $file->getType(),
            ':name'        => $file->getName(),
            ':hash'        => $file->getHash(),
            ':size'        => $file->getSize(),
            ':ip'          => $file->getIp(),
            ':id_geonames' => $file->getIdGeonames() ? $file->getIdGeonames() : null,
            ':latitude'    => $file->getLatitude() ? $file->getLatitude() : null,
            ':longitude'   => $file->getLongitude() ? $file->getLongitude() : null
        ];

        // Execute query
        if ($this->_db->action($sql, $data)) {
            $id = $this->_db->lastInsertId();
            $this->_db->commit();

            return $id;
        } else {
            $this->_db->rollBack();
            throw $this->_dbException();
        }
    }

    /**
     * Delete a file

*
*@param \Api\Entity\File $file
     *
*@return \Api\Entity\File
     * @throws \Exception
     */
    public function deleteFile(File $file)
    {
        if (!$file->getIdFile() or !$file->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Fetch the file to delete
        $sql = 'SELECT ID_FILE, NAME, FK_ID_CLIENT, SIZE, TRANSACTION FROM FILES WHERE ID_FILE = :id';
        $res = $this->_db->query($sql, [':id' => $file->getIdFile()], 'Api\Entity\File');
        if ($res->getNumRows() != 1) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        } else {
            /** @var \Api\Entity\File $res */
            $res = $res->getRows()[0];
        }

        // If the file has been already signed we can't delete it, we mark it as deleted
        if ($res->getTransaction()) {
            $sql = "UPDATE FILES SET STATUS = 'D', CTRL_IP_DEL = :ip, CTRL_DATE_DEL = SYSDATE()
                    WHERE ID_FILE = :id";
            $data = [':id' => $file->getIdFile(), ':ip' => $file->getIp()];
            if ($this->_db->action($sql, $data)) {
                return null;
            } else {
                throw $this->_dbException();
            }
        } else {
            $this->_db->beginTransaction();
            $sql = 'UPDATE CLIENTS SET STORAGE_LEFT = CASE WHEN TYPE > 1 THEN STORAGE_LEFT + :size ELSE 0 END WHERE ID_CLIENT = :id_client;
                    DELETE FROM FILES WHERE ID_FILE = :id;';
            $data = [':id' => $file->getIdFile(), ':id_client' => $res->getIdClient(), ':size' => $res->getSize()];
            if (!$this->_db->action($sql, $data)) {
                $this->_db->rollBack();
                throw $this->_dbException();
            } else {
                $this->_db->commit();
            }
        }

        return $res;
    }

    /**
     * Get a paginated list of files from one client

*
*@param int $idClient
     * @param int $page
     * @param int $numRows [optional]

*
*@return \Api\Entity\ResultSet
     */
    public function fileList($idClient, $page, $numRows = 20)
    {
        // Get the paginated list
        $sql = "SELECT ID_FILE, FK_ID_CLIENT, TYPE, NAME, HASH, CTRL_DATE, TRANSACTION, STATUS, ID_GEONAMES,
                LATITUDE, LONGITUDE FROM FILES WHERE FK_ID_CLIENT = :id AND STATUS = 'A' ORDER BY ID_FILE ASC";
        $data = [':id' => $idClient];

        return $this->_db->query($sql, $data, 'Api\Entity\File', $page, $numRows);
    }

    /**
     * Store a transaction from a signed file
     *
     * @param File   $file
     * @param string $net Blockchain net
     *
     * @return File
     * @throws \Exception
     */
    public function signFile(File $file, $net)
    {
        if (!$file->getIdFile() or !$file->getIdClient() or !$file->getIp() or !$file->getTransaction() or !$file->getHash()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the client
        /** @var \Api\Entity\File $file */
        $file = $this->_geolocalize($file);

        $this->_db->beginTransaction();
        // Insert the transaction
        $sql = "INSERT INTO BLOCKCHAIN(TRANSACTION, NET, FK_ID_CLIENT, HASH, CTRL_DATE, CTRL_IP, TYPE, FK_ID_ELEMENT, ID_GEONAMES, LATITUDE, LONGITUDE)
                    VALUES (:txid, :net, :id_client, :hash, SYSDATE(), :ip, 'F', :id_element, :id_geonames, :latitude, :longitude)";
        $data = [
            ':txid'        => $file->getTransaction(),
            ':net'         => $net,
            ':id_client'   => $file->getIdClient(),
            ':hash'        => $file->getHash(),
            ':ip'          => $file->getIp(),
            ':id_element'  => $file->getIdFile(),
            ':id_geonames' => $file->getIdGeonames() ? $file->getIdGeonames() : null,
            ':latitude'    => $file->getLatitude() ? $file->getLatitude() : null,
            ':longitude'   => $file->getLongitude() ? $file->getLongitude() : null
        ];

        if ($this->_db->action($sql, $data)) {
            // Update the file
            $sql = 'UPDATE FILES SET HASH = :hash, TRANSACTION = :txid WHERE ID_FILE = :id';
            $data = [':id' => $file->getIdFile(), ':hash' => $file->getHash(), ':txid' => $file->getTransaction()];

            if (!$this->_db->action($sql, $data)) {
                $this->_db->rollBack();
                throw $this->_dbException();
            }
        } else {
            $this->_db->rollBack();
            throw $this->_dbException();
        }

        $this->_db->commit();

        return $file;
    }
}
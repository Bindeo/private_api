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

        $sql = 'SELECT TRANSACTION, NET, FK_ID_USER, HASH, CTRL_DATE, CTRL_IP, TYPE, FK_ID_ELEMENT, ID_GEONAMES,
                LATITUDE, LONGITUDE FROM BLOCKCHAIN WHERE TRANSACTION = :id';
        $params = [':id' => $blockchain->getTransaction()];

        $data = $this->db->query($sql, $params, 'Api\Entity\BlockChain');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : [];
    }

    /**
     * Find a file by id
     *
     * @param File $file
     *
     * @return \Api\Entity\File
     * @throws \Exception
     */
    public function findFile(File $file)
    {
        if (!$file->getIdFile()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT ID_FILE, FK_ID_USER, TYPE, NAME, HASH, SIZE, CTRL_DATE, TRANSACTION, STATUS, ID_GEONAMES,
                LATITUDE, LONGITUDE FROM FILES WHERE ID_FILE = :id';
        $params = [':id' => $file->getIdFile()];

        $data = $this->db->query($sql, $params, 'Api\Entity\File');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
    }

    /**
     * Create a new file
     *
     * @param \Api\Entity\File $file
     *
     * @return array
     * @throws \Exception
     */
    public function createFile(File $file)
    {
        $file->clean();
        // Check the received data
        if (!$file->getIdUser() or !$file->getType() or !$file->getName() or !$file->getIp() or !$file->getHash()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the user
        /** @var \Api\Entity\File $file */
        $file = $this->geolocalize($file);

        $this->db->beginTransaction();
        // Prepare query and mandatory data
        $sql = 'UPDATE USERS SET STORAGE_LEFT = CASE WHEN TYPE > 1 THEN STORAGE_LEFT - :size ELSE 0 END WHERE ID_USER = :id_user;
                INSERT INTO FILES(FK_ID_USER, TYPE, NAME, HASH, SIZE, CTRL_DATE, CTRL_IP, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:id_user, :type, :name, :hash, :size, SYSDATE(), :ip, :id_geonames, :latitude, :longitude);';

        $data = [
            ':id_user'     => $file->getIdUser(),
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
        $this->db->action($sql, $data);
        if (!$this->db->getError()) {
            $id = $this->db->lastInsertId();
            $this->db->commit();

            return $id;
        } else {
            $this->db->rollBack();
            throw $this->dbException();
        }
    }

    /**
     * Delete a file
     *
     * @param \Api\Entity\File $file
     *
     * @return \Api\Entity\File
     * @throws \Exception
     */
    public function deleteFile(File $file)
    {
        if (!$file->getIdFile() or !$file->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Fetch the file to delete
        $sql = 'SELECT ID_FILE, NAME, FK_ID_USER, SIZE, TRANSACTION FROM FILES WHERE ID_FILE = :id';
        $res = $this->db->query($sql, [':id' => $file->getIdFile()], 'Api\Entity\File');
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
            if ($this->db->action($sql, $data)) {
                return null;
            } else {
                throw $this->dbException();
            }
        } else {
            $this->db->beginTransaction();
            $sql = 'UPDATE USERS SET STORAGE_LEFT = CASE WHEN TYPE > 1 THEN STORAGE_LEFT + :size ELSE 0 END WHERE ID_USER = :id_user;
                    DELETE FROM FILES WHERE ID_FILE = :id;';
            $data = [':id' => $file->getIdFile(), ':id_user' => $res->getIdUser(), ':size' => $res->getSize()];
            if (!$this->db->action($sql, $data)) {
                $this->db->rollBack();
                throw $this->dbException();
            } else {
                $this->db->commit();
            }
        }

        return $res;
    }

    /**
     * Get a paginated list of files from one user
     *
     * @param int $idUser
     * @param int $page
     * @param int $numRows [optional]
     *
     * @return \Api\Entity\ResultSet
     */
    public function fileList($idUser, $page, $numRows = 20)
    {
        // Get the paginated list
        $sql = "SELECT ID_FILE, FK_ID_USER, TYPE, NAME, HASH, CTRL_DATE, TRANSACTION, STATUS, ID_GEONAMES,
                LATITUDE, LONGITUDE FROM FILES WHERE FK_ID_USER = :id AND STATUS = 'A' ORDER BY ID_FILE ASC";
        $data = [':id' => $idUser];

        return $this->db->query($sql, $data, 'Api\Entity\File', $page, $numRows);
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
        if (!$file->getIdFile() or !$file->getIdUser() or !$file->getIp() or !$file->getTransaction() or !$file->getHash()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the user
        /** @var \Api\Entity\File $file */
        $file = $this->geolocalize($file);

        $this->db->beginTransaction();
        // Insert the transaction
        $sql = "INSERT INTO BLOCKCHAIN(TRANSACTION, NET, FK_ID_USER, HASH, CTRL_DATE, CTRL_IP, TYPE, FK_ID_ELEMENT, ID_GEONAMES, LATITUDE, LONGITUDE)
                    VALUES (:txid, :net, :id_user, :hash, SYSDATE(), :ip, 'F', :id_element, :id_geonames, :latitude, :longitude)";
        $data = [
            ':txid'        => $file->getTransaction(),
            ':net'         => $net,
            ':id_user'     => $file->getIdUser(),
            ':hash'        => $file->getHash(),
            ':ip'          => $file->getIp(),
            ':id_element'  => $file->getIdFile(),
            ':id_geonames' => $file->getIdGeonames() ? $file->getIdGeonames() : null,
            ':latitude'    => $file->getLatitude() ? $file->getLatitude() : null,
            ':longitude'   => $file->getLongitude() ? $file->getLongitude() : null
        ];

        if ($this->db->action($sql, $data)) {
            // Update the file
            $sql = 'UPDATE FILES SET HASH = :hash, TRANSACTION = :txid WHERE ID_FILE = :id';
            $data = [':id' => $file->getIdFile(), ':hash' => $file->getHash(), ':txid' => $file->getTransaction()];

            if (!$this->db->action($sql, $data)) {
                $this->db->rollBack();
                throw $this->dbException();
            }
        } else {
            $this->db->rollBack();
            throw $this->dbException();
        }

        $this->db->commit();

        return $file;
    }
}
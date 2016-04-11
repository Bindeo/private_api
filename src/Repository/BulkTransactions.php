<?php

namespace Api\Repository;

use Api\Entity\BlockchainInfo;
use Api\Entity\BulkEvent;
use Api\Entity\BulkFile;
use Api\Entity\BulkTransaction;
use Api\Entity\BulkType;
use Api\Entity\OAuthClient;
use Api\Entity\ResultSet;
use Bindeo\DataModel\Exceptions;
use \MaxMind\Db\Reader;

class BulkTransactions extends RepositoryLocatableAbstract
{
    /**
     * Verify the received data from a BulkTransaction for creation
     *
     * @param BulkTransaction $bulk
     *
     * @throws \Exception
     */
    public function verifyBulkTransaction(BulkTransaction $bulk)
    {
        // Check the received data
        if (!in_array($bulk->getClientType(), ['U', 'C']) or !$bulk->getIdClient() or !$bulk->getIp() or
            !$bulk->getExternalId() or !in_array($bulk->getElementsType(), ['F', 'E']) or !$bulk->getType()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }
    }

    /**
     * Verify the received data from a BulkFile for creation
     *
     * @param BulkFile $file
     *
     * @throws \Exception
     */
    public function verifyBulkFile(BulkFile $file)
    {
        // Check the received data
        if (!$file->getUniqueId() or !$file->getFileOrigName() or !$file->getFileType() or !$file->getIdSign() or
            !$file->getFullName() or !$file->getFileDate()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }
    }

    /**
     * Get an existent Bulk Transaction
     *
     * @param BulkTransaction $bulk
     *
     * @return BulkTransaction
     * @throws \Exception
     */
    public function getBulk(BulkTransaction $bulk)
    {
        $bulk->clean();

        // Check mandatory data
        if (!$bulk->getIdBulkTransaction() and
            (!$bulk->getClientType() or !$bulk->getIdClient() or !$bulk->getExternalId())
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Build the query
        $sql = "SELECT ID_BULK_TRANSACTION, EXTERNAL_ID, TYPE, ELEMENTS_TYPE, CLIENT_TYPE, FK_ID_CLIENT, NUM_ITEMS, CLOSED,
                  STRUCTURE, HASH, STATUS, TRANSACTION, CONFIRMED
                FROM BULK_TRANSACTIONS WHERE STATUS = 'A'";

        if ($bulk->getIdBulkTransaction()) {
            $sql .= ' AND ID_BULK_TRANSACTION = :id';
            $params = [':id' => $bulk->getIdBulkTransaction()];
        } else {
            $sql .= ' AND CLIENT_TYPE = :type AND FK_ID_CLIENT = :id_client AND EXTERNAL_ID = :id';
            $params = [
                ':type'      => $bulk->getClientType(),
                ':id_client' => $bulk->getIdClient(),
                ':id'        => $bulk->getExternalId()
            ];
        }

        // Execute query
        $res = $this->db->query($sql, $params, 'Api\Entity\BulkTransaction');

        if (!$res or $this->db->getError()) {
            throw $this->dbException();
        }

        return $res->getNumRows() ? $res->getRows()[0] : null;
    }

    /**
     * Create a new Bulk Transaction
     *
     * @param BulkTransaction $bulk
     *
     * @throws \Exception
     */
    public function openBulk(BulkTransaction $bulk)
    {
        $this->verifyBulkTransaction($bulk->clean());

        // Check remain data
        if (!$bulk->getStructure() or !json_decode($bulk->getStructure(), true)) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the bulk transaction
        $this->geolocalize($bulk);

        // Prepare query and mandatory data
        $sql = "INSERT INTO BULK_TRANSACTIONS(EXTERNAL_ID, TYPE, ELEMENTS_TYPE, CLIENT_TYPE, FK_ID_CLIENT,
                  NUM_ITEMS, STRUCTURE, HASH, CTRL_DATE, CTRL_IP, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:external_id, :type, :elements_type, :client_type, :id_client, 0, :structure, 'PENDING',
                  SYSDATE(), :ip, :id_geonames, :latitude, :longitude)";

        $data = [
            ':external_id'   => $bulk->getExternalId(),
            ':type'          => $bulk->getType(),
            ':elements_type' => $bulk->getElementsType(),
            ':client_type'   => $bulk->getClientType(),
            ':id_client'     => $bulk->getIdClient(),
            ':structure'     => $bulk->getStructure(),
            ':ip'            => $bulk->getIp(),
            ':id_geonames'   => $bulk->getIdGeonames() ? $bulk->getIdGeonames() : null,
            ':latitude'      => $bulk->getLatitude() ? $bulk->getLatitude() : null,
            ':longitude'     => $bulk->getLongitude() ? $bulk->getLongitude() : null
        ];

        // Execute query
        $this->db->action($sql, $data);
        if (!$this->db->getError()) {
            $bulk->setIdBulkTransaction($this->db->lastInsertId());
        } else {
            throw $this->dbException();
        }
    }

    /**
     * Update a Bulk Transaction adding elements
     *
     * @param BulkTransaction $bulk
     *
     * @throws \Exception
     */
    public function updateBulk(BulkTransaction $bulk)
    {
        // Prepare bulk
        $bulk->clean();
        $bulk->hash();

        // Check remain data
        if (!$bulk->getIdBulkTransaction() or !$bulk->getStructure() or !$bulk->getHash() or !$bulk->getNumItems()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Update bulk
        $sql = 'UPDATE BULK_TRANSACTIONS SET STRUCTURE = :structure, HASH = :hash, NUM_ITEMS = :num_items
                WHERE ID_BULK_TRANSACTION = :id AND CLOSED = 0';
        $data = [
            ':id'        => $bulk->getIdBulkTransaction(),
            ':structure' => $bulk->getStructure(),
            ':hash'      => $bulk->getHash(),
            ':num_items' => $bulk->getNumItems()
        ];

        // Execute query
        if (!$this->db->action($sql, $data)) {
            if ($this->db->getError()) {
                throw $this->dbException();
            } else {
                throw new \Exception(409, Exceptions::ALREADY_CLOSED);
            }
        }
    }

    /**
     * Close an opened Bulk Transaction
     *
     * @param BulkTransaction $bulk
     *
     * @return BulkTransaction
     * @throws \Exception
     */
    public function closeBulk(BulkTransaction $bulk)
    {
        $bulk->clean();

        // Check data
        if (!$bulk->getIp() or !$bulk->getIdBulkTransaction() and
                               (!$bulk->getClientType() or !$bulk->getIdClient() or !$bulk->getExternalId())
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Obtain main id if it hasn't been given
        if (!$bulk->getIdBulkTransaction()) {
            $ip = $bulk->getIp();
            $bulk = $this->getBulk($bulk);

            if (!$bulk) {
                throw new \Exception(Exceptions::NON_EXISTENT, 409);
            }

            $bulk->setIp($ip);
        }

        $sql = 'UPDATE BULK_TRANSACTIONS SET CTRL_DATE_CLOSED = SYSDATE(), CTRL_IP_CLOSED = :ip, CLOSED = 1
                WHERE CLOSED = 0 AND ID_BULK_TRANSACTION = :id';

        $params = [':id' => $bulk->getIdBulkTransaction(), ':ip' => $bulk->getIp()];

        // Execute query
        if (!$this->db->action($sql, $params)) {
            if ($this->db->getError()) {
                throw $this->dbException();
            } else {
                throw new \Exception(409, Exceptions::ALREADY_CLOSED);
            }
        } else {
            return $bulk->setClosed(1);
        }
    }

    /**
     * Delete a Bulk Transaction, only for Events type
     *
     * @param BulkTransaction $bulk
     *
     * @throws \Exception
     */
    public function deleteBulk(BulkTransaction $bulk)
    {
        $bulk->clean();
        // Check remain data
        if (!$bulk->getIdBulkTransaction() and
            (!$bulk->getClientType() or !$bulk->getIdClient() or !$bulk->getExternalId())
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'DELETE FROM BULK_TRANSACTIONS WHERE CLOSED = 0';
        // Close with main id or external id and client data
        if ($bulk->getIdBulkTransaction()) {
            $sql .= ' AND ID_BULK_TRANSACTION = :id';
            $params = [':id' => $bulk->getIdBulkTransaction()];
        } else {
            $sql .= ' AND CLIENT_TYPE = :type AND FK_ID_CLIENT = :id_client AND EXTERNAL_ID = :id';
            $params = [
                ':type'      => $bulk->getClientType(),
                ':id_client' => $bulk->getIdClient(),
                ':id'        => $bulk->getExternalId()
            ];
        }

        // Execute query
        if (!$this->db->action($sql, $params)) {
            if ($this->db->getError()) {
                throw $this->dbException();
            } else {
                throw new \Exception(Exceptions::ALREADY_CLOSED, 409);
            }
        }
    }

    /**
     * Create a new bulk event
     *
     * @param BulkEvent $event
     *
     * @throws \Exception
     */
    public function createEvent(BulkEvent $event)
    {
        $event->clean();
        // Check remain data
        if (!$event->getClientType() or !$event->getIdClient() or !$event->getIdBulk() or !$event->getName() or
            !$event->getTimestamp() or !$event->getData() or !$event->getIp()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Prepare query and mandatory data
        $sql = 'INSERT INTO BULK_EVENTS(FK_ID_BULK, CLIENT_TYPE, FK_ID_CLIENT, NAME, TIMESTAMP, DATA, CTRL_DATE, CTRL_IP)
                VALUES (:id_bulk, :client_type, :id_client, :name, :timestamp, :data, SYSDATE(), :ip)';

        $data = [
            ':id_bulk'     => $event->getIdBulk(),
            ':client_type' => $event->getClientType(),
            ':id_client'   => $event->getIdClient(),
            ':name'        => $event->getName(),
            ':timestamp'   => $event->getFormattedTimestamp(),
            ':data'        => $event->getData(),
            ':ip'          => $event->getIp()
        ];

        // Execute query
        $this->db->action($sql, $data);
        if (!$this->db->getError()) {
            $event->setIdBulkEvent($this->db->lastInsertId());
        } else {
            throw $this->dbException();
        }
    }

    /**
     * Create a new bulk file
     *
     * @param BulkFile $file
     *
     * @throws \Exception
     */
    public function createFile(BulkFile $file)
    {
        $this->verifyBulkFile($file->clean());
        // Check remain data
        if (!$file->getIdClient() or !$file->getIdBulk() or !$file->getFileName() or !$file->getSize() or
            !$file->getHash() or !$file->getIp()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        /*
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
        */

        $this->db->beginTransaction();
        // Prepare query and mandatory data
        $sql = 'INSERT INTO BULK_FILES(FK_ID_BULK, UNIQUE_ID, FK_ID_USER, FILE_NAME, FILE_ORIG_NAME, FILE_TYPE, ID_SIGN,
                  FULL_NAME, FILE_DATE, FK_ID_CONTENT, QUALIFICATION, HASH, SIZE, CTRL_DATE, CTRL_IP)
                VALUES (:id_bulk, :unique_id, :id_user, :file_name, :file_orig, :file_type, :id_sign, :full_name, :file_date,
                 :id_content, :qualification, :hash, :size, SYSDATE(), :ip)';

        $data = [
            ':id_bulk'       => $file->getIdBulk(),
            ':unique_id'     => $file->getUniqueId(),
            ':id_user'       => $file->getIdClient(),
            ':file_name'     => $file->getFileName(),
            ':file_orig'     => $file->getFileOrigName(),
            ':file_type'     => $file->getFileType(),
            ':id_sign'       => $file->getIdSign(),
            ':full_name'     => $file->getFullName(),
            ':file_date'     => $file->getFormattedFileDate(),
            ':id_content'    => $file->getIdContent() ? $file->getIdContent() : null,
            ':qualification' => $file->getQualification() ? $file->getQualification() : null,
            ':hash'          => $file->getHash(),
            ':size'          => $file->getSize(),
            ':ip'            => $file->getIp()
        ];

        // Execute query
        $this->db->action($sql, $data);
        if (!$this->db->getError()) {
            $file->setIdBulkFile($this->db->lastInsertId());
            $this->db->commit();
        } else {
            $this->db->rollBack();
            throw $this->dbException();
        }
    }

    /**
     * Find an active bulk file by its unique Id or hash
     *
     * @param BulkFile $file
     *
     * @return BulkFile
     * @throws \Exception
     */
    public function findFile(BulkFile $file)
    {
        $file->clean();
        // Check mandatory data
        if (!$file->getUniqueId() and !$file->getHash()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Build the query
        $sql = 'SELECT ID_BULK_FILE, FK_ID_BULK, UNIQUE_ID, FK_ID_USER, FILE_NAME, FILE_ORIG_NAME, FILE_TYPE, ID_SIGN,
                  FULL_NAME, FILE_DATE, FK_ID_CONTENT, QUALIFICATION, HASH, SIZE, STATUS
                FROM BULK_FILES WHERE STATUS = :status';
        $data = [':status' => 'A'];

        if ($file->getUniqueId()) {
            $sql .= ' AND UNIQUE_ID = :id';
            $data[':id'] = $file->getUniqueId();
        } else {
            $sql .= ' AND HASH = :hash';
            $data[':hash'] = $file->getHash();
        }

        // Execute query
        $res = $this->db->query($sql, $data, 'Api\Entity\BulkFile');

        if (!$res or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $res->getNumRows() ? $res->getRows()[0] : null;
    }

    /**
     * Get the bulk types list of a client
     *
     * @param BulkType $bulk
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function bulkTypes(BulkType $bulk)
    {
        $bulk->clean();

        if (!$bulk->getClientType() or !$bulk->getIdClient()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = "SELECT TYPE, BULK_INFO FROM BULK_TYPES WHERE CLIENT_TYPE = :type AND FK_ID_CLIENT = :id";
        $params = [':type' => $bulk->getType(), ':id' => $bulk->getIdClient()];

        $data = $this->db->query($sql, $params, 'Api\Entity\BulkType');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
    }

    /**
     * Check if client is able to use requested type and get the type
     *
     * @param BulkType $bulk
     *
     * @return BulkType
     * @throws \Exception
     */
    public function getType(BulkType $bulk)
    {
        $bulk->clean();

        if (!$bulk->getClientType() or !$bulk->getIdClient() or !$bulk->getType()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = "SELECT CLIENT_TYPE, FK_ID_CLIENT, TYPE, ELEMENTS_TYPE, BULK_INFO, DEFAULT_INFO, CALLBACK_TYPE, CALLBACK_VALUE
                FROM BULK_TYPES WHERE CLIENT_TYPE = :type AND FK_ID_CLIENT = :id AND TYPE = :name";
        $params = [':type' => $bulk->getClientType(), ':id' => $bulk->getIdClient(), ':name' => $bulk->getType()];

        $res = $this->db->query($sql, $params, 'Api\Entity\BulkType');

        if (!$res or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $res->getNumRows() ? $res->getRows()[0] : null;
    }

    /**
     * Get blockchain info about a client
     *
     * @param BlockchainInfo $client
     *
     * @return BlockchainInfo
     * @throws \Exception
     */
    public function getBCInfo(BlockchainInfo $client)
    {
        $client->clean();

        if (!$client->getIdClient()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = "SELECT FK_ID_CLIENT, ACCOUNT, NUMBER_ADDRESSES, TOTAL_UNSPENTS, AVAILABLE_UNSPENTS
                FROM BLOCKCHAIN_INFO WHERE FK_ID_CLIENT = :id";
        $params = [':id' => $client->getIdClient()];

        $res = $this->db->query($sql, $params, 'Api\Entity\BlockchainInfo');

        if (!$res or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $res->getNumRows() ? $res->getRows()[0] : null;
    }

    /**
     * Get blockchain info about a client
     *
     * @param BlockchainInfo $client
     *
     * @throws \Exception
     */
    public function spendTransaction(BlockchainInfo $client)
    {
        $client->clean();

        if (!$client->getIdClient()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = "UPDATE BLOCKCHAIN_INFO SET AVAILABLE_UNSPENTS = AVAILABLE_UNSPENTS - 1 WHERE FK_ID_CLIENT = :id";
        $params = [':id' => $client->getIdClient()];

        // Execute query
        $this->db->action($sql, $params);
        if ($this->db->getError()) {
            throw $this->dbException();
        }
    }
}
<?php

namespace Api\Repository;

use Api\Entity\BulkFile;
use Api\Entity\BulkTransaction;
use Api\Entity\ResultSet;
use Bindeo\DataModel\Exceptions;
use \MaxMind\Db\Reader;
use Api\Entity\BlockChain;

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
        if (!$bulk->getIdUser() or !$bulk->getNumFiles() or !$bulk->getIp()) {
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
        if (!$file->getUniqueId() or !$file->getFileOrigName() or !$file->getFileType() or !$file->getIdSign() or !$file->getFullName() or !$file->getFileDate()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }
    }

    /**
     * Create a new Bulk Transaction
     *
     * @param BulkTransaction $bulk
     *
     * @throws \Exception
     */
    public function createBulk(BulkTransaction $bulk)
    {
        $this->verifyBulkTransaction($bulk->clean());

        // Check remain data
        if (!$bulk->getStructure() or !$bulk->getHash()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Look for another bulk with the same hash and same user
        $sql = "SELECT A.NUM FORBIDDEN, B.NUM WARNING FROM
                (SELECT COUNT(*) NUM FROM BULK_TRANSACTION WHERE STATUS = 'A' AND HASH = :hash AND FK_ID_USER = :id_user) A,
                (SELECT COUNT(*) NUM FROM BULK_TRANSACTION WHERE STATUS = 'A' AND HASH = :hash AND FK_ID_USER != :id_user) B";
        $res = $this->db->query($sql, ['id_user' => $bulk->getIdUser(), ':hash' => $bulk->getHash()]);

        if ($res->getRows()[0]['FORBIDDEN'] > 0) {
            throw new \Exception(Exceptions::DUPLICATED_FILE, 409);
        } elseif ($res->getRows()[0]['WARNING'] > 0) {
            // TODO Insert into the db logger
        }

        // Geolocalize the bulk transaction
        $this->geolocalize($bulk);

        // Prepare query and mandatory data
        $sql = 'INSERT INTO BULK_TRANSACTION(FK_ID_USER, NUM_FILES, STRUCTURE, HASH, CTRL_DATE, CTRL_IP, ID_GEONAMES,
                  LATITUDE, LONGITUDE)
                VALUES (:id_user, :num_files, :structure, :hash, SYSDATE(), :ip, :id_geonames, :latitude, :longitude)';

        $data = [
            ':id_user'     => $bulk->getIdUser(),
            ':num_files'   => $bulk->getNumFiles(),
            ':structure'   => $bulk->getStructure(),
            ':hash'        => $bulk->getHash(),
            ':ip'          => $bulk->getIp(),
            ':id_geonames' => $bulk->getIdGeonames() ? $bulk->getIdGeonames() : null,
            ':latitude'    => $bulk->getLatitude() ? $bulk->getLatitude() : null,
            ':longitude'   => $bulk->getLongitude() ? $bulk->getLongitude() : null
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
     * Update a Bulk Transaction with final structure and hash data
     *
     * @param BulkTransaction $bulk
     *
     * @throws \Exception
     */
    public function updateBulk(BulkTransaction $bulk)
    {
        $bulk->clean();
        // Check remain data
        if (!$bulk->getIdBulkTransaction() or !$bulk->getStructure() or !$bulk->getHash()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'UPDATE BULK_TRANSACTION SET STRUCTURE = :structure, HASH = :hash
                WHERE ID_BULK_TRANSACTION = :id';
        $data = [
            ':id'          => $bulk->getIdBulkTransaction(),
            ':structure'   => $bulk->getStructure(),
            ':hash'        => $bulk->getHash()
        ];

        // Execute query
        $this->db->action($sql, $data);
        if ($this->db->getError()) {
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
        if (!$file->getIdUser() or !$file->getIdBulk() or !$file->getFileName() or !$file->getSize() or !$file->getHash() or !$file->getIp()) {
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
            ':id_user'       => $file->getIdUser(),
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
}
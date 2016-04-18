<?php

namespace Api\Repository;

use Api\Entity\File;
use Api\Entity\ResultSet;
use Api\Entity\Signature;
use Bindeo\DataModel\NotarizableInterface;
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
     * @return BlockChain
     * @throws \Exception
     */
    public function findTransaction(BlockChain $blockchain)
    {
        if (!$blockchain->getTransaction()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT TRANSACTION, NET, CONFIRMED, CLIENT_TYPE, FK_ID_CLIENT, FK_ID_IDENTITY, HASH, JSON_DATA, CTRL_DATE, CTRL_IP,
                  TYPE, FK_ID_ELEMENT, STATUS_ELEMENT, ID_GEONAMES, LATITUDE, LONGITUDE FROM BLOCKCHAIN WHERE TRANSACTION = :id';
        $params = [':id' => $blockchain->getTransaction()];

        $data = $this->db->query($sql, $params, 'Api\Entity\BlockChain');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
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
        if (!$blockchain->getIdElement() or !$blockchain->getIdClient() or !$blockchain->getIp() or
            !$blockchain->getNet() or !$blockchain->getTransaction() or !$blockchain->getHash() or
            !$blockchain->getJsonData() or !in_array($blockchain->getType(), ['F', 'T', 'B'])
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the $blockChain
        $this->geolocalize($blockchain);

        $this->db->beginTransaction();
        // Insert the transaction
        $sql = "INSERT INTO BLOCKCHAIN(TRANSACTION, NET, CLIENT_TYPE, FK_ID_CLIENT, FK_ID_IDENTITY, HASH, JSON_DATA, CTRL_DATE,
                  CTRL_IP, TYPE, FK_ID_ELEMENT, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:txid, :net, :client_type, :id_client, :id_identity, :hash, :json_data, SYSDATE(), :ip, :type, :id_element,
                  :id_geonames, :latitude, :longitude)";
        $data = [
            ':txid'        => $blockchain->getTransaction(),
            ':net'         => $blockchain->getNet(),
            ':client_type' => $blockchain->getClientType(),
            ':id_client'   => $blockchain->getIdClient(),
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
                $sql = 'UPDATE BULK_TRANSACTIONS SET HASH = :hash, TRANSACTION = :txid WHERE ID_BULK_TRANSACTION = :id';
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
     * Get a list of unconfirmed blockchain transactions
     *
     * @param string $net [optional]
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function unconfirmedTransactions($net = 'bitcoin')
    {
        $sql = "SELECT TRANSACTION, NET, CONFIRMED, CLIENT_TYPE, FK_ID_CLIENT, FK_ID_IDENTITY, HASH, JSON_DATA, CTRL_DATE, CTRL_IP,
                  TYPE, FK_ID_ELEMENT, STATUS_ELEMENT, ID_GEONAMES, LATITUDE, LONGITUDE
                FROM BLOCKCHAIN WHERE CONFIRMED = 0 AND STATUS_ELEMENT = 'A' AND NET = :net";

        $data = $this->db->query($sql, [':net' => $net], 'Api\Entity\BlockChain');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
    }

    /**
     * Confirm blockchain transaction and associated element
     *
     * @param BlockChain $blockchain
     *
     * @throws \Exception
     */
    public function confirmTransaction(BlockChain $blockchain)
    {
        if (!$blockchain->getTransaction() or !$blockchain->getType() or !$blockchain->getIdElement()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Update blockchain registry
        $sql = 'UPDATE BLOCKCHAIN SET CONFIRMED = 1 WHERE TRANSACTION = :txid;';
        $params = [':txid' => $blockchain->getTransaction(), ':id' => $blockchain->getIdElement()];

        // Update associated element registry
        if ($blockchain->getType() == 'B') {
            $sql .= 'UPDATE BULK_TRANSACTIONS SET CONFIRMED = 1 WHERE ID_BULK_TRANSACTION = :id AND TRANSACTION = :txid;';
        } elseif ($blockchain->getType() == 'F') {
            $sql .= 'UPDATE FILES SET CONFIRMED = 1 WHERE ID_FILE = :id AND TRANSACTION = :txid;';
        } elseif ($blockchain->getType() == 'E') {
            $sql .= 'UPDATE EMAILS SET CONFIRMED = 1 WHERE ID_EMAIL = :id AND TRANSACTION = :txid;';
        }

        if (!$this->db->action($sql, $params)) {
            throw $this->dbException();
        }

        $blockchain->setConfirmed(1);
    }

    // FILE METHODS

    /**
     * Find a file by id
     *
     * @param NotarizableInterface $file
     *
     * @return File
     * @throws \Exception
     */
    public function findFile(NotarizableInterface $file)
    {
        if (!($file instanceof File) or !$file->getIdElement()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT ID_FILE, CLIENT_TYPE, FK_ID_CLIENT, MODE, FK_ID_MEDIA, NAME, FILE_NAME, FILE_ORIG_NAME, HASH, SIZE,
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
        if (!in_array($file->getClientType(), ['U', 'C']) or !$file->getIdClient() or !$file->getIdMedia() or
            !$file->getName() or !$file->getFileName() or !$file->getFileOrigName() or !$file->getIp() or
            !$file->getHash() or !$file->getMode()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Look for another file with the same hash and same user
        $sql = "SELECT A.NUM FORBIDDEN, B.NUM WARNING FROM
                (SELECT COUNT(*) NUM FROM FILES WHERE STATUS = 'A' AND HASH = :hash AND CLIENT_TYPE = :client_type AND FK_ID_CLIENT = :id_client) A,
                (SELECT COUNT(*) NUM FROM FILES WHERE STATUS = 'A' AND HASH = :hash AND CLIENT_TYPE = :client_type AND FK_ID_CLIENT != :id_client) B";
        $res = $this->db->query($sql, [
            ':client_type' => $file->getClientType(),
            ':id_client'   => $file->getIdClient(),
            ':hash'        => $file->getHash()
        ]);

        if ($res->getRows()[0]['FORBIDDEN'] > 0) {
            throw new \Exception(Exceptions::DUPLICATED_FILE, 409);
        } elseif ($res->getRows()[0]['WARNING'] > 0) {
            // TODO Insert into the db logger
        }

        // Geolocalize the file
        $this->geolocalize($file);

        $this->db->beginTransaction();
        // Prepare query and mandatory data
        if ($file->getClientType() == 'U') {
            $sql = 'UPDATE USERS SET STORAGE_LEFT = CASE WHEN TYPE > 1 THEN STORAGE_LEFT - :size ELSE 0 END WHERE ID_USER = :id_client;';
        } else {
            $sql = 'UPDATE OAUTH_CLIENTS SET STORAGE_LEFT = STORAGE_LEFT - :size WHERE ID_CLIENT = :id_client;';
        }

        $sql .= 'INSERT INTO FILES(CLIENT_TYPE, FK_ID_CLIENT, MODE, FK_ID_MEDIA, NAME, FILE_NAME, FILE_ORIG_NAME, HASH, SIZE,
                   CTRL_DATE, CTRL_IP, TAG, DESCRIPTION, ID_GEONAMES, LATITUDE, LONGITUDE)
                 VALUES (:client_type, :id_client, :mode, :id_media, :name, :file_name, :file_orig, :hash, :size, SYSDATE(), :ip, :tag,
                   :description, :id_geonames, :latitude, :longitude);';

        $data = [
            ':client_type' => $file->getClientType(),
            ':id_client'   => $file->getIdClient(),
            ':mode'        => $file->getMode(),
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
        $sql = 'SELECT ID_FILE, FILE_NAME, CLIENT_TYPE, FK_ID_CLIENT, SIZE, STATUS FROM FILES WHERE ID_FILE = :id';
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
            if ($file->getClientType() == 'U') {
                $sql = 'UPDATE USERS SET STORAGE_LEFT = CASE WHEN TYPE > 1 THEN STORAGE_LEFT + :size ELSE 0 END WHERE ID_USER = :id_client;';
            } else {
                $sql = 'UPDATE OAUTH_CLIENTS SET STORAGE_LEFT = STORAGE_LEFT + :size WHERE ID_CLIENT = :id_client;';
            }

            $sql .= 'DELETE FROM FILES WHERE ID_FILE = :id;';
            $data = [':id' => $file->getIdFile(), ':id_client' => $res->getIdClient(), ':size' => $res->getSize()];
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
        if (!$filter->getClientType() or !is_numeric($filter->getIdClient()) or !is_numeric($filter->getPage())) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $filter->clean();

        // Build the query
        $data = [
            'client_type' => $filter->getClientType(),
            ':id_client'  => $filter->getIdClient(),
            ':status'     => $filter->getStatus()
        ];
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

        $sql = "SELECT ID_FILE, CLIENT_TYPE, FK_ID_CLIENT, MODE, FK_ID_TYPE, FK_ID_MEDIA, NAME, FILE_NAME, FILE_ORIG_NAME,
                  HASH, SIZE, CTRL_DATE, TRANSACTION, CONFIRMED, STATUS, TAG, DESCRIPTION, ID_GEONAMES, LATITUDE, LONGITUDE
                FROM " . $from . " WHERE CLIENT_TYPE = :client_type AND FK_ID_CLIENT = :id_client" . $where .
               " AND STATUS = :status ORDER BY " . $order;

        return $this->db->query($sql, $data, 'Api\Entity\File', $filter->getPage(), $filter->getNumRows());
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

    /**
     * Associate signers to the signable element
     *
     * @param SignableInterface $element
     *
     * @throws \Exception
     */
    public function associateSigners(SignableInterface $element)
    {
        // Check data
        if (!is_array($element->getSigners()) or count($element->getSigners()) == 0 or !$element->getElementId() or
            !$element->getElementType()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Create new signers
        foreach ($element->getSigners() as $signer) {
            // Instantiate new signature object
            $signature = new Signature($signer);
            $signature->clean();
            if (!$signature->getEmail() or !$signature->getName()) {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }

            // Look for signer and user
            $sql = "SELECT S.NUM, U.FK_ID_USER, U.ID_IDENTITY
                    FROM (SELECT COUNT(*) NUM FROM SIGNERS WHERE EMAIL = :email) S
                    LEFT JOIN (SELECT VALUE, FK_ID_USER, ID_IDENTITY FROM USERS_IDENTITIES WHERE TYPE = 'E') U ON (VALUE = :email)";
            $params = [':email' => $signature->getEmail()];
            $res = $this->db->query($sql, $params);

            if ($res->getRows()[0]['NUM'] == 0) {
                // Insert new signer
                $sql = 'INSERT INTO SIGNERS(EMAIL, FK_ID_USER, FK_ID_IDENTITY, CTRL_DATE, CTRL_IP)
                        VALUES (:email, :id_user, :id_identity, SYSDATE(), :ip)';

                $params[':id_user'] = $res->getRows()[0]['FK_ID_USER'] ? $res->getRows()[0]['FK_ID_USER'] : null;
                $params[':id_identity'] = $res->getRows()[0]['ID_IDENTITY'] ? $res->getRows()[0]['ID_IDENTITY'] : null;
                $params[':ip'] = trim($element->getIp());

                if (!$this->db->action($sql, $params)) {
                    throw $this->dbException();
                }
            }

            // Insert signatures
            $sql = 'INSERT INTO SIGNATURES(ELEMENT_TYPE, ELEMENT_ID, EMAIL, NAME, ACCOUNT)
                    VALUES (:element_type, :element_id, :email, :name, :account)';

            $params = [
                ':element_type' => $element->getElementType(),
                ':element_id'   => $element->getElementId(),
                ':email'        => $signature->getEmail(),
                ':name'         => $signature->getName(),
                ':account'      => hash('sha256', $signature->getEmail())
            ];

            if (!$this->db->action($sql, $params)) {
                throw $this->dbException();
            }
        }
    }
}
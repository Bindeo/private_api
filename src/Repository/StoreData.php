<?php

namespace Api\Repository;

use Api\Entity\File;
use Api\Entity\ResultSet;
use Api\Entity\SignCode;
use Api\Entity\Signer;
use Api\Entity\UserIdentity;
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
                $sql = 'UPDATE FILES SET TRANSACTION = :txid WHERE ID_FILE = :id';
            } elseif ($blockchain->getType() == 'F') {
                $sql = 'UPDATE EMAILS SET TRANSACTION = :txid WHERE ID_EMAIL = :id';
            } elseif ($blockchain->getType() == 'B') {
                $sql = 'UPDATE BULK_TRANSACTIONS SET TRANSACTION = :txid WHERE ID_BULK_TRANSACTION = :id';
            }
            $data = [
                ':id'   => $blockchain->getIdElement(),
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
                  CTRL_DATE, TAG, DESCRIPTION, FK_ID_BULK, TRANSACTION, CONFIRMED, STATUS, ID_GEONAMES, LATITUDE, LONGITUDE
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
                  HASH, SIZE, CTRL_DATE, FK_ID_BULK, TRANSACTION, CONFIRMED, STATUS, TAG, DESCRIPTION, ID_GEONAMES, LATITUDE, LONGITUDE
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
     * Update some file data
     *
     * @param File $file
     *
     * @throws \Exception
     */
    public function updateFile(File $file)
    {
        if (!$file->getIdFile() or !$file->getIdBulk()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'UPDATE FILES SET FK_ID_BULK = :id_bulk WHERE ID_FILE = :id';
        $params = [':id' => $file->getIdFile(), ':id_bulk' => $file->getIdBulk()];

        if (!$this->db->action($sql, $params)) {
            throw $this->dbException();
        }
    }

    /**
     * Associate signers to the signable element
     *
     * @param SignableInterface $element
     *
     * @return Signer[]
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
        $signers = [];
        foreach ($element->getSigners() as $signer) {
            // Instantiate new signature object
            $signer = new Signer($signer);
            $signer->clean();
            if (!$signer->getEmail() or !$signer->getName()) {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }

            // Add signers
            if ($signer->getCreator()) {
                // Insert creator the first of the list
                array_unshift($signers, $signer);
            } else {
                // Insert others at the end of the list
                $signers[] = $signer;
            }

            // Look for user identity
            $sql = "SELECT FK_ID_USER, ID_IDENTITY, ACCOUNT FROM USERS_IDENTITIES WHERE TYPE = 'E' AND VALUE = :email";
            $params = [':email' => $signer->getEmail()];
            $res = $this->db->query($sql, $params, 'Api\Entity\UserIdentity');
            /** @var UserIdentity $identity */
            $identity = $res->getNumRows() == 1 ? $res->getRows()[0] : null;

            // Insert signatures
            $sql = 'INSERT INTO SIGNERS(ELEMENT_TYPE, ELEMENT_ID, CREATOR, EMAIL, NAME, ACCOUNT, TOKEN, TOKEN_EXPIRATION,
                      FK_ID_USER, FK_ID_IDENTITY, PHONE)
                    VALUES (:element_type, :element_id, :creator, :email, :name, :account, :token, SYSDATE() + INTERVAL 15 DAY,
                      :id_user, :id_identity, :phone)';

            $params = [
                ':element_type' => $element->getElementType(),
                ':element_id'   => $element->getElementId(),
                ':creator'      => $signer->getCreator() ? 1 : 0,
                ':email'        => $signer->getEmail(),
                ':name'         => $signer->getName(),
                ':account'      => $signer->setAccount($identity ? $identity->getAccount()
                    : hash('sha256', $signer->getEmail()))->getAccount(),
                ':token'        => $signer->setToken(hash('sha256',
                    $element->getElementType() . '_' . $element->getElementId() . '_' . $signer->getAccount() . '_' .
                    time()))->getToken(),
                ':id_user'      => $identity ? $identity->getIdUser() : null,
                ':id_identity'  => $identity ? $identity->getIdIdentity() : null,
                ':phone'        => $signer->getPhone() ? $signer->getPhone() : null
            ];

            if (!$this->db->action($sql, $params)) {
                throw $this->dbException();
            }
        }

        return $signers;
    }

    /**
     * Get the signers list of a signable element
     *
     * @param SignableInterface $element
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function signersList(SignableInterface $element)
    {
        // Check data
        if (!$element->getElementId() or !$element->getElementType()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get signers list
        $sql = 'SELECT S.ELEMENT_TYPE, S.ELEMENT_ID, S.CREATOR, S.EMAIL, S.NAME, S.ACCOUNT, U.LANG
                FROM SIGNERS S LEFT JOIN USERS U ON U.ID_USER = S.FK_ID_USER
                WHERE S.ELEMENT_TYPE = :type AND S.ELEMENT_ID = :id
                ORDER BY S.CREATOR DESC, S.EMAIL ASC';
        $params = [':type' => $element->getElementType(), ':id' => $element->getElementId()];

        return $this->db->query($sql, $params, 'Api\Entity\Signer');
    }

    /**
     * Get the signature creator
     *
     * @param SignableInterface $element
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function getSignatureCreator(SignableInterface $element)
    {
        // Check data
        if (!$element->getElementId() or !$element->getElementType()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get signature creator
        $sql = 'SELECT S.ELEMENT_TYPE, S.ELEMENT_ID, S.CREATOR, S.EMAIL, S.NAME, S.ACCOUNT, S.TOKEN, U.LANG
                FROM SIGNERS S, USERS U
                WHERE S.ELEMENT_TYPE = :type AND S.ELEMENT_ID = :id AND S.CREATOR = 1 AND U.ID_USER = S.FK_ID_USER';
        $params = [':type' => $element->getElementType(), ':id' => $element->getElementId()];

        return $this->db->query($sql, $params, 'Api\Entity\Signer');
    }

    /**
     * Get a signable element by a signer token
     *
     * @param $token
     *
     * @return SignableInterface
     * @throws \Exception
     */
    public function getSignableElement($token)
    {
        // Get requested token
        $sql = 'SELECT ELEMENT_TYPE, ELEMENT_ID, CREATOR, EMAIL, PHONE, NAME, FK_ID_USER, FK_ID_IDENTITY, PHONE, VIEWED, SIGNED
                FROM SIGNERS WHERE TOKEN = :token AND (SIGNED = 1 OR TOKEN_EXPIRATION > SYSDATE() AND SIGNED = 0)';
        $res = $this->db->query($sql, [':token' => trim($token)], 'Api\Entity\Signer');

        if ($res->getNumRows() == 0) {
            // Token doesn't exists or it is expired
            throw new \Exception(Exceptions::EXPIRED_TOKEN, 403);
        }

        // If token is correct, get associated element
        /** @var Signer $signer */
        $signer = $res->getRows()[0];

        $sql = 'UPDATE SIGNERS SET VIEWED = 1 WHERE TOKEN = :token AND VIEWED = 0';
        // Execute query
        $this->db->action($sql, [':token' => trim($token)]);

        if ($signer->getElementType() == 'F') {
            $sql = "SELECT F.ID_FILE, F.CLIENT_TYPE, F.FK_ID_CLIENT, F.MODE, F.FK_ID_MEDIA, F.NAME, F.FILE_NAME,
                      F.FILE_ORIG_NAME, F.HASH, F.SIZE, F.CTRL_DATE, F.TAG, F.DESCRIPTION, F.FK_ID_BULK, F.TRANSACTION,
                      F.CONFIRMED, F.STATUS, COUNT(S.SIGNED) - SUM(S.SIGNED) - 1 PENDING_SIGNERS
                    FROM FILES F, SIGNERS S
                    WHERE F.ID_FILE = :id AND S.ELEMENT_TYPE = 'F' AND S.ELEMENT_ID = F.ID_FILE";
            $class = 'Api\Entity\File';
        } else {
            return null;
        }

        // Get element
        $res = $this->db->query($sql, [':id' => $signer->getElementId()], $class);

        // If element exists, add current token signer
        if ($res->getNumRows() == 0) {
            return null;
        } else {
            return $res->getRows()[0]->setSigners([$signer]);
        }
    }

    /**
     * Get a pin code to sign a document by sending a signer token
     *
     * @param SignCode $code
     *
     * @return SignCode
     * @throws \Exception
     */
    public function getFreshSignCode(SignCode $code)
    {
        $code->clean();

        // Check data
        if (!$code->getToken() or !$code->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get requested token
        $sql = 'SELECT COUNT(*) NUM FROM SIGNERS WHERE TOKEN = :token AND TOKEN_EXPIRATION > SYSDATE() AND SIGNED = 0';
        $res = $this->db->query($sql, [':token' => $code->getToken()]);

        if ($res->getRows()[0]['NUM'] != 1) {
            // Token doesn't exists or it is expired
            throw new \Exception(Exceptions::EXPIRED_TOKEN, 403);
        }

        // Fill rest of data
        $code->setMethod('E')->generateCode();
        $this->geolocalize($code);

        // Generate new code and expire old one if exists
        $sql = 'UPDATE SIGN_CODES SET CODE_EXPIRATION = SYSDATE() WHERE TOKEN = :token AND CODE_EXPIRATION > SYSDATE();
                INSERT INTO SIGN_CODES(TOKEN, METHOD, CODE, CODE_EXPIRATION, CTRL_DATE, CTRL_IP, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:token, :method, :code, SYSDATE() + INTERVAL 10 MINUTE, SYSDATE(), :ip, :id_geonames, :latitude, :longitude);';

        $params = [
            ':token'       => $code->getToken(),
            ':method'      => $code->getMethod(),
            ':code'        => $code->getCode(),
            ':ip'          => $code->getIp(),
            ':id_geonames' => $code->getIdGeonames() ? $code->getIdGeonames() : null,
            ':latitude'    => $code->getLatitude() ? $code->getLatitude() : null,
            ':longitude'   => $code->getLongitude() ? $code->getLongitude() : null
        ];

        // Execute query
        if (!$this->db->action($sql, $params) and $this->db->getError()) {
            throw $this->dbException();
        }

        // Return code
        return $code->setIp(null)->setIdGeonames(null)->setLatitude(null)->setLongitude(null);
    }

    /**
     * Validate a signature code
     *
     * @param SignCode $code
     *
     * @return Signer
     * @throws \Exception
     */
    public function validateSignCode(SignCode $code)
    {
        $code->clean();

        // Check data
        if (!$code->getToken() or !$code->getCode() or !$code->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize code
        $this->geolocalize($code);

        // Get signature associated to code
        $sql = 'SELECT S.ELEMENT_TYPE, S.ELEMENT_ID, S.CREATOR, S.EMAIL, S.PHONE, S.NAME, S.FK_ID_USER, S.FK_ID_IDENTITY, S.ACCOUNT
                FROM SIGNERS S, SIGN_CODES C
                WHERE C.TOKEN = :token AND C.CODE = :code AND C.CODE_EXPIRATION > SYSDATE() AND
                  S.TOKEN = C.TOKEN AND S.TOKEN_EXPIRATION > SYSDATE() AND S.SIGNED = 0';
        $params = [':token' => $code->getToken(), ':code' => $code->getCode()];
        $res = $this->db->query($sql, $params, 'Api\Entity\Signer');

        if ($res->getNumRows() != 1) {
            // Code doesn't exists or it is expired
            throw new \Exception(Exceptions::EXPIRED_TOKEN, 403);
        }

        return $res->getRows()[0];
    }

    /**
     * Update a signer when he signs
     *
     * @param Signer $signer
     *
     * @throws \Exception
     */
    public function updateSigner(Signer $signer)
    {
        $signer->clean();

        // Check data
        if (!$signer->getElementType() or !$signer->getElementId() or !$signer->getEmail() or !$signer->getIp() or
            !($signer->getDate() instanceof \DateTime)
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize signer
        $this->geolocalize($signer);

        // Set as signed
        $sql = 'UPDATE SIGNERS SET SIGNED = 1, CTRL_DATE = :date, CTRL_IP = :ip, ID_GEONAMES = :id_geonames,
                  LATITUDE = :latitude, LONGITUDE = :longitude
                WHERE ELEMENT_TYPE = :element_type AND ELEMENT_ID = :element_id AND EMAIL = :email';
        $params = [
            ':element_type' => $signer->getElementType(),
            ':element_id'   => $signer->getElementId(),
            ':email'        => $signer->getEmail(),
            ':date'         => $signer->getFormattedDate(),
            ':ip'           => $signer->getIp(),
            ':id_geonames'  => $signer->getIdGeonames() ? $signer->getIdGeonames() : null,
            ':latitude'     => $signer->getLatitude() ? $signer->getLatitude() : null,
            ':longitude'    => $signer->getLongitude() ? $signer->getLongitude() : null
        ];

        // Execute query
        if (!$this->db->action($sql, $params)) {
            throw $this->dbException();
        }
    }
}
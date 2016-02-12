<?php

namespace Api\Repository;

use Api\Entity\ResultSet;
use Api\Entity\Client;
use Api\Model\General\Exceptions;
use \MaxMind\Db\Reader;

class Clients extends RepositoryLocatableAbstract
{
    /**
     * Change the account type of the client

*
*@param \Api\Entity\Client $client
     * @param int          $newType
     * @param ResultSet    $types [optional]

*
*@return Client
     * @throws \Exception
     */
    private function _changeType(Client $client, $newType, ResultSet $types = null)
    {
        if (!$client->getIdClient() or !in_array($client->getType(), [1, 2, 3]) or !in_array($newType,
                [1, 2, 3]) or !$client->getIp()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        if (!$types) {
            // Get the account types
            $sql = 'SELECT TYPE, MAX_STORAGE, MAX_STAMPS_MONTH FROM ACCOUNT_TYPES ORDER BY TYPE ASC';
            $types = $this->_db->query($sql, null, 'Api\Entity\AccountType');
        }

        // Geolocalize the client
        /** @var Client $client */
        $client = $this->_geolocalize($client);

        // The user hasn't paid yet, we change him to free user
        $sql = 'UPDATE CLIENTS_TYPES SET DATE_END = SYSDATE() WHERE FK_ID_CLIENT = :id AND DATE_END IS NULL;
                INSERT INTO CLIENTS_TYPES(FK_ID_CLIENT, FK_ID_TYPE, DATE_START, NEXT_PAYMENT, LAST_RESET)
                VALUES (:id, :type, SYSDATE(), CASE WHEN :type > 2 THEN SYSDATE() + INTERVAL 1 MONTH ELSE NULL END, CASE WHEN :type > 2 THEN SYSDATE() ELSE NULL END);
                UPDATE CLIENTS SET TYPE = :type, STAMPS_LEFT = :stamps, STORAGE_LEFT = CASE WHEN :type > 1 THEN STORAGE_LEFT + :storage ELSE 0 END,
                    LAST_RESET = SYSDATE(), LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude,
                    LAST_LONGITUDE = :longitude, CTRL_IP_MOD = :ip, CTRL_DATE_MOD = SYSDATE()
                WHERE ID_CLIENT = :id;';

        $storageLeft = $types->getRows()[$newType - 1]->getMaxStorage() - $types->getRows()[$client->getType() - 1]->getMaxStorage();

        $data = [
            ':id'          => $client->getIdClient(),
            ':type'        => $newType,
            ':stamps'      => $types->getRows()[$newType - 1]->getMaxStampsMonth(),
            ':storage'     => $storageLeft,
            ':ip'          => $client->getIp(),
            ':latitude'    => $client->getLatitude(),
            ':longitude'   => $client->getLongitude(),
            ':id_geonames' => $client->getIdGeonames()
        ];

        if (!$this->_db->action($sql, $data)) {
            throw $this->_dbException();
        }

        return $client->setType($newType)
                      ->setStorageLeft($newType > 1 ? $client->getStorageLeft() + $storageLeft : 0)
                      ->setStampsLeft($types->getRows()[$newType - 1]->getMaxStampsMonth());
    }

    /**
     * Renew a client account

*
*@param \Api\Entity\Client $client
     *
*@return Client
     * @throws \Exception
     */
    private function _renewAccount(Client $client)
    {
        if (!$client->getIdClient() or !in_array($client->getType(), [1, 2, 3]) or !$client->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the account types
        $sql = 'SELECT TYPE, MAX_STORAGE, MAX_STAMPS_MONTH FROM ACCOUNT_TYPES ORDER BY TYPE ASC';
        $resultSet = $this->_db->query($sql, null, 'Api\Entity\AccountType');
        $types = $resultSet->getRows();

        // Renew the client depending the type
        if ($client->getType() == 2) {
            // Free user, simply renew time and stamps
            $sql = 'UPDATE CLIENTS SET STAMPS_LEFT = :stamps, LAST_RESET = SYSDATE()
                    WHERE ID_CLIENT = :id AND LAST_RESET <= SYSDATE() - INTERVAL 1 MONTH';
            $data = [
                ':stamps' => $types[1]->getMaxStampsMonth(),
                ':id'     => $client->getIdClient()
            ];
            if ($this->_db->action($sql, $data)) {
                // Reset the stamps
                $client->setStampsLeft($types[1]->getMaxStampsMonth());
            } else {
                throw $this->_dbException();
            }
        } elseif ($client->getType() > 2) {
            // Pro user, check if the user has paid and update the reset date
            $sql = 'UPDATE CLIENTS_TYPES SET LAST_RESET = NEXT_PAYMENT - INTERVAL CEIL(DATEDIFF(NEXT_PAYMENT, SYSDATE())/30) MONTH
                    WHERE FK_ID_CLIENT = :id AND DATE_END IS NULL AND FK_ID_TYPE = :type AND NEXT_PAYMENT > SYSDATE()';
            $data = [':id' => $client->getIdClient(), ':type' => $client->getType()];

            if ($this->_db->action($sql, $data)) {
                // The user has already paid, reset his stamps
                $sql = 'UPDATE CLIENTS C SET STAMPS_LEFT = :stamps,
                        LAST_RESET = (SELECT LAST_RESET FROM CLIENTS_TYPES WHERE FK_ID_CLIENT = C.ID_CLIENT
                                      AND FK_ID_TYPE = C.TYPE AND DATE_END IS NULL)
                        WHERE C.ID_CLIENT = :id';

                $data = [
                    ':stamps' => $types[$client->getType() - 1]->getMaxStampsMonth(),
                    ':id'     => $client->getIdClient()
                ];

                if ($this->_db->action($sql, $data)) {
                    // Reset his stamps
                    $client->setStampsLeft($types[$client->getType() - 1]->getMaxStampsMonth());
                } else {
                    throw $this->_dbException();
                }
            } else {
                // The user hasn't paid yet, we change him to free user
                $client = $this->_changeType($client, 2, $resultSet);
            }
        }

        return $client;
    }

    /**
     * Find a user by id
     *
     * @param Client $client
     *
     * @return Client
     * @throws \Exception
     */
    public function find(Client $client)
    {
        if (!$client->getIdClient()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT ID_CLIENT, EMAIL, TYPE, NAME, SURNAME, CONFIRMED, LANG, STORAGE_LEFT, STAMPS_LEFT
                FROM CLIENTS WHERE ID_CLIENT = :id';
        $params = [':id' => $client->getIdClient()];

        $data = $this->_db->query($sql, $params, 'Api\Entity\Client');

        if (!$data or $this->_db->getError()) {
            throw new \Exception($this->_db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
    }

    /**
     * Create a new client
     *
     * @param Client $client
     *
     * @return array
     * @throws \Exception
     */
    public function create(Client $client)
    {
        $client->clean();
        // Check the received data
        if (!$client->getEmail() or !$client->getPassword() or !in_array($client->getType(),
                [1, 2, 3]) or !$client->getName() or !$client->getIp() or !$client->getLang()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Obtain the account type data
        $sql = 'SELECT ID_TYPE, TYPE, COST, MAX_STORAGE, MAX_STAMPS_MONTH FROM ACCOUNT_TYPES WHERE ID_TYPE = :id';
        $data = $this->_db->query($sql, [':id' => $client->getType()], 'Api\Entity\AccountType');

        if (!$data or $this->_db->getError() or $data->getNumRows() != 1) {
            throw new \Exception($this->_db->getError(), 400);
        }

        // Geolocalize the client

        /** @var Client $client */
        $client = $this->_geolocalize($client);

        $this->_db->beginTransaction();
        // Prepare query and mandatory data
        $sql = 'INSERT INTO CLIENTS(EMAIL, PASSWORD, TYPE, NAME, SURNAME, CTRL_IP_SIGNUP, CTRL_DATE_SIGNUP, LANG,
                  LAST_ID_GEONAMES, LAST_LATITUDE, LAST_LONGITUDE, STORAGE_LEFT, LAST_RESET, STAMPS_LEFT)
                VALUES (:email, :password, :type, :name, :surname, :ip, SYSDATE(), :lang, :id_geonames, :latitude,
                  :longitude, :storage, SYSDATE(), :stamps)';
        $data = [
            ':email'       => mb_strtolower($client->getEmail()),
            ':password'    => password_hash($client->getPassword(), PASSWORD_DEFAULT),
            ':type'        => $client->getType(),
            ':name'        => $client->getName(),
            ':surname'     => $client->getSurname() ? $client->getSurname() : null,
            ':ip'          => $client->getIp(),
            ':lang'        => $client->getLang(),
            ':id_geonames' => $client->getIdGeonames() ? $client->getIdGeonames() : null,
            ':latitude'    => $client->getLatitude() ? $client->getLatitude() : null,
            ':longitude'   => $client->getLongitude() ? $client->getLongitude() : null,
            ':storage'     => $data->getRows()[0]->getMaxStorage(),
            ':stamps'      => $data->getRows()[0]->getMaxStampsMonth()
        ];

        // Execute query
        if (!$this->_db->action($sql, $data)) {
            $this->_db->rollBack();
            throw $this->_dbException();
        }

        $id = (int)$this->_db->lastInsertId();

        // Generate validation code
        $token = md5($id . $client->getEmail() . time());

        // Insert the validation token and the active client type
        $sql = 'INSERT INTO CLIENTS_VALIDATIONS(TOKEN, TYPE, FK_ID_CLIENT, EMAIL, CTRL_DATE, CTRL_IP)
                VALUES (:token, :type_val, :id, :email, SYSDATE(), :ip);
                INSERT INTO CLIENTS_TYPES(FK_ID_CLIENT, FK_ID_TYPE, DATE_START, NEXT_PAYMENT, LAST_RESET)
                VALUES (:id, :type, SYSDATE(), CASE WHEN :type > 2 THEN SYSDATE() + INTERVAL 1 MONTH ELSE NULL END, CASE WHEN :type > 2 THEN SYSDATE() ELSE NULL END);';
        $data = [
            ':token'    => $token,
            ':type_val' => 'V',
            ':id'       => $id,
            ':type'     => $client->getType(),
            ':email'    => mb_strtolower($client->getEmail()),
            ':ip'       => $client->getIp()
        ];

        if (!$this->_db->action($sql, $data)) {
            $this->_db->rollBack();
            throw $this->_dbException();
        }

        // Commit transaction
        $this->_db->commit();

        return ['id' => $id, 'token' => $token];
    }

    /**
     * Modify an account
     *
     * @param Client $client
     *
     * @return Client
     * @throws \Exception
     */
    public function modify(Client $client)
    {
        $client->clean();

        // Check the type of the update
        if (!$client->getIdClient() or !is_numeric($client->getType()) or !$client->getName() or !$client->getIp() or !$client->getLang()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the client
        /** @var Client $client */
        $client = $this->_geolocalize($client);

        // Prepare query and data
        $sql = 'UPDATE CLIENTS SET NAME = :name, SURNAME = :surname, CTRL_IP_MOD = :ip, CTRL_DATE_MOD = SYSDATE(),
                LANG = :lang, LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                WHERE ID_CLIENT = :id';

        $data = [
            ':id'          => $client->getIdClient(),
            ':name'        => $client->getName(),
            ':surname'     => $client->getSurname() ? $client->getSurname() : null,
            ':ip'          => $client->getIp(),
            ':lang'        => $client->getLang(),
            ':id_geonames' => $client->getIdGeonames() ? $client->getIdGeonames() : null,
            ':latitude'    => $client->getLatitude() ? $client->getLatitude() : null,
            ':longitude'   => $client->getLongitude() ? $client->getLongitude() : null
        ];

        // Execute query
        if ($this->_db->action($sql, $data)) {
            return $this->find($client);
        } else {
            throw $this->_dbException();
        }
    }

    /**
     * Modify an account password

*
*@param Client $client


*
*@return \Api\Entity\ResultSet
     * @throws \Exception
     */
    public function modifyPassword(Client $client)
    {
        $client->clean();

        // Check the requested params
        if (!$client->getIdClient() or !$client->getPassword() or !$client->getOldPassword() or !$client->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Check if the password is correct
        $sql = 'SELECT PASSWORD FROM CLIENTS WHERE ID_CLIENT = :id';
        $res = $this->_db->query($sql, ['id' => $client->getIdClient()]);
        if (!$res or $res->getNumRows() != 1 or !password_verify($client->getOldPassword(),
                $res->getRows()[0]['PASSWORD'])
        ) {
            throw new \Exception(Exceptions::INCORRECT_PASSWORD, 400);
        }

        // Geolocalize the client
        /** @var \Api\Entity\Client $client */
        $client = $this->_geolocalize($client);

        // Prepare query and data
        $sql = 'UPDATE CLIENTS SET PASSWORD = :password, CTRL_IP_MOD = :ip, CTRL_DATE_MOD = SYSDATE(),
                LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                WHERE ID_CLIENT = :id';

        $data = [
            ':id'          => $client->getIdClient(),
            ':password'    => password_hash($client->getPassword(), PASSWORD_DEFAULT),
            ':ip'          => $client->getIp(),
            ':id_geonames' => $client->getIdGeonames() ? $client->getIdGeonames() : null,
            ':latitude'    => $client->getLatitude() ? $client->getLatitude() : null,
            ':longitude'   => $client->getLongitude() ? $client->getLongitude() : null
        ];

        // Execute query
        if ($this->_db->action($sql, $data)) {
            return true;
        } else {
            throw $this->_dbException();
        }
    }

    /**
     * Modify an account type
     *
     * @param Client $client
     *
     * @return Client
     * @throws \Exception
     */
    public function changeType(Client $client)
    {
        $client->clean();

        if (!$client->getIdClient() or !in_array($client->getType(), [1, 2, 3]) or !$client->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Retrive the full user of the db
        $user = $this->find($client);
        if (!$user) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }
        $user->setIp($client->getIp());

        // Change the user type
        return $this->_changeType($user, $client->getType());
    }

    /**
     * Modify an account email
     *
     * @param Client $client
     *
     * @return string
     * @throws \Exception
     */
    public function modifyEmail(Client $client)
    {
        $client->clean();

        // Check data
        if (!$client->getIdClient() or !$client->getPassword() or !$client->getEmail() or !$client->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Check if the password is correct
        $sql = 'SELECT PASSWORD, EMAIL FROM CLIENTS WHERE ID_CLIENT = :id';
        $res = $this->_db->query($sql, ['id' => $client->getIdClient()]);
        if (!$res or $res->getNumRows() != 1 or !password_verify($client->getPassword(),
                $res->getRows()[0]['PASSWORD'])
        ) {
            throw new \Exception(Exceptions::INCORRECT_PASSWORD, 400);
        }

        // Generate validation code
        $token = md5($client->getIdClient() . $client->getEmail() . time());

        // Insert the validation token
        $sql = 'INSERT INTO CLIENTS_VALIDATIONS(TOKEN, TYPE, FK_ID_CLIENT, EMAIL, CTRL_DATE, CTRL_IP, OLD_EMAIL)
                    VALUES (:token, :type, :id, :email, SYSDATE(), :ip, :old_email);';

        $data = [
            ':token'     => $token,
            ':type'      => 'E',
            ':id'        => $client->getIdClient(),
            ':email'     => mb_strtolower($client->getEmail()),
            ':ip'        => $client->getIp(),
            ':old_email' => $res->getRows()[0]['PASSWORD']
        ];

        if ($this->_db->action($sql, $data)) {
            return $token;
        } else {
            throw $this->_dbException();
        }
    }

    /**
     * Validate a received token
     *
     * @param string $token
     * @param string $ip
     * @param string $password
     *
     * @return string
     * @throws \Exception
     */
    public function validateToken($token, $ip, $password = null)
    {
        // Retrieve the token information if is still valid
        $sql = "SELECT TOKEN, TYPE, FK_ID_CLIENT, EMAIL FROM CLIENTS_VALIDATIONS
                WHERE TOKEN = :token AND CONFIRMED = 0 AND (TYPE = 'V' OR CTRL_DATE < SYSDATE() + INTERVAL 1 DAY)";
        $res = $this->_db->query($sql, ['token' => trim($token)]);
        if (!$res or $res->getNumRows() == 0) {
            throw new \Exception(Exceptions::EXPIRED_TOKEN, 400);
        }

        $res = $res->getRows()[0];

        // Geolocalize the client
        /** @var Client $client */
        $client = $this->_geolocalize(new Client([
            'id_client' => $res['FK_ID_CLIENT'],
            'email'     => $res['EMAIL'],
            'ip'        => $ip
        ]));

        // Process the token
        $sql = 'UPDATE CLIENTS_VALIDATIONS SET CONFIRMED = 1 WHERE TOKEN = :token';
        $this->_db->action($sql, ['token' => $token]);

        if ($res['TYPE'] == 'V') {
            // Initial account validation
            $sql = 'UPDATE CLIENTS SET CONFIRMED = 1, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip,
                    LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                    WHERE ID_CLIENT = :id';
            $params = [
                ':id'          => $client->getIdClient(),
                ':ip'          => $client->getIp(),
                ':latitude'    => $client->getLatitude(),
                ':longitude'   => $client->getLongitude(),
                ':id_geonames' => $client->getIdGeonames()
            ];
        } elseif ($res['TYPE'] == 'E') {
            // Confirm the email change
            $sql = 'UPDATE CLIENTS SET CONFIRMED = 1, EMAIL = :email, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip,
                    LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                    WHERE ID_CLIENT = :id';
            $params = [
                ':id'          => $client->getIdClient(),
                ':email'       => $client->getEmail(),
                ':ip'          => $client->getIp(),
                ':latitude'    => $client->getLatitude(),
                ':longitude'   => $client->getLongitude(),
                ':id_geonames' => $client->getIdGeonames()
            ];
        } elseif ($res['TYPE'] == 'P' and $password) {
            // Password recovery
            $sql = 'UPDATE CLIENTS SET CONFIRMED = 1, PASSWORD = :password, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip,
                    LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                    WHERE ID_CLIENT = :id';
            $params = [
                ':id'          => $client->getIdClient(),
                ':password'    => password_hash($password, PASSWORD_DEFAULT),
                ':ip'          => $client->getIp(),
                ':latitude'    => $client->getLatitude(),
                ':longitude'   => $client->getLongitude(),
                ':id_geonames' => $client->getIdGeonames()
            ];
        } else {
            throw new \Exception(Exceptions::EXPIRED_TOKEN, 400);
        }

        // Execute query
        if ($this->_db->action($sql, $params)) {
            return $client;
        } else {
            throw $this->_dbException();
        }
    }

    /**
     * Login the user
     *
     * @param Client $client
     *
     * @return Client
     * @throws \Exception
     */
    public function login(Client $client)
    {
        if (!$client->getEmail() or !$client->getPassword() or !$client->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Try to get the user
        $sql = 'SELECT ID_CLIENT, PASSWORD, EMAIL, TYPE, NAME, SURNAME, CONFIRMED, LANG, STORAGE_LEFT, STAMPS_LEFT,
                CASE WHEN TYPE > 1 AND LAST_RESET <= SYSDATE() - INTERVAL 1 MONTH THEN 1 ELSE 0 END RENEW
                FROM CLIENTS WHERE EMAIL = :email';
        $client->clean();
        $params = [':email' => mb_strtolower($client->getEmail())];
        $data = $this->_db->query($sql, $params, 'Api\Entity\Client');

        // If we don't have the user, maybe the pass is not correct
        /** @var \Api\Entity\Client $user */
        if ($data->getNumRows() == 0 or !($user = $data->getRows()[0]) or !password_verify($client->getPassword(),
                $user->getPassword())
        ) {
            throw new \Exception(Exceptions::INCORRECT_PASSWORD, 400);
        } elseif (!$data and $this->_db->getError()) {
            throw new \Exception($this->_db->getError(), 400);
        }

        $user->setIp($client->getIp())
             ->setLatitude($client->getLatitude())
             ->setLongitude($client->getLongitude())
             ->setIdGeonames($client->getIdGeonames());
        unset($client);

        // Renew the account if necessary
        if ($user->getRenew()) {
            $user = $this->_renewAccount($user);
        }
        $user->setPassword(null)->setRenew(null);

        // Geolocalize the client
        $user = $this->_geolocalize($user);

        // Login is successful, record tracking data
        $this->_db->beginTransaction();
        $sql = 'UPDATE CLIENTS SET CTRL_IP_LOGIN = :ip, CTRL_DATE_LOGIN = SYSDATE(), LAST_ID_GEONAMES = :id_geonames,
                                   LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                WHERE ID_CLIENT = :id';
        $params = [
            ':id'          => $user->getIdClient(),
            ':ip'          => $user->getIp(),
            ':latitude'    => $user->getLatitude(),
            ':longitude'   => $user->getLongitude(),
            ':id_geonames' => $user->getIdGeonames()
        ];

        $res = $this->_db->action($sql, $params);

        $sql = 'INSERT INTO CLIENTS_LOGINS(FK_ID_CLIENT, EMAIL, CTRL_DATE, CTRL_IP, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:id, :email, SYSDATE(), :ip, :id_geonames, :latitude, :longitude)';
        $params = [
            ':id'          => $user->getIdClient(),
            ':email'       => $user->getEmail(),
            ':ip'          => $user->getIp(),
            ':latitude'    => $user->getLatitude(),
            ':longitude'   => $user->getLongitude(),
            ':id_geonames' => $user->getIdGeonames()
        ];

        if ($res) {
            $res = $this->_db->action($sql, $params);
        }

        // If everything has gone ok, we commit the transaction else we do rollback
        if ($res) {
            $this->_db->commit();
        } else {
            $this->_db->rollBack();
            throw new \Exception('Login failed', 500);
        }

        return $user;
    }

    /**
     * Delete an account
     *
     * @param Client $client
     *
     * @return bool
     * @throws \Exception
     */
    public function delete(Client $client)
    {
        if (!$client->getIdClient() or !$client->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the client
        /** @var Client $client */
        $client = $this->_geolocalize($client);

        $this->_db->beginTransaction();
        $sql = 'UPDATE CLIENTS SET CTRL_IP_MOD = :ip, CTRL_DATE_MOD = SYSDATE(), LAST_ID_GEONAMES = :id_geonames,
                                   LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                WHERE ID_CLIENT = :id';

        $params = [
            ':id'          => $client->getIdClient(),
            ':ip'          => $client->getIp(),
            ':latitude'    => $client->getLatitude(),
            ':longitude'   => $client->getLongitude(),
            ':id_geonames' => $client->getIdGeonames()
        ];
        $res = $this->_db->action($sql, $params);

        $sql = 'DELETE FROM CLIENTS WHERE ID_CLIENT = :id';

        // Execute query
        if ($res) {
            $res = $this->_db->action($sql, [':id' => $client->getIdClient()]);
        }

        // If everything has gone ok, we commit the transaction else we do rollback
        if ($res) {
            $this->_db->commit();
        } else {
            $this->_db->rollBack();
            throw new \Exception('', 500);
        }
    }
}
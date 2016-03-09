<?php

namespace Api\Repository;

use Api\Entity\ResultSet;
use Api\Entity\User;
use Api\Entity\UserIdentity;
use Bindeo\DataModel\Exceptions;
use \MaxMind\Db\Reader;

class Users extends RepositoryLocatableAbstract
{
    /**
     * Change the account type of the user
     *
     * @param \Api\Entity\User $user
     * @param int              $newType
     * @param ResultSet        $types [optional]
     *
     * @return User
     * @throws \Exception
     */
    private function changeType(User $user, $newType, ResultSet $types = null)
    {
        if (!$user->getIdUser() or !in_array($user->getType(), [1, 2, 3]) or !in_array($newType,
                [1, 2, 3]) or !$user->getIp()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        if (!$types) {
            // Get the account types
            $sql = 'SELECT ID_TYPE, MAX_STORAGE, MAX_STAMPS_MONTH FROM ACCOUNT_TYPES ORDER BY ID_TYPE ASC';
            $types = $this->db->query($sql, null, 'Api\Entity\AccountType');
        }

        // Geolocalize the user
        /** @var User $user */
        $user = $this->geolocalize($user);

        // The user hasn't paid yet, we change him to free user
        $sql = 'UPDATE USERS_TYPES SET DATE_END = SYSDATE() WHERE FK_ID_USER = :id AND DATE_END IS NULL;
                INSERT INTO USERS_TYPES(FK_ID_USER, FK_ID_TYPE, DATE_START, NEXT_PAYMENT, LAST_RESET)
                VALUES (:id, :type, SYSDATE(), CASE WHEN :type > 2 THEN SYSDATE() + INTERVAL 1 MONTH ELSE NULL END, CASE WHEN :type > 2 THEN SYSDATE() ELSE NULL END);
                UPDATE USERS SET TYPE = :type, STAMPS_LEFT = :stamps, STORAGE_LEFT = CASE WHEN :type > 1 THEN STORAGE_LEFT + :storage ELSE 0 END,
                    LAST_RESET = SYSDATE(), LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude,
                    LAST_LONGITUDE = :longitude, CTRL_IP_MOD = :ip, CTRL_DATE_MOD = SYSDATE()
                WHERE ID_USER = :id;';

        $storageLeft = $types->getRows()[$newType - 1]->getMaxStorage() - $types->getRows()[$user->getType() - 1]->getMaxStorage();

        $data = [
            ':id'          => $user->getIdUser(),
            ':type'        => $newType,
            ':stamps'      => $types->getRows()[$newType - 1]->getMaxStampsMonth(),
            ':storage'     => $storageLeft,
            ':ip'          => $user->getIp(),
            ':latitude'    => $user->getLatitude(),
            ':longitude'   => $user->getLongitude(),
            ':id_geonames' => $user->getIdGeonames()
        ];

        if (!$this->db->action($sql, $data)) {
            throw $this->dbException();
        }

        return $user->setType($newType)
                    ->setStorageLeft($newType > 1 ? $user->getStorageLeft() + $storageLeft : 0)
                    ->setStampsLeft($types->getRows()[$newType - 1]->getMaxStampsMonth());
    }

    /**
     * Renew a user account
     *
     * @param \Api\Entity\User $user
     *
     * @return User
     * @throws \Exception
     */
    private function renewAccount(User $user)
    {
        if (!$user->getIdUser() or !in_array($user->getType(), [1, 2, 3]) or !$user->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the account types
        $sql = 'SELECT ID_TYPE, MAX_STORAGE, MAX_STAMPS_MONTH FROM ACCOUNT_TYPES ORDER BY ID_TYPE ASC';
        $resultSet = $this->db->query($sql, null, 'Api\Entity\AccountType');
        $types = $resultSet->getRows();

        // Renew the user depending the type
        if ($user->getType() == 2) {
            // Free user, simply renew time and stamps
            $sql = 'UPDATE USERS SET STAMPS_LEFT = :stamps, LAST_RESET = SYSDATE()
                    WHERE ID_USER = :id AND LAST_RESET <= SYSDATE() - INTERVAL 1 MONTH';
            $data = [
                ':stamps' => $types[1]->getMaxStampsMonth(),
                ':id'     => $user->getIdUser()
            ];
            if ($this->db->action($sql, $data)) {
                // Reset the stamps
                $user->setStampsLeft($types[1]->getMaxStampsMonth());
            } else {
                throw $this->dbException();
            }
        } elseif ($user->getType() > 2) {
            // Pro user, check if the user has paid and update the reset date
            $sql = 'UPDATE USERS_TYPES SET LAST_RESET = NEXT_PAYMENT - INTERVAL CEIL(DATEDIFF(NEXT_PAYMENT, SYSDATE())/30) MONTH
                    WHERE FK_ID_USER = :id AND DATE_END IS NULL AND FK_ID_TYPE = :type AND NEXT_PAYMENT > SYSDATE()';
            $data = [':id' => $user->getIdUser(), ':type' => $user->getType()];

            if ($this->db->action($sql, $data)) {
                // The user has already paid, reset his stamps
                $sql = 'UPDATE USERS C SET STAMPS_LEFT = :stamps,
                        LAST_RESET = (SELECT LAST_RESET FROM USERS_TYPES WHERE FK_ID_USER = C.ID_USER
                                      AND FK_ID_TYPE = C.TYPE AND DATE_END IS NULL)
                        WHERE C.ID_USER = :id';

                $data = [
                    ':stamps' => $types[$user->getType() - 1]->getMaxStampsMonth(),
                    ':id'     => $user->getIdUser()
                ];

                if ($this->db->action($sql, $data)) {
                    // Reset his stamps
                    $user->setStampsLeft($types[$user->getType() - 1]->getMaxStampsMonth());
                } else {
                    throw $this->dbException();
                }
            } else {
                // The user hasn't paid yet, we change him to free user
                $user = $this->changeType($user, 2, $resultSet);
            }
        }

        return $user;
    }

    /**
     * Find a user by id
     *
     * @param User $user
     *
     * @return User
     * @throws \Exception
     */
    public function find(User $user)
    {
        if (!$user->getIdUser()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT ID_USER, EMAIL, PASSWORD, TYPE, NAME, CONFIRMED, LANG, STORAGE_LEFT, STAMPS_LEFT
                FROM USERS WHERE ID_USER = :id';
        $params = [':id' => $user->getIdUser()];

        $data = $this->db->query($sql, $params, 'Api\Entity\User');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
    }

    /**
     * Find a user by email
     *
     * @param User $user
     *
     * @return User
     * @throws \Exception
     */
    public function findEmail(User $user)
    {
        if (!$user->getEmail()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $user->clean();

        $sql = 'SELECT ID_USER, EMAIL, PASSWORD, TYPE, NAME, CONFIRMED, LANG, STORAGE_LEFT, STAMPS_LEFT
                FROM USERS WHERE EMAIL = :email';
        $params = [':email' => mb_strtolower($user->getEmail())];

        $data = $this->db->query($sql, $params, 'Api\Entity\User');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
    }

    /**
     * Create a new user
     *
     * @param User $user
     *
     * @return array
     * @throws \Exception
     */
    public function create(User $user)
    {
        $user->clean();
        // Check the received data
        if (!$user->getEmail() or !$user->getPassword() or !in_array($user->getType(),
                [1, 2, 3]) or !$user->getName() or !$user->getIp() or !$user->getLang()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Obtain the account type data
        $sql = 'SELECT ID_TYPE, COST, MAX_STORAGE, MAX_STAMPS_MONTH FROM ACCOUNT_TYPES WHERE ID_TYPE = :id';
        $data = $this->db->query($sql, [':id' => $user->getType()], 'Api\Entity\AccountType');

        if (!$data or $this->db->getError() or $data->getNumRows() != 1) {
            throw new \Exception($this->db->getError(), 400);
        }

        // Geolocalize the user
        /** @var User $user */
        $user = $this->geolocalize($user);

        $this->db->beginTransaction();
        // Prepare query and mandatory data
        $sql = 'INSERT INTO USERS(EMAIL, PASSWORD, TYPE, NAME, CTRL_IP_SIGNUP, CTRL_DATE_SIGNUP, LANG,
                  LAST_ID_GEONAMES, LAST_LATITUDE, LAST_LONGITUDE, STORAGE_LEFT, LAST_RESET, STAMPS_LEFT)
                VALUES (:email, :password, :type, :name, :ip, SYSDATE(), :lang, :id_geonames, :latitude,
                  :longitude, :storage, SYSDATE(), :stamps)';
        $data = [
            ':email'       => mb_strtolower($user->getEmail()),
            ':password'    => password_hash($user->getPassword(), PASSWORD_DEFAULT),
            ':type'        => $user->getType(),
            ':name'        => $user->getName(),
            ':ip'          => $user->getIp(),
            ':lang'        => $user->getLang(),
            ':id_geonames' => $user->getIdGeonames() ? $user->getIdGeonames() : null,
            ':latitude'    => $user->getLatitude() ? $user->getLatitude() : null,
            ':longitude'   => $user->getLongitude() ? $user->getLongitude() : null,
            ':storage'     => $data->getRows()[0]->getMaxStorage(),
            ':stamps'      => $data->getRows()[0]->getMaxStampsMonth()
        ];

        // Execute query
        if (!$this->db->action($sql, $data)) {
            $this->db->rollBack();
            throw $this->dbException();
        }

        $id = (int)$this->db->lastInsertId();

        // Generate validation code
        $token = md5($id . $user->getEmail() . time());

        // Insert the validation token, the active user type and the first identity
        $sql = "INSERT INTO USERS_VALIDATIONS(TOKEN, TYPE, FK_ID_USER, EMAIL, CTRL_DATE, CTRL_IP)
                VALUES (:token, :type_val, :id, :email, SYSDATE(), :ip);
                INSERT INTO USERS_TYPES(FK_ID_USER, FK_ID_TYPE, DATE_START, NEXT_PAYMENT, LAST_RESET)
                VALUES (:id, :type, SYSDATE(), CASE WHEN :type > 2 THEN SYSDATE() + INTERVAL 1 MONTH ELSE NULL END, CASE WHEN :type > 2 THEN SYSDATE() ELSE NULL END);
                INSERT INTO USERS_IDENTITIES(FK_ID_USER, MAIN, TYPE, NAME, VALUE, CTRL_IP, CTRL_DATE)
                VALUES (:id, 1, 'E', :name, :email, :ip, SYSDATE());";
        $data = [
            ':token'    => $token,
            ':type_val' => 'V',
            ':id'       => $id,
            ':type'     => $user->getType(),
            ':email'    => mb_strtolower($user->getEmail()),
            ':ip'       => $user->getIp(),
            ':name'     => $user->getName()
        ];

        if (!$this->db->action($sql, $data)) {
            $this->db->rollBack();
            throw $this->dbException();
        }

        // Commit transaction
        $this->db->commit();

        return ['idUser' => $id, 'token' => $token];
    }

    /**
     * Modify an account
     *
     * @param User $user
     *
     * @return User
     * @throws \Exception
     */
    public function modify(User $user)
    {
        $user->clean();

        // Check the type of the update
        if (!$user->getIdUser() or !$user->getIp() or !$user->getLang()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the user
        /** @var User $user */
        $user = $this->geolocalize($user);

        // Prepare query and data
        $sql = 'UPDATE USERS SET CTRL_IP_MOD = :ip, CTRL_DATE_MOD = SYSDATE(), LANG = :lang,
                  LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                WHERE ID_USER = :id';

        $data = [
            ':id'          => $user->getIdUser(),
            ':ip'          => $user->getIp(),
            ':lang'        => $user->getLang(),
            ':id_geonames' => $user->getIdGeonames() ? $user->getIdGeonames() : null,
            ':latitude'    => $user->getLatitude() ? $user->getLatitude() : null,
            ':longitude'   => $user->getLongitude() ? $user->getLongitude() : null
        ];

        // Execute query
        if ($this->db->action($sql, $data)) {
            return $this->find($user);
        } else {
            throw $this->dbException();
        }
    }

    /**
     * Modify an account password
     *
     * @param User $user
     *
     * @return User
     * @throws \Exception
     */
    public function modifyPassword(User $user)
    {
        $user->clean();

        // Check the requested params
        if (!$user->getIdUser() or !$user->getPassword() or !$user->getOldPassword() or !$user->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Check if the password is correct
        $sql = 'SELECT PASSWORD FROM USERS WHERE ID_USER = :id';
        $res = $this->db->query($sql, [':id' => $user->getIdUser()]);
        if (!$res or $res->getNumRows() != 1 or !password_verify($user->getOldPassword(),
                $res->getRows()[0]['PASSWORD'])
        ) {
            throw new \Exception(Exceptions::INCORRECT_PASSWORD, 403);
        }

        // Geolocalize the user
        /** @var \Api\Entity\User $user */
        $user = $this->geolocalize($user);

        // Prepare query and data
        $sql = 'UPDATE USERS SET PASSWORD = :password, CTRL_IP_MOD = :ip, CTRL_DATE_MOD = SYSDATE(),
                LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                WHERE ID_USER = :id';

        $data = [
            ':id'          => $user->getIdUser(),
            ':password'    => password_hash($user->getPassword(), PASSWORD_DEFAULT),
            ':ip'          => $user->getIp(),
            ':id_geonames' => $user->getIdGeonames() ? $user->getIdGeonames() : null,
            ':latitude'    => $user->getLatitude() ? $user->getLatitude() : null,
            ':longitude'   => $user->getLongitude() ? $user->getLongitude() : null
        ];

        // Execute query
        if ($this->db->action($sql, $data)) {
            return $this->find($user);
        } else {
            throw $this->dbException();
        }
    }

    /**
     * Reset an account password
     *
     * @param User $user
     *
     * @return \Api\Entity\ResultSet
     * @throws \Exception
     */
    public function resetPassword(User $user)
    {
        $user->clean();

        // Check the requested params
        if (!$user->getEmail() or !$user->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Check if the user exists
        $sql = 'SELECT ID_USER, NAME, EMAIL FROM USERS WHERE EMAIL = :email';
        $res = $this->db->query($sql, [':email' => mb_strtolower($user->getEmail())]);
        if (!$res or $res->getNumRows() != 1) {
            throw new \Exception(Exceptions::INCORRECT_PASSWORD, 403);
        }
        $user->setIdUser($res->getRows()[0]['ID_USER'])->setName($res->getRows()[0]['NAME']);

        // Check if we already have a non expired or confirmed token with the same change
        $sql = 'SELECT TOKEN FROM USERS_VALIDATIONS
                WHERE FK_ID_USER = :id AND EMAIL = :email AND TYPE = :type AND CONFIRMED = 0 AND
                  CTRL_DATE < SYSDATE() + INTERVAL 1 DAY';
        $data = [
            ':id'    => $user->getIdUser(),
            ':email' => mb_strtolower($user->getEmail()),
            ':type'  => 'P'
        ];
        $res = $this->db->query($sql, $data);

        if ($res->getNumRows() == 0) {
            // Geolocalize the user
            /** @var \Api\Entity\User $user */
            $user = $this->geolocalize($user);

            // Generate validation code
            $token = md5($user->getIdUser() . $user->getEmail() . time());

            // Insert the validation token
            $sql = 'INSERT INTO USERS_VALIDATIONS(TOKEN, TYPE, FK_ID_USER, EMAIL, CTRL_DATE, CTRL_IP)
                    VALUES (:token, :type, :id, :email, SYSDATE(), :ip)';
            $data = [
                ':token' => $token,
                ':type'  => 'P',
                ':id'    => $user->getIdUser(),
                ':email' => mb_strtolower($user->getEmail()),
                ':ip'    => $user->getIp()
            ];

            if ($this->db->action($sql, $data)) {
                return $token;
            } else {
                throw $this->dbException();
            }
        } else {
            return $res->getRows()[0]['TOKEN'];
        }
    }

    /**
     * Modify an account type
     *
     * @param User $user
     *
     * @return User
     * @throws \Exception
     */
    public function modifyType(User $user)
    {
        $user->clean();

        if (!$user->getIdUser() or !in_array($user->getType(), [1, 2, 3]) or !$user->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Retrive the full user of the db
        $user = $this->find($user);
        if (!$user) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }
        $user->setIp($user->getIp());

        // Change the user type
        return $this->changeType($user, $user->getType());
    }

    /**
     * Modify an account email
     *
     * @param User $user
     *
     * @return string
     * @throws \Exception
     */
    public function modifyEmail(User $user)
    {
        $user->clean();

        // Check data
        if (!$user->getIdUser() or !$user->getPassword() or !$user->getEmail() or !$user->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Check if the password is correct
        $sql = 'SELECT PASSWORD, EMAIL FROM USERS WHERE ID_USER = :id';
        $oldUser = $this->db->query($sql, [':id' => $user->getIdUser()]);
        if (!$oldUser or $oldUser->getNumRows() != 1 or !password_verify($user->getPassword(),
                $oldUser->getRows()[0]['PASSWORD'])
        ) {
            throw new \Exception(Exceptions::INCORRECT_PASSWORD, 403);
        }

        // Check if the new email is already used
        $sql = 'SELECT EMAIL FROM USERS WHERE ID_USER != :id AND EMAIL = :email';
        $res = $this->db->query($sql, [':id' => $user->getIdUser(), ':email' => $user->getEmail()]);
        if ($res->getNumRows() > 0) {
            throw new \Exception(Exceptions::DUPLICATED_KEY, 409);
        }

        // Check if we already have a non expired or confirmed token with the same change
        $sql = 'SELECT TOKEN FROM USERS_VALIDATIONS
                WHERE FK_ID_USER = :id AND EMAIL = :email AND TYPE = :type AND CONFIRMED = 0 AND
                  CTRL_DATE < SYSDATE() + INTERVAL 1 DAY';
        $data = [
            ':id'    => $user->getIdUser(),
            ':email' => mb_strtolower($user->getEmail()),
            ':type'  => 'E'
        ];
        $res = $this->db->query($sql, $data);

        if ($res->getNumRows() == 0) {
            // Generate validation code
            $token = md5($user->getIdUser() . $user->getEmail() . time());

            // Insert the validation token
            $sql = 'INSERT INTO USERS_VALIDATIONS(TOKEN, TYPE, FK_ID_USER, EMAIL, CTRL_DATE, CTRL_IP, OLD_EMAIL)
                    VALUES (:token, :type, :id, :email, SYSDATE(), :ip, :old_email);';

            $data = [
                ':token'     => $token,
                ':type'      => 'E',
                ':id'        => $user->getIdUser(),
                ':email'     => mb_strtolower($user->getEmail()),
                ':ip'        => $user->getIp(),
                ':old_email' => $oldUser->getRows()[0]['EMAIL']
            ];

            if ($this->db->action($sql, $data)) {
                return $token;
            } else {
                throw $this->dbException();
            }
        } else {
            return $res->getRows()[0]['TOKEN'];
        }
    }

    /**
     * Get the user validation token
     *
     * @param User $user
     *
     * @return string
     * @throws \Exception
     */
    public function getValidationToken(User $user)
    {
        // Check data
        if (!$user->getIdUser() or !$user->getEmail()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Check if we already have a non expired or confirmed token with the same change
        $sql = "SELECT TOKEN FROM USERS_VALIDATIONS
                WHERE FK_ID_USER = :id AND EMAIL = :email AND TYPE = 'V' AND CONFIRMED = 0";
        $data = [
            ':id'    => $user->getIdUser(),
            ':email' => mb_strtolower($user->getEmail())
        ];
        $res = $this->db->query($sql, $data);

        if ($res->getNumRows() > 0) {
            return $res->getRows()[0]['TOKEN'];
        } else {
            throw $this->dbException();
        }
    }

    /**
     * Validate a received token
     *
     * @param string $token
     * @param string $ip
     * @param string $password [optional]
     *
     * @return User
     * @throws \Exception
     */
    public function validateToken($token, $ip, $password = null)
    {
        // Retrieve the token information if is still valid
        $sql = "SELECT V.TOKEN, V.TYPE, V.FK_ID_USER, V.EMAIL, V.OLD_EMAIL, U.CONFIRMED, U.NAME
                FROM USERS_VALIDATIONS V, USERS U
                WHERE U.ID_USER = V.FK_ID_USER AND V.TOKEN = :token AND V.CONFIRMED = 0 AND
                  (V.TYPE = 'V' OR V.CTRL_DATE < SYSDATE() + INTERVAL 1 DAY)";
        $res = $this->db->query($sql, [':token' => trim($token)]);
        if (!$res or $res->getNumRows() == 0) {
            throw new \Exception(Exceptions::EXPIRED_TOKEN, 403);
        }

        $res = $res->getRows()[0];

        // Geolocalize the user
        /** @var User $user */
        $user = $this->geolocalize(new User([
            'id_user' => $res['FK_ID_USER'],
            'email'   => $res['EMAIL'],
            'ip'      => $ip
        ]));

        // Process the token
        $sql = 'UPDATE USERS_VALIDATIONS SET CONFIRMED = 1 WHERE TOKEN = :token';
        $this->db->action($sql, ['token' => $token]);

        if ($res['TYPE'] == 'V') {
            // Initial account validation
            $sql = "UPDATE USERS SET CONFIRMED = 1, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip,
                    LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                    WHERE ID_USER = :id;
                    UPDATE USERS_IDENTITIES SET CONFIRMED = 1, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip
                    WHERE FK_ID_USER = :id AND TYPE = 'E' AND VALUE = :email;";
            $params = [
                ':id'          => $user->getIdUser(),
                ':email'       => $user->getEmail(),
                ':ip'          => $user->getIp(),
                ':latitude'    => $user->getLatitude(),
                ':longitude'   => $user->getLongitude(),
                ':id_geonames' => $user->getIdGeonames()
            ];
        } elseif ($res['TYPE'] == 'E') {
            // If the user is not confirmed, we will change the identity email, otherwise we create a new identity and deprecate the old one
            $params = [
                ':id'        => $user->getIdUser(),
                ':old_email' => $res['OLD_EMAIL'],
                ':email'     => $user->getEmail(),
                ':ip'        => $user->getIp()
            ];

            if ($res['CONFIRMED'] == 1) {
                // Get the main status of replaced field
                $sql = "SELECT COUNT(*) MAIN FROM USERS_IDENTITIES WHERE FK_ID_USER = :id AND TYPE = 'E' AND VALUE = :old_email AND STATUS = 'A' AND MAIN = 1";
                $params[':main'] = $this->db->query($sql,
                    [':id' => $user->getIdUser(), ':old_email' => $res['OLD_EMAIL']])->getRows()[0]['MAIN'];

                $sql = "UPDATE USERS_IDENTITIES SET STATUS = 'D', MAIN = 0, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip
                        WHERE FK_ID_USER = :id AND TYPE = 'E' AND VALUE = :old_email;
                        INSERT INTO USERS_IDENTITIES(FK_ID_USER, MAIN, TYPE, NAME, VALUE, CONFIRMED, CTRL_IP, CTRL_DATE)
                        VALUES (:id, :main, 'E', :name, :email, 1, :ip, SYSDATE());";
            } else {
                $sql = "UPDATE USERS_IDENTITIES SET VALUE = :email, CONFIRMED = 1, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip
                        WHERE FK_ID_USER = :id AND TYPE = 'E' AND VALUE = :old_email";
            }
            $this->db->action($sql, $params);

            // Confirm the email change
            $sql = 'UPDATE USERS SET CONFIRMED = 1, EMAIL = :email, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip,
                    LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                    WHERE ID_USER = :id';
            $params = [
                ':id'          => $user->getIdUser(),
                ':email'       => $user->getEmail(),
                ':ip'          => $user->getIp(),
                ':latitude'    => $user->getLatitude(),
                ':longitude'   => $user->getLongitude(),
                ':id_geonames' => $user->getIdGeonames()
            ];
        } elseif ($res['TYPE'] == 'P' and $password) {
            // Password recovery
            $sql = 'UPDATE USERS SET CONFIRMED = 1, PASSWORD = :password, CTRL_DATE_MOD = SYSDATE(), CTRL_IP_MOD = :ip,
                    LAST_ID_GEONAMES = :id_geonames, LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                    WHERE ID_USER = :id';
            $params = [
                ':id'          => $user->getIdUser(),
                ':password'    => password_hash($password, PASSWORD_DEFAULT),
                ':ip'          => $user->getIp(),
                ':latitude'    => $user->getLatitude(),
                ':longitude'   => $user->getLongitude(),
                ':id_geonames' => $user->getIdGeonames()
            ];
        } else {
            throw new \Exception(Exceptions::EXPIRED_TOKEN, 403);
        }

        // Execute query
        if ($this->db->action($sql, $params)) {
            return $user;
        } else {
            throw $this->dbException();
        }
    }

    /**
     * Login the user
     *
     * @param User   $user
     * @param string $source
     *
     * @return User
     * @throws \Exception
     */
    public function login(User $user, $source)
    {
        if (!$user->getEmail() or !$user->getPassword() or !$user->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        } elseif (!in_array($source, ['front', 'api'])) {
            throw new \Exception(Exceptions::UNAUTHORIZED, 401);
        }

        // Try to get the user
        $sql = 'SELECT ID_USER, PASSWORD, EMAIL, TYPE, NAME, CONFIRMED, LANG, STORAGE_LEFT, STAMPS_LEFT,
                CASE WHEN TYPE > 1 AND LAST_RESET <= SYSDATE() - INTERVAL 1 MONTH THEN 1 ELSE 0 END RENEW
                FROM USERS WHERE EMAIL = :email';
        $user->clean();
        $params = [':email' => mb_strtolower($user->getEmail())];
        $data = $this->db->query($sql, $params, 'Api\Entity\User');

        // If we don't have the user, maybe the pass is not correct
        /** @var \Api\Entity\User $userAux */
        if ($data->getNumRows() == 0 or !($userAux = $data->getRows()[0]) or !password_verify($user->getPassword(),
                $userAux->getPassword())
        ) {
            throw new \Exception(Exceptions::INCORRECT_PASSWORD, 403);
        } elseif (!$data and $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        $userAux->setIp($user->getIp())
                ->setLatitude($user->getLatitude())
                ->setLongitude($user->getLongitude())
                ->setIdGeonames($user->getIdGeonames());
        unset($user);

        // Renew the account if necessary
        if ($userAux->getRenew()) {
            $userAux = $this->renewAccount($userAux);
        }
        $userAux->setPassword(null)->setRenew(null);

        // Geolocalize the user
        $userAux = $this->geolocalize($userAux);

        // Login is successful, record tracking data
        $this->db->beginTransaction();
        $sql = 'UPDATE USERS SET CTRL_IP_LOGIN = :ip, CTRL_DATE_LOGIN = SYSDATE(), LAST_ID_GEONAMES = :id_geonames,
                                   LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                WHERE ID_USER = :id';
        $params = [
            ':id'          => $userAux->getIdUser(),
            ':ip'          => $userAux->getIp(),
            ':latitude'    => $userAux->getLatitude(),
            ':longitude'   => $userAux->getLongitude(),
            ':id_geonames' => $userAux->getIdGeonames()
        ];

        $res = $this->db->action($sql, $params);

        $sql = 'INSERT INTO USERS_LOGINS(FK_ID_USER, EMAIL, CTRL_DATE, SOURCE, CTRL_IP, ID_GEONAMES, LATITUDE, LONGITUDE)
                VALUES (:id, :email, SYSDATE(), :source, :ip, :id_geonames, :latitude, :longitude)';
        $params = [
            ':id'          => $userAux->getIdUser(),
            ':email'       => $userAux->getEmail(),
            ':source'      => strtoupper($source),
            ':ip'          => $userAux->getIp(),
            ':latitude'    => $userAux->getLatitude(),
            ':longitude'   => $userAux->getLongitude(),
            ':id_geonames' => $userAux->getIdGeonames()
        ];

        if ($res) {
            $res = $this->db->action($sql, $params);
        }

        // If everything has gone ok, we commit the transaction else we do rollback
        if ($res) {
            $this->db->commit();
        } else {
            $this->db->rollBack();
            throw new \Exception('Login failed', 500);
        }

        return $userAux;
    }

    /**
     * Delete an account
     *
     * @param User $user
     *
     * @return bool
     * @throws \Exception
     */
    public function delete(User $user)
    {
        if (!$user->getIdUser() or !$user->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the user
        /** @var User $user */
        $user = $this->geolocalize($user);

        $this->db->beginTransaction();
        $sql = 'UPDATE USERS SET CTRL_IP_MOD = :ip, CTRL_DATE_MOD = SYSDATE(), LAST_ID_GEONAMES = :id_geonames,
                                   LAST_LATITUDE = :latitude, LAST_LONGITUDE = :longitude
                WHERE ID_USER = :id';

        $params = [
            ':id'          => $user->getIdUser(),
            ':ip'          => $user->getIp(),
            ':latitude'    => $user->getLatitude(),
            ':longitude'   => $user->getLongitude(),
            ':id_geonames' => $user->getIdGeonames()
        ];
        $res = $this->db->action($sql, $params);

        $sql = 'DELETE FROM USERS WHERE ID_USER = :id';

        // Execute query
        if ($res) {
            $res = $this->db->action($sql, [':id' => $user->getIdUser()]);
        }

        // If everything has gone ok, we commit the transaction else we do rollback
        if ($res) {
            $this->db->commit();
        } else {
            $this->db->rollBack();
            throw new \Exception('', 500);
        }
    }

    /**
     * Get active identities of the user
     *
     * @param User $user
     * @param bool $main [optional] Only main identity
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function getIdentities(User $user, $main = false)
    {
        // Check necessary fields
        if (!$user->getIdUser()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }
        // Create query
        $sql = "SELECT ID_IDENTITY, FK_ID_USER, MAIN, TYPE, NAME, VALUE, CONFIRMED, STATUS
                FROM USERS_IDENTITIES WHERE FK_ID_USER = :id AND STATUS = 'A'";
        if ($main) {
            $sql .= ' AND MAIN = 1';
        }
        $sql .= " ORDER BY MAIN DESC, ID_IDENTITY ASC";

        $params = [':id' => $user->getIdUser()];

        $data = $this->db->query($sql, $params, 'Api\Entity\UserIdentity');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
    }

    public function saveIdentity(UserIdentity $identity)
    {
        // Check necessary fields
        if (!$identity->getIdIdentity() or !$identity->getName() or !$identity->getValue() or !$identity->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Check if the user is confirmed
        $sql = 'SELECT U.ID_USER, U.CONFIRMED FROM USERS U, USERS_IDENTITIES I
                WHERE U.ID_USER = I.FK_ID_USER AND I.ID_IDENTITY = :id';
        $user = $this->db->query($sql, [':id' => $identity->getIdIdentity()]);


    }
}
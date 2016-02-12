<?php

namespace Api\Model\General;

use Api\Entity\ResultSet;

class MySQL implements DatabaseInterface
{
    private static $_me;

    /**
     * @var \PDO
     */
    private $_conn;

    private $_error;

    /**
     * Singleton constructor
     */
    private function __construct() { }

    /**
     * Singleton getInstance method
     * @return MySQL
     */
    public static function getInstance()
    {
        if (self::$_me === null) {
            self::$_me = new MySQL();
        }

        return self::$_me;
    }

    // Objects of this class cannot be deserialize because singleton pattern
    public function __clone() { }

    public function __wakeup() { }

    /**
     * Check if the connector is connected
     * @return bool
     */
    public function isConnected()
    {
        return $this->_conn !== null;
    }

    /**
     * Connect to the database
     *
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $scheme
     */
    public function connect($host, $user, $pass, $scheme)
    {
        $this->_conn = new \PDO('mysql:host=' . $host . ';dbname=' . $scheme, $user, $pass,
            array(\PDO::ATTR_PERSISTENT => true));
    }

    /**
     * Start a transaction
     * @return $this
     */
    public function beginTransaction()
    {
        if (!$this->isConnected()) {
            return null;
        }
        $this->_conn->beginTransaction();

        return $this;
    }

    /**
     * Do commit
     * @return $this
     */
    public function commit()
    {
        if (!$this->isConnected()) {
            return null;
        }
        $this->_conn->commit();

        return $this;
    }

    /**
     * Do rollback
     * @return $this
     */
    public function rollBack()
    {
        if (!$this->isConnected()) {
            return null;
        }
        $this->_conn->rollBack();

        return $this;
    }

    /**
     * Return last insert id
     * @return $this
     */
    public function lastInsertId()
    {
        return $this->_conn->lastInsertId();
    }

    /**
     * Get error message
     * @return array
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * Execute an action query (update, delete)
     *
     * @param string $query
     * @param array  $params [optional] Params to bind variables
     *
     * @return int Number of affected rows
     */
    public function action($query, $params = null)
    {
        // Prepare and execute query
        $stmt = $this->_conn->prepare($query);

        // If we have params, we bind them
        if ($params and is_array($params)) {
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
        }

        $res = $stmt->execute();

        if (!$res) {
            $this->_error = [$stmt->errorCode(), $stmt->errorInfo()];
        }

        return $stmt->rowCount();
    }

    /**
     * Execute a fetch query
     *
     * @param string $query
     * @param array  $params  [optional] Params to bind variables
     * @param string $class   [optional] Class name to populate result rows
     * @param int    $page    [optional] Number of requested page
     * @param int    $numRows [optional] Number of rows per page
     *
     * @return ResultSet|bool
     */
    public function query($query, $params = null, $class = null, $page = 0, $numRows = 0)
    {
        // If the query is paginated, we need to add sql code
        $totalRows = 0;
        $totalPages = 1;
        if ($page > 0 and $numRows > 0) {
            if ($params === null) {
                $params = [];
            }
            $params[':p_rows'] = $numRows;

            // Count total of rows and pages
            $queryCount = 'SELECT COUNT(*), CEIL(COUNT(*) / :p_rows) FROM (' . $query . ') Q';
            $stmt = $this->_conn->prepare($queryCount);
            $res = $stmt->execute($params);

            if (!$res) {
                $this->_error = [$stmt->errorCode(), $stmt->errorInfo()];

                return false;
            }

            $rows = $stmt->fetch(\PDO::FETCH_NUM);
            $totalRows = $rows[0];
            $totalPages = $rows[1];
            $params[':p_inf'] = ($page - 1) * $numRows;

            // Build the final paginated query
            $query = $query . ' LIMIT :p_inf,:p_rows';
        }

        // Prepare and execute query
        $stmt = $this->_conn->prepare($query);

        // If we have params, we bind them
        if ($params and is_array($params)) {
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
        }

        $res = $stmt->execute();

        if ($res) {
            // Fetch query rows
            $rows = ($class and is_subclass_of($class, 'Bindeo\DataModel\DataModelAbstract')) ? $stmt->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class)
                : $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = new ResultSet($totalRows ? $totalRows : $stmt->rowCount(), $totalPages, $rows);
        } else {
            $this->_error = [$stmt->errorCode(), $stmt->errorInfo()];
            $result = false;
        }

        return $result;
    }
}
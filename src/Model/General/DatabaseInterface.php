<?php

namespace Api\Model\General;

use Api\Entity\ResultSet;

interface DatabaseInterface
{
    /**
     * Start a transaction
     * @return $this
     */
    public function beginTransaction();

    /**
     * Do commit
     * @return $this
     */
    public function commit();

    /**
     * Do rollback
     * @return $this
     */
    public function rollBack();

    /**
     * Return last insert id
     * @return $this
     */
    public function lastInsertId();

    /**
     * Get error message
     * @return array
     */
    public function getError();

    /**
     * Execute an action query (update, delete)
     *
     * @param string $query
     * @param array  $params [optional] Params to bind variables
     *
     * @return int Number of affected rows
     */
    public function action($query, $params = null);

    /**
     * Execute a fetch query
     *
     * @param string $query
     * @param array  $params  [optional] Params to bind variables
     * @param string $class   [optional] Class name to populate result rows
     * @param int    $page    [optional] Number of requested page
     * @param int    $numRows [optional] Number of rows per page
     *
     * @return ResultSet
     */
    public function query($query, $params = null, $class = null, $page = 0, $numRows = 0);
}
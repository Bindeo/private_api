<?php

namespace Api\Repository;

use Api\Entity\Process;
use Api\Entity\ResultSet;
use Bindeo\DataModel\ClientsInterface;
use Bindeo\DataModel\ProcessInterface;
use Bindeo\DataModel\Exceptions;
use Bindeo\Filter\ProcessesFilter;
use \MaxMind\Db\Reader;

class Processes extends RepositoryAbstract
{
    /**
     * Get a list of processes status translated into given language
     *
     * @param string $lang
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function getStatusList($lang)
    {
        if (!in_array($lang, ['es_ES', 'en_US'])) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = "SELECT P.TYPE, P.ID_STATUS, T.VALUE NAME
                FROM PROCESSES_STATUS P, TRANSLATIONS T WHERE T.ID_TRANSLATION = P.FK_ID_TRANSLATION AND T.LANG = :lang
                ORDER BY P.TYPE ASC, P.ID_STATUS ASC";
        $params = [':lang' => $lang];

        $data = $this->db->query($sql, $params, 'Api\Entity\ProcessStatus');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
    }

    /**
     * Get a filter list of processes
     *
     * @param ProcessesFilter $filter
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function processesList(ProcessesFilter $filter)
    {
        if (!in_array($filter->getLang(), ['es_ES', 'en_US']) or !$filter->getClientType() or
            !is_numeric($filter->getIdClient()) or !is_numeric($filter->getPage())
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $filter->clean();

        // Build the query
        $params = [
            ':lang'        => $filter->getLang(),
            ':client_type' => $filter->getClientType(),
            ':id_client'   => $filter->getIdClient()
        ];
        $where = '';

        // Status
        if ($filter->getType() and $filter->getIdStatus()) {
            $params[':type'] = $filter->getType();
            $params[':id_status'] = $filter->getIdStatus();
            $where .= ' AND P.TYPE = :type AND P.FK_ID_STATUS = :id_status';
        }

        // Name
        if ($filter->getName()) {
            $params[':name'] = $filter->getName();
            $where .= ' AND MATCH(P.SEARCH) AGAINST(:name  IN NATURAL LANGUAGE MODE)';
        }

        // Orders
        switch ($filter->getOrder()) {
            case ProcessesFilter::ORDER_DATE_DESC:
                $order = 'P.CTRL_DATE DESC';
                break;
            case ProcessesFilter::ORDER_DATE_ASC:
                $order = 'P.CTRL_DATE ASC';
                break;
            case ProcessesFilter::ORDER_NAME_ASC:
                $order = 'P.NAME ASC';
                break;
            case ProcessesFilter::ORDER_NAME_DESC:
                $order = 'P.NAME DESC';
                break;
        }

        // Get the paginated list
        $sql = "SELECT P.TYPE, P.ID_ELEMENT, P.CLIENT_TYPE, P.FK_ID_CLIENT, P.FK_ID_STATUS, P.NAME, P.CTRL_DATE, P.ADDITIONAL_DATA,
                  T.VALUE STATUS, IFNULL(B.EXTERNAL_ID, BL.HASH) ID_ALT_ELEMENT
                FROM PROCESSES P
                LEFT JOIN BULK_TRANSACTIONS B ON P.TYPE = 'S' AND B.ID_BULK_TRANSACTION = P.ID_ELEMENT
                LEFT JOIN BLOCKCHAIN BL ON P.TYPE = 'N' AND BL.TYPE = 'F' AND BL.FK_ID_ELEMENT = P.ID_ELEMENT,
                  PROCESSES_CLIENTS C, PROCESSES_STATUS S, TRANSLATIONS T
                WHERE C.CLIENT_TYPE = :client_type AND C.FK_ID_CLIENT = :id_client AND P.TYPE = C.TYPE AND P.ID_ELEMENT = C.ID_ELEMENT AND
                  S.TYPE = P.TYPE AND S.ID_STATUS = P.FK_ID_STATUS AND T.ID_TRANSLATION = S.FK_ID_TRANSLATION AND T.LANG = :lang" .
               $where . ' ORDER BY ' . $order;

        return $this->db->query($sql, $params, 'Api\Entity\Process', $filter->getPage(), $filter->getNumRows());
    }

    /**
     * Get a process
     *
     * @param Process $process
     *
     * @return Process
     * @throws \Exception
     */
    public function getProcess(Process $process)
    {
        // Check data
        if (!$process->getType() or !$process->getIdElement()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get process
        $sql = 'SELECT TYPE, ID_ELEMENT, CLIENT_TYPE, FK_ID_CLIENT, FK_ID_STATUS, NAME, CTRL_DATE, ADDITIONAL_DATA
                FROM PROCESSES WHERE TYPE = :type AND ID_ELEMENT = :id_element';
        $params = [':type' => $process->getType(), ':id_element' => $process->getIdElement()];

        $data = $this->db->query($sql, $params, 'Api\Entity\Process');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data->getNumRows() ? $data->getRows()[0] : null;
    }

    /**
     * Create new main process
     *
     * @param ProcessInterface $element
     *
     * @return Process
     * @throws \Exception
     */
    public function createProcess(ProcessInterface $element)
    {
        // Check data
        if (!$element->getElementId() or !$element->getElementName() or !$element->getClientType() or
            !$element->getIdClient()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'INSERT INTO PROCESSES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ID_ELEMENT, FK_ID_STATUS, NAME, SEARCH, CTRL_DATE)
                VALUES (:client_type, :id_client, :type, :id_element, 1, :name, REGEX_REPLACE(\'[^A-Za-z0-9]\', \' \', :name), SYSDATE());
                INSERT INTO PROCESSES_CLIENTS(TYPE, ID_ELEMENT, CLIENT_TYPE, FK_ID_CLIENT)
                VALUES (:type, :id_element, :client_type, :id_client);';
        $params = [
            ':client_type' => $element->getClientType(),
            ':id_client'   => $element->getIdClient(),
            ':type'        => $element->getProcessType(),
            ':id_element'  => $element->getElementId(),
            ':name'        => $element->getElementName()
        ];

        // Execute query
        if (!$this->db->action($sql, $params)) {
            throw $this->dbException();
        }

        return (new Process())->setType($element->getProcessType())
                              ->setIdElement($element->getElementId())
                              ->setIdStatus(1);
    }

    /**
     * Add clients to an existent process
     *
     * @param Process $process
     * @param array   $clients
     *
     * @throws \Exception
     */
    public function addProcessClients(Process $process, array $clients)
    {
        // Check data
        if (!$process->getIdElement() or !$process->getType()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Insert clients
        $this->db->beginTransaction();

        $sql = 'INSERT INTO PROCESSES_CLIENTS(TYPE, ID_ELEMENT, CLIENT_TYPE, FK_ID_CLIENT)
                VALUES (:type, :id_element, :client_type, :id_client)';

        // Loop clients
        foreach ($clients as $client) {
            if ($client instanceof ClientsInterface) {
                if ($client->getIdClient()) {
                    // Insert clients
                    $params = [
                        ':type'        => $process->getType(),
                        ':id_element'  => $process->getIdElement(),
                        ':client_type' => $client->getClientType(),
                        ':id_client'   => $client->getIdClient()
                    ];

                    if (!$this->db->action($sql, $params)) {
                        // If client is repeated because is also the creator, avoid exception
                        if ($this->db->getError()[0] != "23000") {
                            $this->db->rollBack();
                            throw $this->dbException();
                        }
                    }
                }
            } else {
                $this->db->rollBack();
                throw $this->dbException();
            }
        }

        $this->db->commit();
    }

    /**
     * Update process status or data
     *
     * @param Process $process
     *
     * @throws \Exception
     */
    public function updateProcess(Process $process)
    {
        $process->clean();

        // Check data
        if (!$process->getType() or !$process->getIdElement() or !$process->getIdStatus()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Update process additional information
        $sql = 'UPDATE PROCESSES SET FK_ID_STATUS = :id_status, ADDITIONAL_DATA = IFNULL(:additional_data, ADDITIONAL_DATA)
                WHERE TYPE = :type AND ID_ELEMENT = :id_element';
        $params = [
            ':type'            => $process->getType(),
            ':id_element'      => $process->getIdElement(),
            ':id_status'       => $process->getIdStatus(),
            ':additional_data' => $process->getAdditionalData() ? $process->getAdditionalData() : null
        ];

        $this->db->action($sql, $params);
        if ($this->db->getError()) {
            throw $this->dbException();
        }
    }
}
<?php

namespace Api\Repository;

use Api\Entity\Process;
use Bindeo\DataModel\ClientsInterface;
use Bindeo\DataModel\ProcessInterface;
use Bindeo\DataModel\Exceptions;
use \MaxMind\Db\Reader;

class Processes extends RepositoryAbstract
{
    /**
     * Get a list of processes status translated into given language
     *
     * @param string $lang
     *
     * @return \Api\Entity\ResultSet
     * @throws \Exception
     */
    public function getStatusList($lang)
    {
        if (!in_array($lang, ['es_ES', 'en_US'])) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = "SELECT P.TYPE, P.ID_STATUS, T.VALUE NAME
                FROM PROCESSES_STATUS P, TRANSLATIONS T WHERE T.ID_TRANSLATION = P.FK_ID_TRANSLATION AND T.LANG = :lang";
        $params = [':lang' => $lang];

        $data = $this->db->query($sql, $params, 'Api\Entity\ProcessStatus');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
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

        $sql = 'INSERT INTO PROCESSES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ID_ELEMENT, FK_ID_STATUS, NAME, CTRL_DATE)
                VALUES (:client_type, :id_client, :type, :id_element, 1, :name, SYSDATE());
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
                // Insert clients
                $params = [
                    ':type'        => $process->getType(),
                    ':id_element'  => $process->getIdElement(),
                    ':client_type' => $client->getClientType(),
                    ':id_client'   => $client->getIdClient()
                ];

                if (!$this->db->action($sql, $params)) {
                    $this->db->rollBack();
                    throw $this->dbException();
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

        if (!$this->db->action($sql, $params)) {
            throw $this->dbException();
        }
    }
}
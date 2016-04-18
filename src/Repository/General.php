<?php

namespace Api\Repository;

use Api\Entity\ResultSet;
use Bindeo\DataModel\Exceptions;

class General extends RepositoryAbstract
{
    const MEDIA_TYPE_OTHERS = 8;

    /**
     * Get the account types list by language
     *
     * @param string $lang
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function accountTypes($lang)
    {
        if (!in_array($lang, ['es_ES', 'en_US'])) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = "SELECT A.ID_TYPE, T.VALUE NAME, A.COST, A.MAX_FILESIZE, A.MAX_STORAGE, A.MAX_STAMPS_MONTH
                FROM ACCOUNT_TYPES A, TRANSLATIONS T WHERE T.ID_TRANSLATION = A.FK_ID_TRANSLATION AND T.LANG = :lang";
        $params = [':lang' => $lang];

        $data = $this->db->query($sql, $params, 'Api\Entity\AccountType');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
    }

    /**
     * Get the media types list by language
     *
     * @param string $lang
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function mediaTypes($lang)
    {
        if (!in_array($lang, ['es_ES', 'en_US'])) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = 'SELECT A.ID_TYPE, T.VALUE NAME
                FROM MEDIA_TYPES A, TRANSLATIONS T WHERE T.ID_TRANSLATION = A.FK_ID_TRANSLATION AND T.LANG = :lang';
        $params = [':lang' => $lang];

        $data = $this->db->query($sql, $params, 'Api\Entity\MediaType');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
    }
}
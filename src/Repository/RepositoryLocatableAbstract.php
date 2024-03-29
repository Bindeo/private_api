<?php

namespace Api\Repository;

use Api\Model\General\DatabaseInterface;
use Bindeo\DataModel\LocatableInterface;
use \MaxMind\Db\Reader;

abstract class RepositoryLocatableAbstract extends RepositoryAbstract
{
    /**
     * @var Reader
     */
    protected $maxmind;

    public function __construct(DatabaseInterface $db, Reader $maxmind)
    {
        $this->maxmind = $maxmind;
        parent::__construct($db);
    }

    /**
     * Geolocalize a data model
     *
     * @param \Bindeo\DataModel\LocatableInterface $model
     */
    protected function geolocalize(LocatableInterface $model)
    {
        // Geolocalize the ip
        $geoip = $this->maxmind->get($model->getIp());

        if (!$model->getLatitude()) {
            $model->setLatitude($geoip['location']['latitude']);
        }
        if (!$model->getLongitude()) {
            $model->setLongitude($geoip['location']['longitude']);
        }
        if (!$model->getIdGeonames()) {
            $model->setIdGeonames(isset($geoip['location']['geoname_id']) ? $geoip['location']['geoname_id']
                : $geoip['country']['geoname_id']);
        }
    }
}
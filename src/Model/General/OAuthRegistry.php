<?php

namespace Api\Model\General;

/**
 * Store general data
 */
class OAuthRegistry
{
    protected static $me;
    protected        $grantType;
    protected        $appId;
    protected        $appName;
    protected        $appRole;

    /**
     * Singleton constructor
     */
    protected function __construct() { }

    /**
     * Singleton getInstance method
     * @return OAuthRegistry
     */
    public static function getInstance()
    {
        if (self::$me === null) {
            self::$me = new OAuthRegistry();
        }

        return self::$me;
    }

    // Impedimos que los objetos de esta clase puedan ser clonados o deserializados, para asegurar el singleton
    public function __clone() { }

    public function __wakeup() { }

    /**
     * @return mixed
     */
    public function getGrantType()
    {
        return $this->grantType;
    }

    /**
     * @param mixed $grantType
     *
     * @return OAuthRegistry
     */
    public function setGrantType($grantType)
    {
        $this->grantType = $grantType;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAppId()
    {
        return $this->appId;
    }

    /**
     * @param mixed $appId
     *
     * @return OAuthRegistry
     */
    public function setAppId($appId)
    {
        $this->appId = $appId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAppName()
    {
        return $this->appName;
    }

    /**
     * @param mixed $appName
     *
     * @return OAuthRegistry
     */
    public function setAppName($appName)
    {
        $this->appName = $appName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAppRole()
    {
        return $this->appRole;
    }

    /**
     * @param mixed $appRole
     *
     * @return OAuthRegistry
     */
    public function setAppRole($appRole)
    {
        $this->appRole = $appRole;

        return $this;
    }
}
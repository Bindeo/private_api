<?php

namespace Api\Model\General;

/**
 * Store general data
 */
class OAuthRegistry
{
    protected static $_me;
    protected        $_grantType;
    protected        $_clientId;
    protected        $_appName;
    protected        $_appRole;

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
        if (self::$_me === null) {
            self::$_me = new OAuthRegistry();
        }

        return self::$_me;
    }

    // Impedimos que los objetos de esta clase puedan ser clonados o deserializados, para asegurar el singleton
    public function __clone() { }

    public function __wakeup() { }

    /**
     * @return mixed
     */
    public function getGrantType()
    {
        return $this->_grantType;
    }

    /**
     * @param mixed $grantType
     *
     * @return OAuthRegistry
     */
    public function setGrantType($grantType)
    {
        $this->_grantType = $grantType;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getClientId()
    {
        return $this->_clientId;
    }

    /**
     * @param mixed $clientId
     *
     * @return OAuthRegistry
     */
    public function setClientId($clientId)
    {
        $this->_clientId = $clientId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAppName()
    {
        return $this->_appName;
    }

    /**
     * @param mixed $appName
     *
     * @return OAuthRegistry
     */
    public function setAppName($appName)
    {
        $this->_appName = $appName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAppRole()
    {
        return $this->_appRole;
    }

    /**
     * @param mixed $appRole
     *
     * @return OAuthRegistry
     */
    public function setAppRole($appRole)
    {
        $this->_appRole = $appRole;

        return $this;
    }
}
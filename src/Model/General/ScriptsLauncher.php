<?php

namespace Api\Model\General;

class ScriptsLauncher
{
    private static $me;
    private        $scripts;

    /**
     * Singleton constructor
     */
    private function __construct() { }

    // We prevent that objects of this class can be cloned or deserialized to ensure the singleton pattern
    public function __clone() { }

    public function __wakeup() { }

    /**
     * Singleton getInstance method
     * @return ScriptsLauncher
     */
    public static function getInstance()
    {
        if (self::$me === null) {
            self::$me = new ScriptsLauncher();
        }

        return self::$me;
    }

    public function setScripts($scripts)
    {
        $this->scripts = $scripts;
    }

    /**
     * Launch shell commands in background
     *
     * @param string $cmd
     */
    public function execBackground($cmd)
    {
        if ($this->scripts['status'] == 'enabled') {
            exec('export HOME=/tmp && ' . $this->scripts['path'] . '/' . $cmd . " > /dev/null &");
        }
    }
}
<?php

namespace KonturElbaApi;

use KonturElbaApi\classes\Employee;

/**
 * Class Core
 * @package src
 *
 * @property Client $_client
 * @property string $_login
 * @property string $_password
 */
class Core
{

    const BASE_URI = 'https://elba.kontur.ru';

    private $_client;

    private $_login;

    private $_password;

    public function __construct(array $config = [])
    {
        if (!isset($config['login'])) {
            throw new \Exception('Login is not set');
        }

        if (!isset($config['password'])) {
            throw new \Exception('Password is not set');
        }

        $this->_login = $config['login'];
        $this->_password = $config['password'];
    }

    protected function getClient()
    {
        if ($this->_client === null) {
            $this->_client = new \KonturElbaApi\Client($this->_login, $this->_password);
        }

        return $this->_client;
    }


    public function getEmployee($name)
    {
        $employeesList = $this->getClient()->getEmployeesList();
        return $employeesList->getEmployee($name, $this->getClient());
    }

}
<?php

namespace KonturElbaApi;

use GuzzleHttp\Cookie\CookieJar;
use KonturElbaApi\classes\Employee;
use KonturElbaApi\classes\EmployeesList;

class Client
{

    const BASE_URI = 'https://elba.kontur.ru';

    /* @var \GuzzleHttp\Client $_instance */
    protected $_instance;

    protected $_sessionId;

    protected $_organizationId;

    protected $_login;

    protected $_password;

    public function __construct($login, $password)
    {
        $this->_login = $login;
        $this->_password = $password;
    }

    protected function getInstance()
    {
        if ($this->_instance === null) {
            $jar = new CookieJar();

            $this->_instance = new \GuzzleHttp\Client([
                'base_uri' => self::BASE_URI,
                'headers' => [
                    'Accept' => 'text/plain, */*; q=0.01',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
                    'Origin' => self::BASE_URI,
                    'Host' => 'elba.kontur.ru',
                ],
                'cookies' => $jar,
                'verify' => false,
                'proxy' => 'http://localhost:8888',
            ]);
        }

        return $this->_instance;
    }

    protected function login()
    {
        $response = $this->getInstance()->request('GET', 'AccessControl/Login');

        sleep(1);

        $response = $this->getInstance()->request('POST', 'AccessControl/Login/GoInside', [
            'body' => json_encode([
                'Login' => $this->_login,
                'Password' => $this->_password,
                'RememberMe' => true,
            ]),
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' => 'application/json',
                'Referer' => 'https://elba.kontur.ru/AccessControl/Login'
            ]
        ]);

        preg_match('/sessionId%3D([\d]+)/', $response->getHeader('redirectUri')[0], $out);
        return $out[1];
    }

    protected function getSessionId()
    {
        if ($this->_sessionId === null) {
            $this->_sessionId = $this->login();
        }

        return $this->_sessionId;
    }

    protected function getOrganizationId()
    {
        $sessionId = $this->getSessionId();
        if ($this->_organizationId === null) {
            $response = $this->getInstance()->request('GET', "Staff/Evrika/StaffEvrikaHost.aspx?sessionId={$sessionId}");
            preg_match('/\"OrganizationId\":\"([\w\d-]+)\"/', $response->getBody()->__toString(), $out);
            $this->_organizationId = $out[1];
        }

        return $this->_organizationId;
    }

    public function getEmployeesList()
    {
        $organizationId = $this->getOrganizationId();
        $response = new Response($this->getInstance()->request('GET', "https://e-e.kontur.ru/Workers/Selection?organizationId={$organizationId}"));
        return new EmployeesList($response, true);
    }

    public function getEmployee($employeeId, $organizationId)
    {
        $response = new Response($this->getInstance()->request('GET', "https://e-e.kontur.ru/Worker/Wage?workerId={$employeeId}&organizationId={$organizationId}"));
        return new Employee($response);
    }



}
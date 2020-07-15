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
            ]);
        }

        return $this->_instance;
    }

    public function login()
    {
        $response = $this->getInstance()->request('GET', 'AccessControl/Login');

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

        preg_match('/scope%3D(\w+)/', $response->getHeader('redirectUri')[0], $out);
        $scope = $out[1];        
        return  $scope;
    }

    public function getSessionId()
    {
        $this->login();
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
    
    public function getOutgoingDocumentList($ContractorId = null, $Type = 'undefined', $OnlyAttentionRequired = false, $Period = null, $skip = 0, $limit = 100)
    {
        try {
            $this->getSessionId();

            $body = [
                "Period" => $Period, 
                "ContractorId" => $ContractorId, 
                "Type" => $Type,
                "OnlyAttentionRequired" => $OnlyAttentionRequired
            ];

            $response = $this->getInstance()->request('POST', "Business/Documents/Outgoing/List/OutgoingDocumentList/GetItems?scope={$this->_sessionId}&skip={$skip}&take={$limit}&metaonly=false&sort=SumForSorting.IsFilled%2Cdesc%3BSumForSorting.SumForSorting%2Cdesc%3BDate%2Cdesc%3BCreated%2Cdesc&ignoresavedfilter=false", [
                'body' => json_encode($body),
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $json = $response->getBody()->__toString();

            return $this->normalizeJson($json);
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function printFile($documentid, $report = 'Bill', $requisitesprintmode = 'all', $mode = 'pdf', $downloadfilehandler = 'true')
    {
        $this->getSessionId();
        $response = $this->getInstance()->request('GET', "Print/PrintFile/PrintFile?report={$report}&scope={$this->_sessionId}&documentid={$documentid}&requisitesprintmode={$requisitesprintmode}&mode={$mode}&downloadfilehandler={$downloadfilehandler}");
        
        header ( "Content-Type: " . $response->getHeader('Content-Type')[0]);
        header ( "Accept-Ranges: bytes");
        header ( "Content-Disposition: " . $response->getHeader('Content-Disposition')[0]);
        header ( "Content-Length: " . $response->getHeader('Content-Length')[0] );
             
       return  $response->getBody()->getContents();
    }
    
    public function GetContractorsAutocomplete($q, $limit = 500)
    {
        $this->getSessionId();
        
        $body = [
            "q" => $q, 
            "limit" => $limit            
        ];
                
        $response = $this->getInstance()->request('POST', "Business/Contractors/ContractorsAutocomplete/GetContractorsAutocomplete?withemptycontractor=true&withallcontractors=true&shortnameisinpriority=true&scope={$this->_sessionId}", [
            'body' => http_build_query($body),
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);
        
        $json = $response->getBody()->__toString();
        
        return $this->normalizeJson($json); 
    }

    public function getEmployeesList()
    {
        $organizationId = $this->getOrganizationId();
        $response = new Response($this->getInstance()->request('GET', "https://elba-staff.kontur.ru/Workers/Selection?organizationId={$organizationId}"));
        return new EmployeesList($response, true);
    }

    public function getEmployee($employeeId, $organizationId)
    {
        $response = new Response($this->getInstance()->request('GET', "https://elba-staff.kontur.ru/Worker/Wage?workerId={$employeeId}&organizationId={$organizationId}"));
        return new Employee($response);
    }    

    private function normalizeJson($json) {
        preg_match_all('/new Date\(\d+,\d+,\d+,\d+,\d+,\d+,\d+\)/i', $json, $out);
        if (isset($out[0]) && is_array($out[0])) {
            foreach ($out[0] as $dt) {
                preg_match('/new Date\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/i', $dt, $ti);
                if (isset($ti)) {
                    $n_ti = '"'.date('Y-m-d H:i:s', strtotime($ti[1].'-'.($ti[2]+1).'-'.$ti[3].' '.$ti[4].':'.$ti[5].':'.$ti[6])).'"';
                    $json = str_replace($dt, $n_ti, $json);                    
                }
            }          
        }
        return json_decode($json);
    }

}
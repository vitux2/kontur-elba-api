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
        return $scope;
    }

    public function getPartnerInfo($partnerId)
    {
        if (strlen($partnerId) != 36) return [];
        $this->getSessionId();
        $info = [];
        $response = $this->getInstance()->request('GET', "/Business/Contractors/ContractorInfo?scope={$this->_sessionId}&id={$partnerId}");
        $body = $response->getBody()->__toString();
        preg_match('/\"Name\":\"([^\"]*)\".*\"Inn\":\"([\d]{10})\".*\"Kpp\":\"([\d]{9})\"/', $body, $out);
        $info['name'] = $out[1] ?? '';
        $info['inn'] = $out[2] ?? '';
        $info['kpp'] = $out[3] ?? '';
        return $info;
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

    public function getPdfFile($documentId)
    {
        $this->getSessionId();
        $response = $this->getInstance()->request('GET', "/Print/PrintFile/PrintFile?report=Act&mode=pdf&scope={$this->_sessionId}&DocumentId={$documentId}&RequisitesPrintMode=Nothing");
        return $response->getBody()->__toString();
    }

    /*Returns only last documents*/

    public function getOutgoingDocumentList($ContractorId = null, $Type = 'undefined', $OnlyAttentionRequired = false, $Period = null, $skip = 0, $limit = 50)
    {
        try {
            $body = [
                "Period" => $Period,
                "ContractorId" => $ContractorId,
                "Type" => $Type,
                "OnlyAttentionRequired" => $OnlyAttentionRequired?true:false
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

    /* returns full documents list by groups
    * param $limit - for groups pagination.
    */
    public function getOutgoingDocumentFullList($contractorId = null, $skip = 0, $limit = 50)
    {
        try {
            $this->getSessionId();
            $body = [
                "DocumentByDeals" => $this->getOwnerIds($contractorId, 'Undefined', false, '', $skip, $limit)
            ];

            $response = $this->getInstance()->request('POST', "Business/Documents/Deals/Deals/GetOutgoingDealItems?scope={$this->_sessionId}&skip={$skip}&take={$limit}&metaonly=false&sort=Date%2Cdesc%3BUpdated%2Cdesc", [
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

    public function filterList($list, $type = 'undefined', $period = null, $skip = 0, $limit = 50, $created = '')
    {
        $services = [];
        if ($list->Items) {
            foreach ($list->Items as $service) {

                $single_service = [
                    'owner_id' => $service->ownerId,
                    'is_group' => $service->isDeal,
                    'status' => $service->DealStatus,
                    'date' => $service->Date,
                    'created' => $service->Created,
                    'file_id' => $service->Caption->FileId,
                    'type' => $service->Caption->Type,
                    'number' => $service->Caption->Number,
                    'name' => $service->Caption->Value,
                    'name_without_year' => $service->Caption->ValueWithoutYear,
                    'documents' => []
                ];

                if ($service->LinkedItems) {
                    foreach ($service->LinkedItems as $document) {
                        if ($created != '' && date("Y-m-d", strtotime($document->Created)) != date("Y-m-d", strtotime($created))) {
                            continue;
                        }
                        if ($period != '') {
                            $start = $period['Period']['StartDate']??null;
                            $end = $period['Period']['EndDate']??null;
                            if (!( (strtotime($document->Date) >= strtotime($start) ) && (strtotime($document->Date) <= strtotime($end)))) {
                                continue;
                            }
                        }
                        $single_document = [
                            'owner_id' => $document->OwnerId,
                            'file_id' => $document->Caption->FileId,
                            'type' => $document->Caption->Type,
                            'number' => $document->Caption->Number,
                            'name' => $document->Caption->Value,
                            'name_without_year' => $document->Caption->ValueWithoutYear,
                            'sended_to_diadoc' => $document->WasSentToDiadoc,
                            'last_sended'=>$document->SendToContractorLastTime,
                            'doc_type'=>$document->Type,
                            'type_name' => $document->DocumentType,
                            'date' => $document->Date,
                            'created' => $document->Created,
                            'status' => $document->Status,
                            'sum' => $document->Sum,//array
                            'sorting_sum' => $document->SumForSorting->SumForSorting
                        ];
                        $single_service['documents'][] = $single_document;
                    }
                }
                $services[] = $single_service;
            }
        }
        return $services;
    }

    public function getDocumentById($contractorId, $documentId)
    {
        $documents = $this->getOutgoingDocumentList($contractorId);

            foreach ($documents as $service) {
                if ($service['documents']) {
                    foreach ($service['documents'] as $document) {
                       if ($document['owner_id'] == $documentId)
                       {
                           return $document;
                       }
                    }
                }
            }

        return false;
    }

    public function sendEmail($settings)
    {
        try {

            $body = $settings;
            $response = $this->getInstance()->request('POST', "Business/Documents/SendViaEmail/SendViaEmail/Send?scope={$this->_sessionId}", [
                'body' => json_encode($body),
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Content-Type' => 'application/json',
                ],
            ]);
            $json = $response->getBody()->__toString();
            $result = $this->normalizeJson($json);
            return $result->Success ?? false;

        } catch (\Exception $e) {
            return [];
        }
    }

    public function sendDiadoc($docId)
    {
        $this->getSessionId();
        $params = [
            "DocumentId" => $docId,
            "ResendAccepted" => true,
            "SelectedContractor" => null,
            "SendMode" => "0"
        ];
        $response = $this->getInstance()->request('POST', "/Business/Documents/Diadoc/Diadoc/SendSingleDocument?scope={$this->_sessionId}", [
            'body' => json_encode($params),
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' => 'application/json',
            ],
        ]);
        $json = $response->getBody()->__toString();

        return $this->normalizeJson($json);
    }


    public function getEmailSettings($owner_id)
    {
        $response = $this->getInstance()->request('GET', "Business/Documents/SendViaEmail/SendViaEmail/GetViewData?contractorid=&documentids={$owner_id}&scope={$this->_sessionId}&_=1660283244878", [

        ]);

        $json = $response->getBody()->__toString();

        $settings = $this->normalizeJson($json);

        return $settings;
        // $this->sendEmail($settings);
    }

    public function getOwnerIds($ContractorId = null, $Type = 'undefined', $OnlyAttentionRequired = false, $Period = null, $skip = 0, $limit = 50)
    {
        //if(strlen($ContractorId) != 36) return [];
        try {
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
            $result_array = [];

            $owners_id = $this->normalizeJson($json);

            if (isset($owners_id->Items)) {
                foreach ($owners_id->Items as $owner_id) {
                    $result_array[] = ["DealId" => $owner_id->OwnerId];
                }
            }
            return $result_array;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function printFile($documentid, $report = 'Bill', $requisitesprintmode = 'all', $mode = 'pdf', $downloadfilehandler = 'true')
    {
        $this->getSessionId();
        $response = $this->getInstance()->request('GET', "Print/PrintFile/PrintFile?report={$report}&scope={$this->_sessionId}&documentid={$documentid}&requisitesprintmode={$requisitesprintmode}&mode={$mode}&downloadfilehandler={$downloadfilehandler}");

        header("Content-Type: " . $response->getHeader('Content-Type')[0]);
        header("Accept-Ranges: bytes");
        header("Content-Disposition: " . $response->getHeader('Content-Disposition')[0]);
        //header ( "Content-Length: " . $response->getHeader('Content-Length')[0] );

        return $response->getBody()->getContents();
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

    public function checkDocumentNumberUniqueness($number = 1, $documenttype = 0, $date = null)
    {
        $date = empty($date) ? date('d.m.Y') : $date;
        $this->getSessionId();
        $response = $this->getInstance()->request('GET', "Business/Documents/DocumentEditing/EditDocument/CheckDocumentNumberUniqueness?number={$number}&documenttype={$documenttype}&date={$date}");

        return json_decode($response->getBody()->getContents());
    }

    public function createBill($data)
    {
        $response = $this->getInstance()->request('POST', "API/CreateBill.ashx", [
            'body' => json_encode($data),
            'headers' => [
                'X-Login' => $this->_login,
                'X-Password' => $this->_password,
                'Content-Type' => 'application/json',
            ],
        ]);

        $sesult = $response->getBody()->__toString();
        return $sesult;
    }

    private function normalizeJson($json)
    {
        preg_match_all('/new Date\(\d+,\d+,\d+,\d+,\d+,\d+,\d+\)/i', $json, $out);
        if (isset($out[0]) && is_array($out[0])) {
            foreach ($out[0] as $dt) {
                preg_match('/new Date\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/i', $dt, $ti);
                if (isset($ti)) {
                    $n_ti = '"' . date('Y-m-d H:i:s', strtotime($ti[1] . '-' . ($ti[2] + 1) . '-' . $ti[3] . ' ' . $ti[4] . ':' . $ti[5] . ':' . $ti[6])) . '"';
                    $json = str_replace($dt, $n_ti, $json);
                }
            }
        }
        return json_decode($json);
    }

}
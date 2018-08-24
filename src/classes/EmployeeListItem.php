<?php

namespace KonturElbaApi\classes;

use KonturElbaApi\Client;

class EmployeeListItem
{

    public $id;

    public $firstName;

    public $lastName;

    public $middleName;

    public $organizationId;

    public $isActive;

    public function __construct($rawData)
    {
        $this->parseName($rawData['td'][0]['a'][0]['#text'][0]);
        $this->parseIds($rawData['td'][0]['a'][0]['href']);
        $this->parseStatus($rawData['td'][0]['a'][0]['class']);
    }

    protected function parseName($name)
    {
        preg_match('/(.+)\s(.+)\s(.+)/', $name, $out);
        $this->firstName = $out[2];
        $this->lastName = $out[1];
        $this->middleName = $out[3];
    }

    protected function parseIds($link)
    {
        preg_match('/workerId=([\w-]+).+organizationId=([\w-]+)/', $link,$out);
        $this->id = $out[1];
        $this->organizationId = $out[2];
    }

    protected function parseStatus($data)
    {
        $this->isActive = empty($data);
    }

    public function getFullName()
    {
        return "{$this->lastName} {$this->firstName}";
    }

    public function getEmployee(Client $client)
    {
        return $client->getEmployee($this->id, $this->organizationId);
    }

}
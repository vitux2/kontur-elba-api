<?php

namespace KonturElbaApi;

class Response
{

    protected $_dom;

    public function __construct(\GuzzleHttp\Psr7\Response $response)
    {
        $this->_dom = new \nokogiri($response->getBody()->__toString());
    }

    public function getEmployeesArray()
    {
        return $this->_dom->get('html body tbody tr')->toArray();
    }

    public function getRows()
    {
        $result = [];
        $data = $this->_dom->get('html body table tbody tr')->toArray();
        foreach ($data as $item) {
            if (isset($item['id']) && preg_match('/^WageHistory_Items_ArrayControl_[\d]+$/', $item['id'])) {
                array_push($result, $item);
            }
        }
        return $result;
    }



}

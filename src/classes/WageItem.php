<?php

namespace KonturElbaApi\classes;

class WageItem
{

    public $date;

    public $ndfl;

    public $contribution;

    public function __construct($rawData)
    {
        $this->parse($rawData);
    }

    protected function parse($data)
    {
        $this->date = $data['td'][0]['a'][0]['span'][0]['#text'][0];
        $this->ndfl = $data['td'][2]['span'][0]['a'][0]['span'][0]['#text'][0];
        $this->contribution = $data['td'][4]['span'][0]['a'][0]['span'][0]['#text'][0];
    }

}
<?php

namespace KonturElbaApi\classes;


use KonturElbaApi\Response;

/**
 * Class EmployeeResponse
 * @package src\response
 *
 * @property string $name
 */
class Employee
{

    /* @var \KonturElbaApi\classes\WageItem[] $wageList */
    public $wageList = [];

    public function __construct(Response $response)
    {
        foreach ($response->getRows() as $row) {
            array_push($this->wageList, new WageItem($row));
        }
    }

}
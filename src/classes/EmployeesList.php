<?php

namespace KonturElbaApi\classes;

use KonturElbaApi\Response;

class EmployeesList
{

    /* @var \KonturElbaApi\classes\EmployeeListItem[] $list */
    public $list = [];

    public function __construct(Response $response, $active = false)
    {
        foreach ($response->getEmployeesArray() as $employeeItem) {
            $employee = new EmployeeListItem($employeeItem);

            if ((!$employee->isActive && !$active) || $employee->isActive) {
                array_push($this->list, $employee);
            }
        }
    }

    public function findByName($name)
    {
        foreach ($this->list as $item) {
            if ($item->getFullName() == $name) {
                return $item;
            }
        }
        return false;
    }

    public function getEmployee($name, $client)
    {
        $item = $this->findByName($name);
        if (!$item) {
            return false;
        }
        
        return $item->getEmployee($client);
    }

}
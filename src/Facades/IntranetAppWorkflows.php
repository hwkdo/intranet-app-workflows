<?php

namespace Hwkdo\IntranetAppWorkflows\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppWorkflows\IntranetAppWorkflows
 */
class IntranetAppWorkflows extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppWorkflows\IntranetAppWorkflows::class;
    }
}

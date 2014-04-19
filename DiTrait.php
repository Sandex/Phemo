<?php

namespace Phemo;

use Phalcon\DiInterface;

/**
 * Trait for Phalcon\DI\InjectionAwareInterface
 */
trait DiTrait
{

    /**
     * @var DiInterface
     */
    private $di;

    public function setDI($di)
    {
        $this->di = $di;
    }

    public function getDI()
    {
        return $this->di;
    }

}

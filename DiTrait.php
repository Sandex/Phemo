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
    protected $di;

    public function setDI(DiInterface $di)
    {
        $this->di = $di;
    }

    /**
     * @return DiInterface
     */
    public function getDI()
    {
        return $this->di;
    }

}

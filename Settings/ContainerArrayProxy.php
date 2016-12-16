<?php

namespace MakinaCorpus\Drupal\Sf\Settings;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trick Drupal in thinking this will be an array of settings, but it will
 * fetch data from the container instead.
 */
class ContainerArrayProxy implements \ArrayAccess
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function offsetExists($offset)
    {
        return $this->container->hasParameter($offset);
    }

    public function offsetGet($offset)
    {
        if ($this->container->hasParameter($offset)) {
            return $this->container->getParameter($offset);
        }
        return null;
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException("You cannot modify settings at runtime");
    }

    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException("You cannot modify settings at runtime");
    }
}

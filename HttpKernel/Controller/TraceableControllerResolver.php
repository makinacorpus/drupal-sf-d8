<?php

namespace MakinaCorpus\Drupal\Sf\HttpKernel\Controller;

use Drupal\Core\Controller\ControllerResolverInterface as DrupalControllerResolverInterface;

use Symfony\Component\HttpKernel\Controller\TraceableControllerResolver as SymfonyTraceableControllerResolver;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Add missing Drupal core method for it to work.
 */
class TraceableControllerResolver extends SymfonyTraceableControllerResolver implements DrupalControllerResolverInterface
{
    protected $drupalResolver;

    /**
     * {@inheritdoc}
     */
    public function __construct(DrupalControllerResolverInterface $resolver, Stopwatch $stopwatch, ArgumentResolverInterface $argumentResolver = null)
    {
        parent::__construct($resolver, $stopwatch, $argumentResolver);

        $this->drupalResolver = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function getControllerFromDefinition($controller)
    {
        return $this->drupalResolver->getControllerFromDefinition($controller);
    }
}

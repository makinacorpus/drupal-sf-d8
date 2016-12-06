<?php

namespace MakinaCorpus\Drupal\Sf\EventDispatcher;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher as SymfonyContainerAwareEventDispatcher;

/**
 * Allow Drupal to register its own listeners via the constructor.
 */
class ContainerAwareEventDispatcher extends SymfonyContainerAwareEventDispatcher
{
    /**
     * Constructs a container aware event dispatcher.
     *
     * @param ContainerInterface $container
     * @param array $listeners
     *   A nested array of listener definitions keyed by event name and priority.
     *   The array is expected to be ordered by priority. A listener definition is
     *   an associative array with one of the following key value pairs:
     *   - callable: A callable listener
     *   - service: An array of the form [service id, method]
     *   A service entry will be resolved to a callable only just before its
     *   invocation.
     */
    public function __construct(ContainerInterface $container, array $listeners = [])
    {
        parent::__construct($container);

        foreach ($listeners as $eventName => $priorities) {
            foreach ($priorities as $priority => $callables) {
                foreach ($callables as $data) {
                    if (isset($data['service'])) {
                        $this->addListenerService($eventName, $data['service'], $priority);
                    } else if (isset($data['callable'])) {
                        $this->addListener($eventName, $data['callable'], $priority);
                    }
                }
            }
        }
    }
}

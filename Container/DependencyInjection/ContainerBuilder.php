<?php

namespace MakinaCorpus\Drupal\Sf\Container\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Container;

class ContainerBuilder extends SymfonyContainerBuilder
{
    // Redefining those two since 2.8 has them, and 3.0 dropped them, and we
    // will potentially run with both.
    const SCOPE_CONTAINER = 'container';
    const SCOPE_PROTOTYPE = 'prototype';

    /**
     * {@inheritdoc}
     */
    public function set($id, $service, $scope = self::SCOPE_CONTAINER)
    {
        // I don't understand why, but a core compiler pass changes the database
        // service to be synthetic, without any documentation nor comment, sadly
        // Drupal core overrided ContainerBuilder changes the original interface
        // contract and behaviour, so we have to do a few fixes in here.
        // @see \Drupal\Core\DependencyInjection\Compiler\BackendCompilerPass
        if (null === $service && 'database' === $id) {
            return;
        }

        // We need those to be considered as synthetic even thought there are
        // not (<del>thank</del><strong>fuck</string> you, Drupal for being so
        // nice with Symfony API.
        if ('session' === $id || 'request_stack' === $id || 'router.route_provider' === $id) {
            return Container::set($id, $service, $scope);
        }

        return parent::set($id, $service, $scope);
    }
}

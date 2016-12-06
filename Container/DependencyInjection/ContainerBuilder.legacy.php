<?php
// @codingStandardsIgnoreFile

namespace MakinaCorpus\Drupal\Sf\Container\DependencyInjection;

use Drupal\Core\DependencyInjection\ContainerBuilder as DrupalContainerBuilder;

use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Drupal container builder does an excellent job in messing pretty much
 * everything up, sadly, we cannot bypass it because a few Drupal 8 compiler
 * passes are hardcoding its class, really.
 */
class ContainerBuilder extends DrupalContainerBuilder
{
    /**
     * {@inheritdoc}
     */
    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        SymfonyContainerBuilder::__construct($parameterBag);
    }

    /**
     * Direct copy of the parent function.
     */
    protected function shareService(Definition $definition, $service, $id)
    {
        return SymfonyContainerBuilder::shareService($definition, $service, $id);
    }

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

        return SymfonyContainerBuilder::set($id, $service, $scope);
    }

    /**
     * {@inheritdoc}
     */
    public function register($id, $class = null)
    {
        return SymfonyContainerBuilder::register($id, $class);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($name, $value)
    {
        return SymfonyContainerBuilder::setParameter($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    protected function callMethod($service, $call)
    {
        return SymfonyContainerBuilder::callMethod($service, $call);
    }

    /**
     * {@inheritdoc}
     */
    public function __sleep()
    {
        return array_keys(get_object_vars($this));
    }
}

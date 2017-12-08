<?php

namespace MakinaCorpus\Drupal\Sf;

use MakinaCorpus\Drupal\Sf\Container\DependencyInjection\DrupalExtension;
use MakinaCorpus\Drupal\Sf\Container\DependencyInjection\Compiler\DrupalCompatibilityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DrupalBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new DrupalCompatibilityPass());
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtensionClass()
    {
        return DrupalExtension::class;
    }
}

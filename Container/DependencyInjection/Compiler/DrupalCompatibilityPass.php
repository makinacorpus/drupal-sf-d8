<?php

namespace MakinaCorpus\Drupal\Sf\Container\DependencyInjection\Compiler;

use MakinaCorpus\Drupal\Sf\EventDispatcher\ContainerAwareEventDispatcher as CompatEventDispatcher;
use MakinaCorpus\Drupal\Sf\HttpKernel\Controller\TraceableControllerResolver as CompatTraceableControllerResolver;
use MakinaCorpus\Drupal\Sf\Twig\Extension\TranslationExtension as CompatTranslationExtension;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DrupalCompatibilityPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->has('debug.controller_resolver')) {
            $container->getDefinition('debug.controller_resolver')->setClass(CompatTraceableControllerResolver::class);
        }

        if ($container->has('event_dispatcher')) {
            $container->getDefinition('event_dispatcher')->setClass(CompatEventDispatcher::class);
        }

        if ($container->has('twig.extension.trans')) {
            $container->getDefinition('twig.extension.trans')->setClass(CompatTranslationExtension::class);
        }
    }
}

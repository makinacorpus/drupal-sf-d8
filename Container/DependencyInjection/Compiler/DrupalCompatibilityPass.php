<?php

namespace MakinaCorpus\Drupal\Sf\Container\DependencyInjection\Compiler;

use MakinaCorpus\Drupal\Sf\EventDispatcher\ContainerAwareEventDispatcher as CompatEventDispatcher;
use MakinaCorpus\Drupal\Sf\HttpKernel\Controller\TraceableControllerResolver as CompatTraceableControllerResolver;
use MakinaCorpus\Drupal\Sf\Twig\Environment as CompatTwigEnvironment;
use MakinaCorpus\Drupal\Sf\Twig\Extension\TranslationExtension as CompatTranslationExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Drupal\Core\Template\Loader\FilesystemLoader as DrupalFilesystemLoader;
use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader as SymfonyFilesystemLoader;
use Symfony\Component\DependencyInjection\Reference;

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

        if ($container->has('twig')) {
            $container->getDefinition('twig')->setClass(CompatTwigEnvironment::class);
        }

        // Re-using the definition allows to keep already set method calls
        // and such ensures that we keep all registered custom namespaces.
        $container
            ->getDefinition('twig.loader.filesystem')
            ->setClass(SymfonyFilesystemLoader::class)
            ->setArguments([
                new Reference('templating.locator'),
                new Reference('templating.name_parser'),
                "twig.loader"
            ])
            ->addTag('twig.loader', ['priority' => 100])
            ->setConfigurator([new Reference('twig.loader.filesystem.configurator'), 'configure'])
        ;
    }
}

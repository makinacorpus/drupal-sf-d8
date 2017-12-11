<?php

namespace MakinaCorpus\Drupal\Sf\HttpKernel\Controller\ArgumentResolver;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Enabling the fullstack framework will override the argument resolver so we
 * need to reproduce what Drupal actually does in ControllerResolver::doGetArguments()
 */
class DrupalArgumentResolver implements ArgumentValueResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();

        return $type === RouteMatchInterface::class || is_subclass_of($type, RouteMatchInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        yield RouteMatch::createFromRequest($request);
    }
}

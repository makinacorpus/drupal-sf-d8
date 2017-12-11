<?php

namespace MakinaCorpus\Drupal\Sf\Twig;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\State\StateInterface;

class Environment extends \Twig_Environment
{
    /**
     * Reproduces the one of Drupal core; for fun.
     */
    public function __construct($root, CacheBackendInterface $cache, $twig_extension_hash, StateInterface $state, \Twig_LoaderInterface $loader = NULL, $options = [])
    {
        // Ensure that twig.engine is loaded, given that it is needed to render a
        // template because functions like TwigExtension::escapeFilter() are called.
        require_once $root . '/core/themes/engines/twig/twig.engine';

        // @todo will restore that later
        //   Drupal 8 overrides twig environment configuration with %twig.config%
        //   but sadly for us the original twig options are built from
        //   Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension and we
        //   cannot interfere and fetch once again...
        //   for now, this is hardcoded, but will be replaced eventually using
        //   %kernel.cache_dir%/twig
        $options['cache'] = dirname($root).'/var/cache/dev/twig';

        $this->loader = $loader;

        parent::__construct($this->loader, $options);
    }

    /**
     * @see \Drupal\Core\Template\TwigEnvironment
     *   We need this, Drupal calls it.
     */
    public function renderInline($template_string, array $context = [])
    {
        // Prefix all inline templates with a special comment.
        $template_string = '{# inline_template_start #}' . $template_string;

        return Markup::create($this->loadTemplate($template_string, null)->render($context));
    }
}

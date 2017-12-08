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
        $options['cache'] = false;

        /*
        $this->templateClasses = [];

        $options += [
            // @todo Ensure garbage collection of expired files.
            'cache' => TRUE,
            'debug' => FALSE,
            'auto_reload' => NULL,
        ];
        // Ensure autoescaping is always on.
        $options['autoescape'] = 'html';

        if ($options['cache'] === TRUE) {
          $current = $state->get('twig_extension_hash_prefix', ['twig_extension_hash' => '']);
          if ($current['twig_extension_hash'] !== $twig_extension_hash || empty($current['twig_cache_prefix'])) {
            $current = [
              'twig_extension_hash' => $twig_extension_hash,
              // Generate a new prefix which invalidates any existing cached files.
              'twig_cache_prefix' => uniqid(),

            ];
            $state->set('twig_extension_hash_prefix', $current);
          }
          $this->twigCachePrefix = $current['twig_cache_prefix'];

          $options['cache'] = new TwigPhpStorageCache($cache, $this->twigCachePrefix);
        }
        */

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

<?php

namespace MakinaCorpus\Drupal\Sf\Twig;

use Drupal\Core\Render\Markup;

class Environment extends \Twig_Environment
{
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

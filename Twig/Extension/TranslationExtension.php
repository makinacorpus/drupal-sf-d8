<?php

namespace MakinaCorpus\Drupal\Sf\Twig\Extension;

use Symfony\Bridge\Twig\Extension\TranslationExtension as SymfonyTranslationExtension;
use Symfony\Bridge\Twig\TokenParser\TransChoiceTokenParser;
use Symfony\Bridge\Twig\TokenParser\TransDefaultDomainTokenParser;

/**
 * We have to remove the trans/endtrans default parser, Drupal will use its own
 * that allows more than string nodes to be present in.
 *
 * I still don't understand why they had to rewrite it in the first place, their
 * comment is about Drupal translation being complex, but that's just bullshit,
 * the trans() and transChoice() methods on Symfony translator are enough to
 * fully proxify to Drupal translation component without loosing any feature.
 *
 * Once again, Drupal did not want to use what's already here, they love to make
 * everything much complex than it needs to be.
 */
class TranslationExtension extends SymfonyTranslationExtension
{
    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return [
            new TransChoiceTokenParser(),
            new TransDefaultDomainTokenParser(),
        ];
    }
}

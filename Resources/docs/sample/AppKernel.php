<?php

use MakinaCorpus\Drupal\Sf\DrupalAppKernel;

use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends DrupalAppKernel
{
    public function registerBundles()
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \Symfony\Bundle\MonologBundle\MonologBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \MakinaCorpus\AutocompleteBundle\AutocompleteBundle(),
            new \MakinaCorpus\FilechunkBundle\FilechunkBundle(),
            new \MakinaCorpus\IbanBundle\IbanBundle(),
            // new \MakinaCorpus\Saur\FormBundle\SaurFormBundle(),
            new \EWZ\Bundle\RecaptchaBundle\EWZRecaptchaBundle(),
        ];
    }

    /**
     * {inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        // Reproduce the config_ENV.yml file from Symfony, but keep it
        // optional instead of forcing its usage
        $customConfigFile = $this->rootDir.'/config/config_'.$this->getEnvironment().'.yml';
        if (!file_exists($customConfigFile)) {
            // Else attempt with a default one
            $customConfigFile = $this->rootDir.'/config/config.yml';
        }
        if (!file_exists($customConfigFile)) {
            // If no file is provided by the user, just use the default one
            // that provide sensible defaults for everything to work fine
            $customConfigFile = __DIR__.'/../Resources/config/config.yml';
        }

        $loader->load($customConfigFile);
    }
}

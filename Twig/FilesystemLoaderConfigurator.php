<?php

namespace MakinaCorpus\Drupal\Sf\Twig;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class FilesystemLoaderConfigurator
{
    private $kernel;
    private $moduleHandler;
    private $themeHandler;

    /**
     * Default constructor
     */
    public function __construct(KernelInterface $kernel, ModuleHandlerInterface $moduleHandler, ThemeHandlerInterface $themeHandler)
    {
        $this->kernel = $kernel;
        $this->moduleHandler = $moduleHandler;
        $this->themeHandler = $themeHandler;
    }

    /**
     * Add namespaced paths for modules and themes
     */
    public function configure(\Twig_Loader_Filesystem $loader)
    {
        $rootDir = dirname($this->kernel->getRootDir()).'/web';

        foreach ($this->moduleHandler->getModuleList() as $name => $extension) {
            $loader->addPath($rootDir.'/'.$extension->getPath(), $name);
        }
        foreach ($this->themeHandler->listInfo() as $name => $extension) {
            $templatePath = $rootDir.'/'.$extension->getPath() . '/templates';
            if (is_dir($templatePath)) {
                $loader->addPath($templatePath, $name);
            }
        }
    }
}

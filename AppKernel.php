<?php

namespace MakinaCorpus\Drupal\Sf;

use MakinaCorpus\Drupal\Sf\Container\DependencyInjection\ParameterBag\DrupalParameterBag;

use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

abstract class AppKernel extends Kernel
{
    protected $isFullStack = false;
    protected $cacheDir = null;
    protected $logDir = null;
    protected $confKey = 'conf';

    /**
     * Default constructor
     *
     * @param string $rootDir
     * @param string $environment
     * @param boolean $debug
     */
    public function __construct($drupalDir, $environment = 'prod', $debug = false)
    {
        // Compute the kernel root directory
        if (empty($GLOBALS[$this->confKey]['kernel.root_dir'])) {
            $rootDir = $drupalDir . '/../app';
            if (is_dir($rootDir)) {
                $this->rootDir = $rootDir;
            } else {
                throw new \LogicException("could not find a valid kernel.root_dir candidate");
            }
        } else {
            $this->rootDir = $GLOBALS[$this->confKey]['kernel.root_dir'];
        }

        if ($rootDir = realpath($this->rootDir)) {
            if (!$rootDir) {
                // Attempt to automatically create the root directory
                if (!mkdir($rootDir, 0750, true)) {
                    throw new \LogicException(sprintf("%s: unable to create directory", $rootDir));
                }
                if (!$rootDir = realpath($rootDir)) {
                    throw new \LogicException(sprintf("%s: unable to what ??", $rootDir));
                }
            }
            $this->rootDir = $rootDir;
        }

        // And cache directory
        if (empty($GLOBALS[$this->confKey]['kernel.cache_dir'])) {
            $this->cacheDir = $this->rootDir . '/cache/' . $environment;
        } else {
            $this->cacheDir = $GLOBALS[$this->confKey]['kernel.cache_dir'] . '/' . $environment;
        }

        if ($cacheDir = realpath($this->cacheDir)) {
            if (!$cacheDir) {
                // Attempt to automatically create the root directory
                if (!mkdir($cacheDir, 0750, true)) {
                    throw new \LogicException(sprintf("%s: unable to create directory", $cacheDir));
                }
                if (!$cacheDir = realpath($cacheDir)) {
                    throw new \LogicException(sprintf("%s: unable to what ??", $cacheDir));
                }
            }
            $this->cacheDir = $cacheDir;
        }

        // And finally, the logs directory
        if (empty($GLOBALS[$this->confKey]['kernel.logs_dir'])) {
            $this->logDir = $this->rootDir . '/logs';
        } else {
            $this->logDir = $GLOBALS[$this->confKey]['kernel.logs_dir'];
        }

        if ($logDir = realpath($this->logDir)) {
            if (!$logDir) {
                // Attempt to automatically create the root directory
                if (!mkdir($logDir, 0750, true)) {
                    throw new \LogicException(sprintf("%s: unable to create directory", $logDir));
                }
                if (!$logDir = realpath($logDir)) {
                    throw new \LogicException(sprintf("%s: unable to what ??", $logDir));
                }
            }
            $this->logDir = $logDir;
        }

        if (!empty($GLOBALS[$this->confKey]['kernel.symfony_all_the_way'])) {
            $this->isFullStack = true;
        }

        // In case this was set, even if empty, remove it to ensure that
        // the Drupal parameter bag won't override the kernel driven
        // parameters with 'NULL' values which would make the container
        // unhappy and raise exception while resolving path values
        $GLOBALS[$this->confKey]['kernel.root_dir'] = $this->rootDir;

        // More specific something for cache_dir, since the environment
        // name is suffixed, we cannot just store it, else in case of
        // cache clear/kernel drop, the second kernel will have the env
        // name appened a second time, and everything will fail.
        // I know, this is a very messed-up side effect due to wrongly
        // written settings.php files, but I should keep this for safety.
        if (empty($GLOBALS[$this->confKey]['kernel.cache_dir'])) {
            unset($GLOBALS[$this->confKey]['kernel.cache_dir']);
        }
        if (empty($GLOBALS[$this->confKey]['kernel.logs_dir'])) {
            unset($GLOBALS[$this->confKey]['kernel.logs_dir']);
        }

        parent::__construct($environment, $debug);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir()
    {
        return $this->logDir;
    }

    /**
     * Drop cache
     */
    public function dropCache()
    {
        (new Filesystem())->remove($this->getCacheDir());
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerBuilder()
    {
        $container = new ContainerBuilder(new DrupalParameterBag($this->getKernelParameters()));

        if (class_exists('ProxyManager\Configuration') && class_exists('Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator')) {
            $container->setProxyInstantiator(new RuntimeInstantiator());
        }

        return $container;
    }

    /**
     * I am so, so sorry I had to rewrite this, just because once the container
     * has been require_once'ed, it cannot be a second time during the same PHP
     * runtime, and container refresh does not work upon Drupal module enable.
     *
     * {@inheritdoc}
     */
    protected function initializeContainer()
    {
        $class = $this->getContainerClass();
        $cache = new ConfigCache($this->getCacheDir().'/'.$class.'.php', $this->debug);
        $fresh = true;
        if (!$cache->isFresh()) {
            $container = $this->buildContainer();
            $container->compile();
            $this->dumpContainer($cache, $container, $class, $this->getContainerBaseClass());

            $fresh = false;

            $this->container = $container;
        } else {
            require_once $cache->getPath();

            $this->container = new $class();
            $this->container->set('kernel', $this);
        }

//         if (!$fresh && $this->container->has('cache_warmer')) {
//             $this->container->get('cache_warmer')->warmUp($this->container->getParameter('kernel.cache_dir'));
//         }
    }

    /**
     * {inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        if ($this->isFullStack) {

            // Reproduce the config_ENV.yml file from Symfony, but keep it
            // optional instead of forcing its usage
            $customConfigFile = $this->rootDir . '/config/config_' . $this->getEnvironment() . '.yml';
            if (!file_exists($customConfigFile)) {
                // Else attempt with a default one
                $customConfigFile = $this->rootDir . '/config/config.yml';
            }
            if (!file_exists($customConfigFile)) {
                // If no file is provided by the user, just use the default one
                // that provide sensible defaults for everything to work fine
                $customConfigFile = __DIR__ . '/../Resources/config/config.yml';
            }

            $loader->load($customConfigFile);
        }
    }
}

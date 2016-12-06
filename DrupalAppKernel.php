<?php

namespace MakinaCorpus\Drupal\Sf;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Config\NullStorage;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DrupalKernel as StaticDrupalKernel;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Language\Language;
use Drupal\Core\Site\Settings;

use MakinaCorpus\Drupal\Sf\Container\DependencyInjection\Compiler\DrupalCompatibilityPass;
use MakinaCorpus\Drupal\Sf\Container\DependencyInjection\ContainerBuilder as CustomContainerBuilder;
use MakinaCorpus\Drupal\Sf\Container\DependencyInjection\ParameterBag\DrupalParameterBag;

use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Route;

/**
 * This class has been voluntarily been separated from the AppKernel class, it
 * implements the DrupalKernelInterface and does nothing more: it allows isolation
 * of Drupal-isms from Symfony-isms.
 */
class DrupalAppKernel extends AppKernel implements DrupalKernelInterface
{
    protected $classLoader;
    protected $configStorage;
    protected $containerNeedsRebuild = false;
    protected $moduleData = [];
    protected $moduleList;
    protected $serviceProviderClasses;
    protected $serviceProviders;
    protected $serviceYamls;
    protected $sitePath;

    public function __construct($drupalDir, $environment = 'prod', $debug = false, $classLoader = null)
    {
        $this->classLoader = $classLoader;

        parent::__construct($drupalDir, $environment, $debug);
    }

    /**
     * {@inheritdoc}
     */
    public function setSitePath($path)
    {
        if ($this->booted) {
            throw new \LogicException('Site path cannot be changed after calling boot()');
        }
        $this->sitePath = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function getSitePath()
    {
        return $this->sitePath;
    }

    /**
     * {@inheritdoc}
     */
    public function getAppRoot()
    {
        return $this->rootDir . '/../web';
    }

    /**
     * Get Drupal config directories.
     */
    public function getDrupalConfigDirectory()
    {
        return [
            'sync' => $this->rootDir.'/config/drupal/sync',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        if ($this->booted) {
            return $this;
        }

        // Ensure that findSitePath is set.
        if (!$this->sitePath) {
            throw new \Exception('Kernel does not have site path set before calling boot()');
        }

        // Initialize the FileCacheFactory component. We have to do it here instead
        // of in \Drupal\Component\FileCache\FileCacheFactory because we can not use
        // the Settings object in a component.
        $configuration = Settings::get('file_cache');

        // Provide a default configuration, if not set.
        if (!isset($configuration['default'])) {
            // @todo Use extension_loaded('apcu') for non-testbot
            //  https://www.drupal.org/node/2447753.
            if (function_exists('apcu_fetch')) {
                $configuration['default']['cache_backend_class'] = '\Drupal\Component\FileCache\ApcuFileCacheBackend';
            }
        }
        FileCacheFactory::setConfiguration($configuration);
        FileCacheFactory::setPrefix(Settings::getApcuPrefix('file_cache', $this->getAppRoot()));

        parent::boot();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        if (!$this->booted) {
            return;
        }

        $this->getContainer()->get('stream_wrapper_manager')->unregister();
        $this->booted = false;
        $this->container = null;
        $this->moduleList = null;
        $this->moduleData = [];
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        if ($this->container) {
            throw new \Exception('The container should not override an existing container.');
        }
        if ($this->booted) {
            throw new \Exception('The container cannot be set after a booted kernel.');
        }

        $this->container = $container;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCachedContainerDefinition()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function loadLegacyIncludes()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preHandle(Request $request)
    {
        $this->container->get('module_handler')->loadAll();
        $this->container->get('stream_wrapper_manager')->register();
        $this->initializeRequestGlobals($request);
        $this->container->get('request_stack')->push($request);
        UrlHelper::setAllowedProtocols($this->container->getParameter('filter_protocols'));
        MimeTypeGuesser::registerWithSymfonyGuesser($this->container);
    }

    /**
     * {@inheritdoc}
     */
    public function discoverServiceProviders()
    {
        $this->serviceYamls = ['app' => [], 'site' => []];
        $this->serviceProviderClasses = ['app' => [], 'site' => []];
        $this->serviceYamls['app']['core'] = 'core/core.services.yml';
        $this->serviceProviderClasses['app']['core'] = 'Drupal\Core\CoreServiceProvider';

        // Retrieve enabled modules and register their namespaces.
        if (!isset($this->moduleList)) {
            $extensions = $this->getConfigStorage()->read('core.extension');
            $this->moduleList = isset($extensions['module']) ? $extensions['module'] : [];
        }
        $module_filenames = $this->getModuleFileNames();
        $this->classLoaderAddMultiplePsr4($this->getModuleNamespacesPsr4($module_filenames));

        // Load each module's serviceProvider class.
        foreach ($module_filenames as $module => $filename) {
            $camelized = ContainerBuilder::camelize($module);
            $name = "{$camelized}ServiceProvider";
            $class = "Drupal\\{$module}\\{$name}";
            if (class_exists($class)) {
                $this->serviceProviderClasses['app'][$module] = $class;
            }
            $filename = dirname($filename) . "/$module.services.yml";
            if (file_exists($filename)) {
                $this->serviceYamls['app'][$module] = $filename;
            }
        }

        // Add site-specific service providers.
        if (!empty($GLOBALS['conf']['container_service_providers'])) {
            foreach ($GLOBALS['conf']['container_service_providers'] as $class) {
                if ((is_string($class) && class_exists($class)) || (is_object($class) && ($class instanceof ServiceProviderInterface || $class instanceof ServiceModifierInterface))) {
                    $this->serviceProviderClasses['site'][] = $class;
                }
            }
        }

        $this->serviceYamls['site'] = array_filter(Settings::get('container_yamls', []), 'file_exists');
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceProviders($origin)
    {
        return $this->serviceProviders[$origin];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        if (self::MASTER_REQUEST === $type) {
            StaticDrupalKernel::bootEnvironment($this->getAppRoot());
            $this->initializeSettings($request);
        }

        return parent::handle($request, $type, $catch);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareLegacyRequest(Request $request)
    {
        $this->boot();
        $this->preHandle($request);

        // Setup services which are normally initialized from within stack
        // middleware or during the request kernel event.
        if (PHP_SAPI !== 'cli') {
            $request->setSession($this->container->get('session'));
        }

        $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('<none>'));
        $request->attributes->set(RouteObjectInterface::ROUTE_NAME, '<none>');

        $this->container->get('request_stack')->push($request);
        $this->container->get('router.request_context')->fromRequest($request);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerBuilder()
    {
        $container = new CustomContainerBuilder(new DrupalParameterBag($this->getKernelParameters()));

        if (class_exists('ProxyManager\Configuration') && class_exists('Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator')) {
            $container->setProxyInstantiator(new RuntimeInstantiator());
        }

        return $container;
    }

    /**
     * Returns module data on the filesystem.
     *
     * @param $module
     *   The name of the module.
     *
     * @return \Drupal\Core\Extension\Extension|bool
     *   Returns an Extension object if the module is found, false otherwise.
     */
    private function moduleData($module)
    {
        if (!$this->moduleData) {
            // First, find profiles.
            $listing = new ExtensionDiscovery($this->getAppRoot());
            $listing->setProfileDirectories([]);
            $all_profiles = $listing->scan('profile');
            $profiles = array_intersect_key($all_profiles, $this->moduleList);

            // If a module is within a profile directory but specifies another
            // profile for testing, it needs to be found in the parent profile.
            $settings = $this->getConfigStorage()->read('simpletest.settings');
            $parent_profile = !empty($settings['parent_profile']) ? $settings['parent_profile'] : null;
            if ($parent_profile && !isset($profiles[$parent_profile])) {
                // In case both profile directories contain the same extension, the
                // actual profile always has precedence.
                $profiles = [$parent_profile => $all_profiles[$parent_profile]] + $profiles;
            }

            $profile_directories = array_map(function ($profile) {
                return $profile->getPath();
            }, $profiles);

            $listing->setProfileDirectories($profile_directories);

            // Now find modules.
            $this->moduleData = $profiles + $listing->scan('module');
        }

        return isset($this->moduleData[$module]) ? $this->moduleData[$module] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function updateModules(array $module_list, array $module_filenames = [])
    {
        $this->moduleList = $module_list;
        foreach ($module_filenames as $name => $extension) {
            $this->moduleData[$name] = $extension;
        }

        // If we haven't yet booted, we don't need to do anything: the new module
        // list will take effect when boot() is called. However we set a
        // flag that the container needs a rebuild, so that a potentially cached
        // container is not used. If we have already booted, then rebuild the
        // container in order to refresh the serviceProvider list and container.
        $this->containerNeedsRebuild = true;
        if ($this->booted) {
            $this->rebuildContainer();
        }
    }

    private function attachSyntheticServices(ContainerInterface $container)
    {
        $this->classLoaderAddMultiplePsr4($container->getParameter('container.namespaces'));
        $container->set('kernel', $this);
        $container->set('class_loader', $this->classLoader);
    }

    /**
     * Initializes the service container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected function initializeContainer()
    {
        parent::initializeContainer();

        $this->attachSyntheticServices($this->container);
        \Drupal::setContainer($this->container);
    }

    /**
     * This is not part of the public API but will actually trick Drupal into
     * bootstrapping correctly when using the bootEnvironment() method
     *
     * @see \Drupal\Core\DrupalKernel::initializeSettings()
     * @see \Drupal\Core\DrupalKernel::createFromRequest()
     */
    private function initializeSettings(Request $request)
    {
        $site_path = StaticDrupalKernel::findSitePath($request);

        // Override a few "Settings" before initializing it.
        $configDirectories = $this->getDrupalConfigDirectory();
        if ($configDirectories) {
            $GLOBALS['config_directories'] = $configDirectories;
        }

        $this->setSitePath($site_path);
        Settings::initialize($this->getAppRoot(), $site_path, $this->classLoader);

        // Initialize our list of trusted HTTP Host headers to protect against
        // header attacks.
        $host_patterns = Settings::get('trusted_host_patterns', []);
        if (PHP_SAPI !== 'cli' && !empty($host_patterns)) {
            if (StaticDrupalKernel::setupTrustedHosts($request, $host_patterns) === false) {
                throw new BadRequestHttpException('The provided host name is not valid for this server.');
            }
        }
    }

    /**
     * Bootstraps the legacy global request variables.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @todo D8: Eliminate this entirely in favor of Request object.
     */
    private function initializeRequestGlobals(Request $request)
    {
        global $base_url;
        // Set and derived from $base_url by this function.
        global $base_path, $base_root;
        global $base_secure_url, $base_insecure_url;

        // Create base URL.
        $base_root = $request->getSchemeAndHttpHost();
        $base_url = $base_root;

        // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
        // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
        if ($dir = rtrim(dirname($request->server->get('SCRIPT_NAME')), '\/')) {
            // Remove "core" directory if present, allowing install.php,
            // authorize.php, and others to auto-detect a base path.
            $core_position = strrpos($dir, '/core');
            if ($core_position !== false && strlen($dir) - 5 == $core_position) {
                $base_path = substr($dir, 0, $core_position);
            } else {
                $base_path = $dir;
            }
            $base_url .= $base_path;
            $base_path .= '/';
        } else {
            $base_path = '/';
        }

        $base_secure_url = str_replace('http://', 'https://', $base_url);
        $base_insecure_url = str_replace('https://', 'http://', $base_url);
    }

    /**
     * {@inheritdoc}
     */
    public function rebuildContainer()
    {
        $this->moduleList = null;
        $this->moduleData = [];
        $this->containerNeedsRebuild = true;

        // Since Drupal 8 does trigger this at runtime, we need to keep a few
        // components in order to avoid crashes when dealing with runtime
        // container rebuilds. Those services are supposed to have no or very
        // few dependencies so it will not keep outdated references, in theory.
        $services = [
            'session' => null,
            'request_stack' => null,
        ];

        foreach (array_keys($services) as $id) {
            if ($this->container->has($id)) {
                $services[$id] = $this->container->get($id);
            }
        }

        $this->dropCache();
        $this->initializeContainer();

        foreach ($services as $id => $service) {
            if (null !== $service) {
                $this->container->set($id, $service);
            }
        }

        foreach ($this->getBundles() as $bundle) {
            $bundle->setContainer($this->container);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateContainer()
    {
        $this->containerNeedsRebuild = true;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildContainer()
    {
        $container = parent::buildContainer();

        $this->initializeServiceProviders();
        $container->set('kernel', $this);
        $container->setParameter('container.modules', $this->getModulesParameter());

        // Get a list of namespaces and put it onto the container.
        $namespaces = $this->getModuleNamespacesPsr4($this->getModuleFileNames());
        // Add all components in \Drupal\Core and \Drupal\Component that have one of
        // the following directories:
        // - Element
        // - Entity
        // - Plugin
        foreach (['Core', 'Component'] as $parent_directory) {
            $path = 'core/lib/Drupal/' . $parent_directory;
            $parent_namespace = 'Drupal\\' . $parent_directory;
            foreach (new \DirectoryIterator($this->getAppRoot() . '/' . $path) as $component) {
                /** @var $component \DirectoryIterator */
                $pathname = $component->getPathname();
                if (!$component->isDot() && $component->isDir() && (
                    is_dir($pathname . '/Plugin') ||
                    is_dir($pathname . '/Entity') ||
                    is_dir($pathname . '/Element')
                )) {
                    $namespaces[$parent_namespace . '\\' . $component->getFilename()] = $path . '/' . $component->getFilename();
                }
            }
        }
        $container->setParameter('container.namespaces', $namespaces);

        // In some case, especially when errors happen, the container is needed
        // in some code path, so even if it's not fully initialized yet, give it
        // to everyone.
        // Note that this is required we do it after the container.namespaces
        // initialization.
        $this->attachSyntheticServices($container);
        \Drupal::setContainer($container);

        // Store the default language values on the container. This is so that the
        // default language can be configured using the configuration factory. This
        // avoids the circular dependencies that would created by
        // \Drupal\language\LanguageServiceProvider::alter() and allows the default
        // language to not be English in the installer.
        $default_language_values = Language::$defaultValues;
        if ($system = $this->getConfigStorage()->read('system.site')) {
            if ($default_language_values['id'] != $system['langcode']) {
                $default_language_values = ['id' => $system['langcode']];
            }
        }
        $container->setParameter('language.default_values', $default_language_values);

        // Register synthetic services.
        $container->register('class_loader')->setSynthetic(true);
        $container->register('kernel', 'Symfony\Component\HttpKernel\KernelInterface')->setSynthetic(true);
        $container->register('service_container', 'Symfony\Component\DependencyInjection\ContainerInterface')->setSynthetic(true);

        // Register application services.
        foreach ($this->serviceYamls['app'] as $filename) {
            $loader = new YamlFileLoader($container, new FileLocator(dirname($this->getAppRoot() . '/' . $filename)));
            $loader->load(basename($filename));
        }
        foreach ($this->serviceProviders['app'] as $provider) {
            if ($provider instanceof ServiceProviderInterface) {
                $provider->register($container);
            }
        }

        // Register site-specific service overrides.
        foreach ($this->serviceYamls['site'] as $filename) {
            $loader = new YamlFileLoader($container, new FileLocator(dirname($this->getAppRoot() . '/' . $filename)));
            $loader->load(basename($filename));
        }
        foreach ($this->serviceProviders['site'] as $provider) {
            if ($provider instanceof ServiceProviderInterface) {
                $provider->register($container);
            }
        }

        // Fixes Drupal stupid overrides compatibility problems by doing some
        // heavy black magic.
        $container->addCompilerPass(new DrupalCompatibilityPass());
        $customLoader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $customLoader->load('services.yml');

        // Drupal overrides the twig filesystem loader service, but the Symfony
        // paths are set during the extension merge compiler pass, which happens
        // to always be the first. Since the result will be merge back to the
        // current application container, Drupal 'twig.loader.filesystem' service
        // will override the one that Twig set itself, with the correct paths
        // etc. so we need to remove it before it compiles.
        $container->removeDefinition('twig.loader.filesystem');

        // And for the exact same reason, remove the Drupal twig environnement
        // override because it does not provide anything good:
        //   - we get rid of remote backend caching, twig is fine PHP in files;
        //   - we get rid of the Drupal security policy which is way too much
        //     restrictve and prevent default Symfony templates from working.
        // And why does the fuck, Drupal, do you want to do everything your way
        // when everything is working fine at everyone else's?
        $container->removeDefinition('twig');

        return $container;
    }

    /**
     * Registers all service providers to the kernel.
     *
     * @throws \LogicException
     */
    private function initializeServiceProviders()
    {
        $this->discoverServiceProviders();

        $this->serviceProviders = ['app' => [], 'site' => []];

        foreach ($this->serviceProviderClasses as $origin => $classes) {
            foreach ($classes as $name => $class) {
                if (!is_object($class)) {
                    $this->serviceProviders[$origin][$name] = new $class();
                } else {
                    $this->serviceProviders[$origin][$name] = $class;
                }
            }
        }
    }

    /**
     * Returns the active configuration storage to use during building the container.
     *
     * @return \Drupal\Core\Config\StorageInterface
     */
    private function getConfigStorage()
    {
        if (!isset($this->configStorage)) {
            // The active configuration storage may not exist yet; e.g., in the early
            // installer. Catch the exception thrown by config_get_config_directory().
            try {
                $this->configStorage = BootstrapConfigStorageFactory::get($this->classLoader);
            } catch (\Exception $e) {
                $this->configStorage = new NullStorage();
            }
        }

        return $this->configStorage;
    }

    /**
     * Returns an array of Extension class parameters for all enabled modules.
     *
     * @return array
     */
    private function getModulesParameter()
    {
        $extensions = [];

        foreach ($this->moduleList as $name => $weight) {
            if ($data = $this->moduleData($name)) {
                $extensions[$name] = [
                    'type'      => $data->getType(),
                    'pathname'  => $data->getPathname(),
                    'filename'  => $data->getExtensionFilename(),
                ];
            }
        }

        return $extensions;
    }

    /**
     * Gets the file name for each enabled module.
     *
     * @return array
     *   Array where each key is a module name, and each value is a path to the
     *   respective *.info.yml file.
     */
    private function getModuleFileNames()
    {
        $filenames = [];

        foreach ($this->moduleList as $module => $weight) {
            if ($data = $this->moduleData($module)) {
                $filenames[$module] = $data->getPathname();
            }
        }

        return $filenames;
    }

    /**
     * Gets the PSR-4 base directories for module namespaces.
     *
     * @param string[] $module_file_names
     *   Array where each key is a module name, and each value is a path to the
     *   respective *.info.yml file.
     *
     * @return string[]
     *   Array where each key is a module namespace like 'Drupal\system', and each
     *   value is the PSR-4 base directory associated with the module namespace.
     */
    private function getModuleNamespacesPsr4($module_file_names)
    {
        $namespaces = [];

        foreach ($module_file_names as $module => $filename) {
            $namespaces["Drupal\\$module"] = dirname($filename) . '/src';
        }

        return $namespaces;
    }

    /**
     * Registers a list of namespaces with PSR-4 directories for class loading.
     *
     * @param array $namespaces
     *   Array where each key is a namespace like 'Drupal\system', and each value
     *   is either a PSR-4 base directory, or an array of PSR-4 base directories
     *   associated with this namespace.
     */
    private function classLoaderAddMultiplePsr4(array $namespaces = [])
    {
        foreach ($namespaces as $prefix => $paths) {
            if (is_array($paths)) {
                foreach ($paths as $key => $value) {
                    $paths[$key] = $this->getAppRoot() . '/' . $value;
                }
            } else if (is_string($paths)) {
                $paths = $this->getAppRoot() . '/' . $paths;
            }

            $this->classLoader->addPsr4($prefix . '\\', $paths);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContainer()
    {
        return parent::getContainer();
    }

    /**
     * Please, override me!
     */
    public function registerBundles()
    {
        return [];
    }
}

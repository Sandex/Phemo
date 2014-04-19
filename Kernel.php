<?php

namespace Phemo;

use Phalcon\Config;
use Phalcon\DI;
use Phalcon\DI\FactoryDefault;
use Phalcon\DiInterface;
use Phalcon\Exception;
use Phalcon\Http\Response;
use Phalcon\Loader;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Model\Transaction\Manager;
use Phalcon\Mvc\Router\Route;
use Phalcon\Mvc\View;
use Phalcon\Session\Adapter\Files;

class Kernel
{

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var boolean
     */
    protected $debug;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var DiInterface
     */
    protected $di;

    /**
     * @var Loader
     */
    protected $loader;

    /**
     * @var array
     */
    protected $bundlesModules;

    /**
     * @param stirng $environment
     * @param boolean $debug
     */
    public function __construct($environment, $debug = false)
    {
        $this->environment = $environment;
        $this->debug = (boolean) $debug;

        if ($this->debug) {
            error_reporting(E_ALL);
        }
        else {
            error_reporting(0);
        }

        $config = new Config($this->registerConfig());
        $this->setConfig($config);

        $this->setBundles($this->registerBundles());

        try {
            $this->registerAutolader();

            $this->confingureAutolader();

            $this->confingureServices();

            $this->initializeBundles();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function setConfig(Config $config)
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setBundles($bundles)
    {
        $this->bundles = $bundles;

        return $this;
    }

    public function getBundles()
    {
        return $this->bundles;
    }

    /**
     * Handle app request
     */
    public function handle()
    {
        try {
            // Handle the request
            $application = new Application($this->getDI());

            // Register modules
            if (is_array($this->bundlesModules)) {
                $application->registerModules($this->bundlesModules);
            }

            /* @var $response Response */
            $response = $application->handle();

            echo $response->getContent();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Create a DI
     *
     * @return DI
     */
    private function getDI()
    {
        if (!$this->di) {
            $this->di = new FactoryDefault;
        }

        return $this->di;
    }

    /**
     * Push components into DI
     *
     * @return DI
     */
    private function confingureServices()
    {
        $di = $this->getDI();

        // Register config as service
        $di->setShared('config', function() {
            return $this->getConfig();
        });

        $di->setShared('bundles', function() {
            return $this->getBundles();
        });


        /* @var $assets Manager */
        $assets = $this->getDI()->get('assets');
        $assets->collection('headerCss');
        $assets->collection('headerJs');



        // Start the session the first time a component requests the session service
        $di->set('session', function() {
            $session = new Files();
            $session->start();

            return $session;
        });


        // Setup the database service
        $di->set('db', function() {
            $pdoAdapter = '\\Phalcon\\Db\\Adapter\\Pdo\\' . ucfirst($this->config->database->adapter);
            unset($this->config->database->adapter);

            if (!class_exists($pdoAdapter)) {
                throw new \Phalcon\Db\Exception('DB adapter "' . $pdoAdapter . '" not exists! See param database->adapter in config file.');
            }

            try {
                $db = new $pdoAdapter((array) $this->config->database);
            } catch (Exception $e) {
                $this->handleException($e);
            }

            return $db;
        });


        // Setup the view component
        $di->set('view', function() {
            $templating = $this->config->framework->templating;

            $view = new View();
            $view->registerEngines((array) $templating->engines);
            $view->setViewsDir(__DIR__ . $templating->baseView);

            return $view;
        });

        // Set i13n
        $di->setShared('i13n', function() {
            /* @var $dispatcher DispatcherInterface */
            $dispatcher = $this->getDI()->getShared('dispatcher');

            // Get currnet language from query
            $language = $dispatcher->getParam('language', 'string');
            $locale = $dispatcher->getParam('locale', 'string');
            $locale = $locale ? $locale : $language;

            if ($language) {
                $i13n = $language . '-' . $locale;
            }
            else {
                // Get currnet browser language setting

                /* @var $request Request */
                $request = $this->getDI()->getShared('request');
                $i13n = $request->getBestLanguage();
            }

            return strtolower($i13n);
        });

        return $this;
    }

    /**
     * Register an autoloader
     */
    private function registerAutolader()
    {
        if (!$this->loader) {
            $this->loader = new Loader();
        }

        return $this->loader;
    }

    private function confingureAutolader()
    {
        $autoloadDirs = $this->config->framework->autoloadDirs;

        $namespaces = [];

        // Add phemo vendor autoload
        foreach (['Mvc'] as $dir) {
            $namespaces['Phemo\\' . $dir] = realpath(__DIR__ . DIRECTORY_SEPARATOR . $dir);
        }
        $namespaces['Phemo'] = realpath(__DIR__);


        // Add autoload bundles
        foreach ($this->getBundles() as $bundleNamespace => $bundlePath) {
            foreach ($autoloadDirs as $dir) {
                $namespaces[$bundleNamespace . '\\' . $dir] = realpath(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 3) . DIRECTORY_SEPARATOR . $bundlePath . $dir);
            }
            $namespaces[$bundleNamespace] = realpath(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 3) . DIRECTORY_SEPARATOR . $bundlePath);
        }

        $this->loader->registerNamespaces($namespaces);
        $this->loader->register();

        return $this;
    }

    /**
     * Initialize all project bundles
     */
    private function initializeBundles()
    {
        foreach ($this->getBundles() as $bundleNamespace => $bundlePath) {
            $this->initializeBundle($bundleNamespace, $bundlePath);
        }
    }

    /**
     * Initialize bundle
     *
     * @param string $bundleNamespace
     * @param string $bundlePath
     */
    private function initializeBundle($bundleNamespace, $bundlePath)
    {
        /* @var $router Route */
        $router = $this->getDI()->getShared('router');

        $bundleNamespaceParsts = preg_split("/\\\/", $bundleNamespace);
        $bundleVendorName = array_shift($bundleNamespaceParsts);
        $bundleName = array_shift($bundleNamespaceParsts);
        $prefix = strtolower($bundleVendorName . '/' . str_replace('Bundle', '', $bundleName));

        /**
         * Example:
         * phalcon.local/phemo/demo/product/index/?page=2
         * phalcon.local/fr/phemo/demo/product/index/?page=2
         * phalcon.local/fr-ca/phemo/demo/product/index/?page=2
         *
         * language     = fr
         * locale       = ca
         * module       = phemo/demo
         * controller   = product
         * action       = index
         * params       = page=2
         */
        $router->add('/(([a-z]{2})(-([a-z]{2}))?/)?' . $prefix . '(/:controller(/:action(/:params)?)?)?', [
            'module'     => $prefix,
            'namespace'  => $bundleNamespace . '\\Controller',
            'language'   => 2,
            'locale'     => 4,
            'controller' => 6,
            'action'     => 8,
            'params'     => 10,
        ]);

        $this->bundlesModules[$prefix] = [
            'className' => $bundleNamespace . '\BundleModule',
            'path'      => realpath(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 3) . DIRECTORY_SEPARATOR . $bundlePath . 'BundleModule.php'),
        ];

        // Register independent bundle service
        $bundleModuleClass = '\\' . $bundleNamespace . '\\BundleModule';
        $registerServicesBundleMethod = 'registerServicesBundle';

        if (method_exists($bundleModuleClass, $registerServicesBundleMethod)) {
            $bundleModuleClass::$registerServicesBundleMethod($this->getDI());
        }
    }

    /**
     * Format and output exception info
     *
     * @todo use prettify tool https:// github.com/phalcon/pretty-exceptions
     *
     * @param \Exception $e
     */
    private function handleException(\Exception $e)
    {
        $out = '<pre>';
        $out .= get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
        $out .= 'File: ' . $e->getFile() . PHP_EOL;
        $out .= 'Line: ' . $e->getLine() . PHP_EOL;
        $out .= '<br/>';
        $out .= $e->getTraceAsString();
        $out .= '</pre>';

        echo $out;
    }

}

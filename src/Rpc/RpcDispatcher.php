<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/9/9
 * Time: 下午11:15
 */

namespace Inhere\Server\Rpc;

use Inhere\Exceptions\RequestException;
use Inhere\Library\Traits\EventTrait;
use Inhere\Library\Traits\OptionsTrait;

/**
 * Class RpcDispatcher
 * @package Inhere\Server\Rpc
 */
class RpcDispatcher
{
    use OptionsTrait;
    use EventTrait;

    // events
    const ON_FOUND = 'found';
    const ON_NOT_FOUND = 'notFound';
    const ON_EXEC_START = 'execStart';
    const ON_EXEC_END = 'execEnd';
    const ON_EXEC_ERROR = 'execError';

    /**
     * @var array
     * [
     *  name => ServiceInterface
     * ]
     */
    private $services = [];

    /**
     * @var array
     */
    protected $options = [
        // default service name
        'defaultService' => null,

        // default service action method name
        'defaultAction' => 'index',

        // action executor. will auto call controller's executor method to run all action.
        // e.g: 'actionExecutor' => 'run'`
        'actionExecutor' => '', // 'run'
    ];

    /**
     * @param string $name
     * @param \Closure|ServiceInterface|string $handler
     * @param bool $override
     */
    public function add(string $name, $handler, $override = false)
    {
        $this->register($name, $handler, $override);
    }

    /**
     * @param string $name
     * @param \Closure|ServiceInterface $handler
     * @param bool $override
     */
    public function register(string $name, $handler, $override = false)
    {
        $name = trim($name);

        if (!$override && $this->hasService($name)) {
            throw new \RuntimeException("The service:{$name} has been registered!");
        }

        if (\is_string($handler) && class_exists($handler)) {
            $handler = new $handler;
        } elseif (!\is_object($handler)) {
            throw new \InvalidArgumentException("The service:{$name} handler must is an Object!");
        }

        // instanceof ServiceInterface OR a closure
        if ($handler instanceof ServiceInterface || method_exists($handler, '__invoke')) {
            $this->services[$name] = $handler;
        } else {
            throw new \InvalidArgumentException("The service:{$name} handler must is an instanceof ServiceInterface Object OR a Closure!");
        }
    }

    /**
     * @param array $map
     */
    public function registers(array $map)
    {
        foreach ($map as $item) {
            if (isset($item[0], $item[1])) {
                $this->register($item[0], $item[1], $item[2] ?? false);
            }
        }
    }

    /**
     * @param string $key e.g 'user' OR 'user/info'
     * @param array $params
     * @return mixed
     */
    public function dispatch(string $key, $params = null)
    {
        $result = null;
        $method = null;
        $name = $key = trim($key, '/ ');
        $params = $params ?: [];

        try {
            if (!$key) {
                $key = $this->options['defaultService'];
            }

            if (!$key) {
                throw new RequestException('The request service name is required');
            }

            // split service name and method name
            if (strpos($key, '/')) {
                list($name, $method,) = explode('/', $key, 3);
            }

            if (!$service = $this->getService($name)) {
                throw new RequestException("The request service [$name] is not exists");
            }

            // trigger exec_start event
            $this->fire(self::ON_EXEC_START, [$key, $params]);

            if ($service instanceof ServiceInterface) {
                // defined default action
                if (!$method && !($method = $this->options['defaultAction'])) {
                    throw new \RuntimeException("No any service[$name] method to call");
                }

                // if set the 'actionExecutor', the action handle logic by it.
                if ($executor = $this->options['actionExecutor']) {
                    $result = $service->$executor($method, $params);
                } else {
                    $result = $service->$method(...$params);
                }
            } elseif (method_exists($service, '__invoke')) {
                $result = $service(...$params);
            } else {
                throw new \RuntimeException("Invalid service handler for the service [$name]");
            }
            // trigger exec_end event
            $this->fire(self::ON_EXEC_END, [$result, $key, $params]);
        } catch (\Throwable $e) {
            // trigger exec_error event
            $this->fire(self::ON_EXEC_ERROR, [$e, $key, $params]);
        }

        return $result;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getService(string $name)
    {
        return $this->services[$name] ?? null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasService(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @param array $services
     */
    public function setServices(array $services)
    {
        $this->services = $services;
    }
}

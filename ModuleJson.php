<?php
namespace Config\Router;
class ModuleJson  implements \Level2\Router\Rule {
	private $moduleDir;
	private $configFile;
	private $moduleNames;
	private $jsonLoader;
	private $request;

	public function __construct(\Dice\Dice $dice, \Level2\Core\Request $request, $moduleDir = 'Modules', $configFile = 'manifest.json') {
		$this->dice = $dice;
		$this->moduleDir = $moduleDir;
		$this->configFile = $configFile;
		$this->request = $request;
	}

	public function find(array $route) {
		if (count($route) == 0 || $route[0] == '') return false;

        $config = $this->getConfig($route);
		$method = $this->request->server('REQUEST_METHOD');


		if (!isset($route[0]) || (isset($route[0]) && $route[0] == '')) $routeName = isset($config->$method->defaultRoute) ? $config->$method->defaultRoute : 'index';
		else $routeName = array_shift($route);

		if (isset($config->$method->$routeName)) {
			$matchedRoute = $config->$method->$routeName;

			// This allows POST to inherit from GET if "inherit" : "GET" is set (or vice versa)
			if (isset($matchedRoute->inherit) && isset($config->{$matchedRoute->inherit}->$routeName)) {
				$inheritMethod = $matchedRoute->inherit;
				$matchedRoute = (object) array_merge((array)$matchedRoute, (array)$config->$inheritMethod->$routeName);
			}

			return $this->getRoute($matchedRoute, $route);
		}
	}

	private function getRouteDir($moduleName) {
		return $this->moduleDir . '/' . $moduleName;
	}

    public function getConfig(&$route) {
        $moduleName = array_shift($route);

        $directory = $this->getRouteDir($moduleName);
        $file = $directory . '/' . $this->configFile;
        return $this->getRouteModuleFile($file);
    }

	private function getRouteModuleFile($file) {
		if (file_exists($file)) {
			$config = json_decode(str_replace('{dir}', dirname($file), file_get_contents($file)));

			// Extend property
			if (isset($route->extend)) {
				$extended = $this->getRouteModuleFile($directory . DIRECTORY_SEPARATOR . $route->extend);
				$config = (object) array_merge((array)$extended, (array)$config);
			}
			return $config;
		}
		else return false;
	}

	private function getRoute($routeSettings, $route) {
		$this->dice->addRule('$View', (array) $routeSettings->view);

		if (isset($routeSettings->model)) {
			$this->dice->addRule('$Model',  json_decode(json_encode($routeSettings->model), true));
			$model = $this->dice->create('$Model', [], [$this->request]);
		}
		else $model = null;

		if (isset($routeSettings->controller)) {

			$controllerRule = (array) $routeSettings->controller;

			if ($routeSettings->action == '$1') {
				$action = isset($route[0]) && method_exists($controllerRule['instanceOf'], $route[0]) ? array_shift($route) : $routeSettings->defaultAction;
			}
			else $action = $routeSettings->action;

			$controllerRule['call'] = [];

			$controllerRule['call'][] = [$action, $route];
			$this->dice->addRule('$controller', $controllerRule);
			$controller = $this->dice->create('$controller', [], [$model, $this->request]);
		}
		else $controller = null;


		$view = $this->dice->create('$View');

		$route = new \Level2\Router\Route($model, $view, $controller, getcwd());
		return $route;
	}
}

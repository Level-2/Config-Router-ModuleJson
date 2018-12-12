<?php
namespace Level2\Router\Config;
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
			array_shift($route);
			$method = $this->request->server('REQUEST_METHOD');


			if (empty($route[0]) || !isset($config[$method][$route[0]])) $routeName = $config[$method]['defaultRoute'] ?? 'index';
			else $routeName = array_shift($route);

			if (isset($config[$method][$routeName])) {
				$matchedRoute = $config[$method][$routeName];

				// This allows POST to inherit from GET if "inherit" : "GET" is set (or vice versa)
				if (isset($matchedRoute['inherit']) && isset($config[$matchedRoute['inherit']][$routeName])) {
					$inheritMethod = $matchedRoute['inherit'];
					$matchedRoute = array_merge($config[$inheritMethod][$routeName], $matchedRoute);
				}
			}
			else return false;

		return $this->getRoute($matchedRoute, $route);
	}

	private function getRouteDir($moduleName) {
		$files = glob($this->moduleDir . '/*');
		$match = preg_grep('/' . $this->moduleDir . '\/' . $moduleName . '/i', $files);
		return array_values($match)[0] ?? false;
	}

	public function getConfig($route) {
		$moduleName = $route[0] ?? '';

		$directory = $this->getRouteDir($moduleName);
		$file = $directory . '/' . $this->configFile;
		return $this->getRouteModuleFile($file);
	}

	private function getRouteModuleFile($file) {
		if (file_exists($file)) {
			$config = json_decode(str_replace('"./', '"' . dirname($file) . '/', file_get_contents($file)), true);

			// Extend property
			if (isset($route['extend'])) {
				$extended = $this->getRouteModuleFile($directory . DIRECTORY_SEPARATOR . $route['extend']);
				$config = array_merge($extended, $config);
			}
			return $config;
		}
		else return false;
	}

	private function getRoute($routeSettings, $route) {
		$this->dice->addRule('$View', $routeSettings['view']);

		if (isset($routeSettings['model'])) {
			$this->dice->addRule('$Model',  $routeSettings['model']);
			$model = $this->dice->create('$Model', []);
		}
		else if (isset($routeSettings['models'])) {
			$model = [];
			foreach ($routeSettings['models'] as $name => $diceRule) {
				$this->dice->addRule('$Model_' . $name, $diceRule);
				$model[$name] = $this->dice->create('$Model_' . $name, []);
			}
		}
		else $model = null;

		if (isset($routeSettings['controller'])) {

			$controllerRule = $routeSettings['controller'];

			if ($routeSettings['action'] == '$1') {
				$action = isset($route[0]) && method_exists($controllerRule['instanceOf'], $route[0]) ? array_shift($route) : $routeSettings['defaultAction'];
			}
			else $action = $routeSettings['action'];

			$controllerRule['call'] = [];

			$controllerRule['call'][] = [$action, $route];
			$this->dice->addRule('$controller', $controllerRule);
			if (is_array($model)) {
				$controller = $this->dice->create('$Controller', [], array_merge(array_values($model), [$this->request]));
			}
			else {
				$controller = $this->dice->create('$Controller', [], [$model, $this->request]);
			}
		}
		else $controller = null;

		$view = $this->dice->create('$View');

		$route = new \Level2\Router\Route($model, $view, $controller, getcwd());
		return $route;
	}
}

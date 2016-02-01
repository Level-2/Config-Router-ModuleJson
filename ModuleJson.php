<?php
namespace Config\Router;
class ModuleJson  implements \Level2\Router\Rule {
	private $moduleDir;
	private $configFile;
	private $moduleNames;
	private $jsonLoader;
	private $request;

	public function __construct(\Dice\Dice $dice, \Level2\Core\Request $request, \Dice\Loader\Json $jsonLoader, $moduleDir = 'Modules', $configFile = 'manifest.json', $defaultConfig = 'Conf/Dice/Module.json') {
		$this->dice = $dice;
		$this->jsonLoader = $jsonLoader;
		$this->moduleDir = $moduleDir;
		$this->configFile = $configFile;
		$this->request = $request;
	}

	public function find(array $route) {
		if (count($route) == 0 || $route[0] == '') return false;
		else if (file_exists($this->moduleDir . '/' . $route[0] . '/' . $this->configFile)) $config = json_decode(str_replace('{dir}', $this->moduleDir . '/' . $route[0], file_get_contents($this->moduleDir . '/' . $route[0] . '/' . $this->configFile)));
		else return false;

		$moduleName = array_shift($route);

		$method = $this->request->server('REQUEST_METHOD');

		if ($route[0] == '') $routeName = isset($config->$method->defaultRoute) ? $config->$method->defaultRoute : 'index';
		else $routeName = array_shift($route);

		if (isset($config->$method->$routeName)) {
			//Store the original directory

			//chdir into the module folder so all includes are relative

			$matchedRoute = $config->$method->$routeName;
			$this->dice->addRule('$View', (array) $matchedRoute->view);

			if (isset($matchedRoute->model)) {
				$this->dice->addRule('$Model',  json_decode(json_encode($matchedRoute->model), true));
				$model = $this->dice->create('$Model', [], [$this->request]);
			}
			else $model = null;

			if (isset($matchedRoute->controller)) {

				$controllerRule = (array) $matchedRoute->controller;

				if ($matchedRoute->action == '$1') {
					$action = isset($route[0]) && method_exists($controllerRule['instanceOf'], $route[0]) ? array_shift($route) : $matchedRoute->defaultAction;	
				}
				else $action = $mathedRoute->action;
				
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
}



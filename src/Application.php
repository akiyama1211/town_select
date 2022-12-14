<?php

class Application
{
    public $request;

    protected $router;
    protected $response;

    public function __construct()
    {
        $this->request = new Request();
        $this->router = new Router($this->registerRoutes());
        $this->response = new Response();
    }

    public function run(): void
    {
        try {
            $params = $this->router->resolve($this->request->getPathInfo());
            if (!$params) {
                throw new HttpNotFoundException();
            }
            $controller = $params['controller'];
            $action = $params['action'];
            $this->runAction($controller, $action);
        } catch (HttpNotFoundException) {
            $this->render404Page();
        }

        $this->response->send();
    }

    private function runAction($controllerName, $action): void
    {
        $controllerClass = ucfirst($controllerName) . 'Controller';
        if (!class_exists($controllerClass)) {
            throw new HttpNotFoundException();
        }

        $controller = new $controllerClass($this);
        $content = $controller->run($action);
        $this->response->setContent($content);
    }

    private function registerRoutes(): array
    {
        return [
            '/' => ['controller' => 'home', 'action' => 'index'],
            '/explain' => ['controller' => 'home', 'action' => 'explain'],
            '/inquiry' => ['controller' => 'home', 'action' => 'inquiry'],
            '/result' => ['controller' => 'home', 'action' => 'analyze'],
        ];
    }

    private function render404Page(): void
    {
        $this->response->setStatusCode(404, 'Not Found');
        $this->response->setContent(
            <<<EOT
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>404 </title>
    </head>
    <body>
        <h1>404 Page Not Found.</h1>
    </body>
</html>
EOT
        );
    }
}

<?php
/**
 * 簡易路由器
 * core/Router.php
 */

class Router
{
    private array $routes = [];
    private string $basePath = '';
    
    /**
     * 設定基礎路徑
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = rtrim($path, '/');
    }
    
    /**
     * 註冊 GET 路由
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * 註冊 POST 路由
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * 註冊 PUT 路由
     */
    public function put(string $path, callable|array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * 註冊 DELETE 路由
     */
    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * 新增路由
     */
    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $pattern = $this->pathToPattern($path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }
    
    /**
     * 將路徑轉換為正則表達式
     * /api/cohorts/{id} -> #^/api/cohorts/([^/]+)$#
     */
    private function pathToPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * 解析請求並執行對應的處理器
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // 移除基礎路徑
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }
        
        // 移除尾部斜線
        $uri = rtrim($uri, '/') ?: '/';
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches); // 移除完整匹配
                $this->executeHandler($route['handler'], $matches);
                return;
            }
        }
        
        // 找不到路由
        ApiResponse::notFound('路由不存在: ' . $uri);
    }
    
    /**
     * 執行處理器
     */
    private function executeHandler(callable|array $handler, array $params = []): void
    {
        if (is_array($handler)) {
            // [ControllerClass, 'methodName']
            [$class, $method] = $handler;
            $controller = new $class();
            call_user_func_array([$controller, $method], $params);
        } else {
            // 匿名函式
            call_user_func_array($handler, $params);
        }
    }
    
    /**
     * 群組路由（共用前綴）
     */
    public function group(string $prefix, callable $callback): void
    {
        $originalBasePath = $this->basePath;
        $this->basePath .= $prefix;
        $callback($this);
        $this->basePath = $originalBasePath;
    }
}

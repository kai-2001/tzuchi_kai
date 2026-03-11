<?php
/**
 * 自動載入器
 * core/Autoloader.php
 * 
 * 使用 PSR-4 類似的自動載入規則
 */

class Autoloader
{
    private static array $namespaces = [];
    
    /**
     * 註冊自動載入器
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }
    
    /**
     * 添加命名空間對應
     */
    public static function addNamespace(string $namespace, string $baseDir): void
    {
        self::$namespaces[$namespace] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
    }
    
    /**
     * 載入類別
     */
    public static function load(string $class): void
    {
        // 檢查命名空間對應
        foreach (self::$namespaces as $namespace => $baseDir) {
            if (strpos($class, $namespace) === 0) {
                $relativeClass = substr($class, strlen($namespace));
                $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                
                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
        }
        
        // 預設路徑（無命名空間的類別）
        $basePaths = [
            __DIR__ . '/',                         // core/
            __DIR__ . '/../app/Controllers/',       // Controllers
            __DIR__ . '/../app/Controllers/Api/',   // API Controllers
            __DIR__ . '/../app/Models/',            // Models
            __DIR__ . '/../app/Services/',          // Services
        ];
        
        foreach ($basePaths as $basePath) {
            $file = $basePath . $class . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
}

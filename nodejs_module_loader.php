<?php
/**
 * Node.js-compatible ES Module Loader for V8Js
 * 
 * Resolves modules using Node.js resolution algorithm:
 * 1. Relative paths (./module, ../module)
 * 2. node_modules lookup (walks up directory tree)
 * 3. Supports .js, .mjs extensions and package.json "module" field
 */
class NodeJsModuleLoader
{
    private $basePath;
    private $cache = [];
    
    public function __construct($basePath = null)
    {
        $this->basePath = $basePath ?: getcwd();
    }
    
    /**
     * Module loader callback for V8Js
     */
    public function load($modulePath)
    {
        // Module path is already resolved by V8Js using our resolver
        $resolvedPath = $modulePath;
        
        // Check if file exists
        if (!is_file($resolvedPath)) {
            throw new Exception("Cannot find module '$modulePath'");
        }
        
        // Check cache
        if (isset($this->cache[$resolvedPath])) {
            return $this->cache[$resolvedPath];
        }
        
        // Load file
        $code = file_get_contents($resolvedPath);
        if ($code === false) {
            throw new Exception("Failed to read module: $resolvedPath");
        }
        
        // Cache and return
        $this->cache[$resolvedPath] = $code;
        return $code;
    }
    
    /**
     * Module resolver callback for V8Js
     * Signature: function(string $referrerBase, string $specifier): string
     */
    public function resolve($referrerBase, $specifier)
    {
        // Determine search base - referrerBase is already the directory path
        $searchBase = $referrerBase ?: $this->basePath;
        
        // Handle relative paths
        if ($this->isRelativePath($specifier)) {
            $resolved = $this->resolveRelative($specifier, $searchBase);
            return $resolved ?: $specifier; // Return original if not found
        }
        
        // Handle absolute paths
        if ($this->isAbsolutePath($specifier)) {
            $resolved = $this->resolveFile($specifier);
            return $resolved ?: $specifier;
        }
        
        // Handle node_modules lookup
        $resolved = $this->resolveNodeModules($specifier, $searchBase);
        return $resolved ?: $specifier;
    }
    
    private function isRelativePath($path)
    {
        if (!$path) return false;
        return strpos($path, './') === 0 || strpos($path, '../') === 0;
    }
    
    private function isAbsolutePath($path)
    {
        if (!$path) return false;
        return strpos($path, '/') === 0 || preg_match('/^[a-zA-Z]:/', $path);
    }
    
    private function resolveRelative($modulePath, $basePath)
    {
        $fullPath = realpath($basePath . '/' . $modulePath);
        if ($fullPath) {
            return $this->resolveFile($fullPath);
        }
        return null;
    }
    
    private function resolveFile($path)
    {
        // Try exact path
        if (is_file($path)) {
            return realpath($path);
        }
        
        // Try with extensions
        $extensions = ['.js', '.mjs', '.cjs'];
        foreach ($extensions as $ext) {
            if (is_file($path . $ext)) {
                return realpath($path . $ext);
            }
        }
        
        // Try index files
        if (is_dir($path)) {
            foreach ($extensions as $ext) {
                if (is_file($path . '/index' . $ext)) {
                    return realpath($path . '/index' . $ext);
                }
            }
        }
        
        return null;
    }
    
    private function resolveNodeModules($moduleName, $startPath)
    {
        $currentPath = realpath($startPath);
        
        while ($currentPath && $currentPath !== '/') {
            $nodeModulesPath = $currentPath . '/node_modules/' . $moduleName;
            
            // Check if module exists
            $resolved = $this->resolvePackage($nodeModulesPath);
            if ($resolved) {
                return $resolved;
            }
            
            // Move up one directory
            $parentPath = dirname($currentPath);
            if ($parentPath === $currentPath) {
                break;
            }
            $currentPath = $parentPath;
        }
        
        return null;
    }
    
    private function resolvePackage($packagePath)
    {
        // Try package.json
        $packageJsonPath = $packagePath . '/package.json';
        if (is_file($packageJsonPath)) {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            
            // Try "module" field (ES modules)
            if (isset($packageJson['module'])) {
                $modulePath = $packagePath . '/' . $packageJson['module'];
                if (is_file($modulePath)) {
                    return realpath($modulePath);
                }
            }
            
            // Try "main" field
            if (isset($packageJson['main'])) {
                $mainPath = $packagePath . '/' . $packageJson['main'];
                if (is_file($mainPath)) {
                    return realpath($mainPath);
                }
            }
        }
        
        // Try as regular file/directory
        return $this->resolveFile($packagePath);
    }
    
    private function getFullPath($path)
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }
        return $this->basePath . '/' . $path;
    }
    
    /**
     * Get loader and resolver callbacks for V8Js
     */
    public function getCallbacks()
    {
        return [
            'loader' => [$this, 'load'],
            'resolver' => [$this, 'resolve']
        ];
    }
}

// Example usage:
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "Node.js-style ES Module Loader\n\n";
    
    // Create loader with base path
    $loader = new NodeJsModuleLoader(__DIR__);
    
    // Use with V8Js
    $v8 = new V8Js();
    
    $callbacks = $loader->getCallbacks();
    $v8->setModuleLoader($callbacks['loader']);
    $v8->setModuleResolver($callbacks['resolver']);
    
    echo "Example: Load a module from node_modules\n";
    echo "----------------------------------------\n";
    
    try {
        // This would load from node_modules/lodash if it exists
        $result = $v8->executeModule('
            // Relative import
            // import { helper } from "./utils/helper.js";
            
            // node_modules import
            // import _ from "lodash";
            
            export const message = "Node.js module loader ready!";
        ', 'main');
        
        echo "Success! Module loader configured.\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

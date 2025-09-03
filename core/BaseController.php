<?php
namespace Core;

abstract class BaseController {
    protected $container;

    /**
     * BaseController receives the container (the dependency package).
     */
    public function __construct($container) {
        $this->container = $container;
    }

    /**
     * Every controller implements a handle() method.
     *
     * Now the handle() method receives two parameters:
     *   - $request: a package of routing information (including, for example, the current route and any parameters).
     */
    abstract public function handle($request);

    /**
     * Helper to attempt pretty route matching and delegate handling.
     *
     * Example:
     *   Suppose $request is an array with at least a 'route' key.
     *   if ($this->callRoute('/hello/{name}', [$this, 'hello'], $request)) {
     *       return;
     *   }
     *
     * If the target returns false the caller can continue processing.
     *
     * @param string $pattern The route pattern using placeholders {var}.
     * @param callable|string|array $target A callable, class name, or [object, method] target.
     * @param array $request A package of routing information. It must include a 'route' key that holds the current route.
     * @return bool True if handled; otherwise false.
     */
    protected function delegateRoute($pattern, $target, $request) {
        // Check for route key in request
        if (!isset($request['subroute'])) {
            if (!isset($request['route'])) {
                throw new \Exception("Routing package must include a 'route' or 'subroute' key.");
            }
            $request['subroute'] = $request['route'];
        }
        
        $regex = $this->patternToRegex($pattern);
        
        if (preg_match($regex, $request['subroute'], $matches)) {
            // Remove numeric keys; keep only named parameters.
            $routeParams = array_filter($matches, function($key){
                return !is_int($key);
            }, ARRAY_FILTER_USE_KEY);

            $oldSubroute = $request['subroute'];
            $request['subroute'] = $routeParams['subroute'] ?? '';
            if(isset($routeParams['subroute'])) unset($routeParams['subroute']);

            // Merge the extracted parameters into the routing package
            if (!isset($request['params'])) {
                $request['params'] = [];
            }
            $request['params'] = array_merge($request['params'], $routeParams);

            // Decide how to invoke the target
            if (is_callable($target)) {
                // If the target is a callable directly, call it.
                $result = call_user_func($target, $request);
            } elseif (is_string($target)) {
                // If the target is a class name, instantiate it using the container and call its handle method.
                $controller = $this->container->get($target);
                $result = call_user_func([$controller, 'handle'], $request);
            } elseif (is_array($target) && count($target) === 2 && is_callable($target)) {
                // If the target is an array [object, method], call the method.
                $result = call_user_func($target, $request);
            } else {
                throw new \Exception("Invalid target provided to delegateRoute.");
            }
            
            if ($result !== false) {
                exit;
            }

            $request['subroute'] = $oldSubroute;
        }
        
        return false;
    }

    /**
     * Convert a route pattern with placeholders to a regular expression.
     *
     * This method now works just as before; the remaining routing information is
     * merged into the routing package by delegateRoute().
     *
     * @param string $pattern The route pattern (which may optionally end in '!' for a final match).
     * @return string A regex pattern.
     */
    protected function patternToRegex($pattern) {
        // Check if an explicit stop marker is present.
        $isFinal = false;
        if (substr($pattern, -1) === '!') {
            $isFinal = true;
            // Remove the explicit marker from the pattern.
            $pattern = substr($pattern, 0, -1);
        }
        
        // Remove any trailing slashes.
        $pattern = rtrim($pattern, '/');
        
        // Convert route variables of the form "{name}" to named capturing groups.
        $regex = preg_replace_callback('/\{([^}]+)\}/', function($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $pattern);
        
        if (!$isFinal) {
            $regex .= '(?P<subroute>/.*)?';
        }
        
        // The pattern ends with an optional slash, and we add the case-insensitive flag (i).
        return '@^' . $regex . '/?$@iD';
    }
}
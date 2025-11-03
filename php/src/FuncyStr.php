<?php

namespace FuncyStr;

/**
 * FuncyStr - A utility class for processing strings with embedded function calls.
 *
 * Replaces patterns like {{functionName|arg1|arg2}} with the result of
 * calling the specified function.
 *
 * @author Moriel Schottlender <mooeypoo@gmail.com>
 * @license MIT
 */
class FuncyStr
{
    private array $funcs = [];
    private string $regexlookup = '/{{([^{}\v]+)}}/';

    /**
     * Creates a new FuncyStr instance.
     *
     * @param array $funcs An associative array mapping function names to their implementations (callables).
     * @param array $config Configuration options.
     * @param string|null $config['regexlookup'] Custom regex pattern for matching function calls.
     */
    public function __construct(array $funcs = [], array $config = [])
    {
        $this->setFuncs($funcs);
        if (isset($config['regexlookup']) && is_string($config['regexlookup'])) {
            $this->regexlookup = $config['regexlookup'];
        }
    }

    /**
     * Adds a single function to the function registry.
     *
     * @param string $name The name of the function to add.
     * @param callable $func The function implementation to add.
     * @throws \InvalidArgumentException If func is not a callable.
     */
    public function addFunc(string $name, callable $func): void
    {
        if (!is_callable($func)) {
            throw new \InvalidArgumentException('func must be a callable');
        }
        $this->funcs[strtolower($name)] = $func;
    }

    /**
     * Sets multiple functions in the function registry.
     *
     * @param array $funcs An associative array mapping function names to their implementations (callables).
     * @throws \InvalidArgumentException If funcs is not an array.
     */
    public function setFuncs(array $funcs): void
    {
        // Check if all values in the array are callables
        foreach ($funcs as $name => $func) {
            $this->addFunc($name, $func);
        }
    }

    /**
     * Retrieves a function from the registry by name.
     *
     * @param string $name The name of the function to retrieve.
     * @return callable|null The function implementation or null if not found.
     */
    public function getFunc(string $name): ?callable
    {
        return $this->funcs[strtolower($name)] ?? null;
    }

    /**
     * Executes a registered function with the given arguments.
     * Supports both synchronous and asynchronous functions.
     *
     * @param string $name The name of the function to run.
     * @param array $args Arguments to pass to the function.
     * @param mixed $params Context object passed as the first parameter to the function.
     * @return mixed The result of the function call.
     */
    private function runFunc(string $name, array $args, $params)
    {
        $name = strtolower($name);
        $func = $this->getFunc($name);
        
        if ($func === null) {
            return null;
        }

        // Call the function with params as first argument, followed by args
        $result = call_user_func($func, $params, ...$args);

        // Handle async operations (Generator)
        // Note: For Promise support (ReactPHP), you would need to implement
        // proper async/await handling. Generators can be used for coroutines.
        if ($result instanceof \Generator) {
            // If it's a generator, iterate it to completion to get the return value
            while ($result->valid()) {
                $result->next();
            }
            try {
                $result = $result->getReturn();
            } catch (\Exception $e) {
                // Generator didn't return a value, return null
                $result = null;
            }
        }

        return $result;
    }

    /**
     * Evaluates a function call embedded in a string.
     *
     * @param string $str The string containing the function call.
     * @param mixed $params Context object passed to the function.
     * @return string The result of the function call or a placeholder if the function doesn't exist.
     */
    private function evaluateFunction(string $str, $params): string
    {
        if (preg_match($this->regexlookup, $str, $matches) !== 1) {
            return $str;
        }

        $funcString = $matches[1];
        
        // If there are nested functions, resolve them first
        if (strpos($funcString, '{{') !== false) {
            $processed = $this->process($funcString, $params);
            return $processed;
        }

        $parts = explode('|', $funcString);
        $funcName = $parts[0];
        $args = array_slice($parts, 1);

        // Call the corresponding function from the function map
        $lowerName = strtolower($funcName);
        if (isset($this->funcs[$lowerName])) {
            $funcResult = $this->runFunc($funcName, $args, $params);

            // Convert result to string if needed
            $funcResultStr = $this->convertToString($funcResult);

            // Handle null or empty results
            if ($funcResultStr === null || $funcResultStr === '') {
                return $this->replaceWithPlaceholders($funcResultStr ?? '');
            }

            // If the internal result of the function passes the regex
            // lookup, we want to keep it so it can then be processed.
            if (preg_match($this->regexlookup, $funcResultStr)) {
                return $funcResultStr;
            }
            
            // ...But if it doesn't, we need to replace { and } with placeholders
            // so we avoid having unbalanced brackets in the string.
            return $this->replaceWithPlaceholders($funcResultStr);
        }

        // If function isn't found, we want to return the original
        // however, if we do that, the loop looking for {{...}} will
        // never end. So, we cheat here. If there is no match, instead
        // of returning the original, we will replace {{ }} with another
        // set of symbols; then, at the completion of the entire process,
        // we will replace those symbols with the original {{ }}.
        // This way, we can break the loop and return the original
        // matches when the function isn't recognized.
        return $this->replaceWithPlaceholders($matches[0]); // $matches[0] is the entire match including {{ and }}
    }

    /**
     * Converts a value to string, handling null and other types.
     *
     * @param mixed $value The value to convert.
     * @return string|null The string representation or null.
     */
    private function convertToString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        return (string) $value;
    }

    /**
     * Replaces brackets and pipes with placeholders to prevent parsing conflicts.
     *
     * @param string|null $str The string to process.
     * @param bool $replacePipes Whether to replace pipes (currently unused, kept for compatibility).
     * @return string|null The processed string or null.
     */
    private function replaceWithPlaceholders(?string $str, bool $replacePipes = true): ?string
    {
        if ($str === null || $str === '') {
            return $str;
        }
        
        // Replace single brackets and pipes with a placeholder
        return str_replace(
            ['{', '}', '|'],
            ['%%open%%brack%%', '%%close%%brack%%', '%%pipe%%'],
            $str
        );
    }

    /**
     * Replaces single brackets only with placeholders.
     *
     * @param string|null $str The string to process.
     * @return string|null The processed string or null.
     */
    private function replaceSingleBrackets(?string $str): ?string
    {
        if ($str === null || $str === '') {
            return $str;
        }
        
        // Replace single brackets only with a placeholder
        return str_replace(
            ['{', '}'],
            ['%%open%%brack%%', '%%close%%brack%%'],
            $str
        );
    }

    /**
     * Restores placeholders back to original brackets and pipes.
     *
     * @param string $str The string to restore.
     * @return string The restored string.
     */
    private function restorePlaceholders(string $str): string
    {
        // Restore single brackets from the placeholder regardless of case
        $str = preg_replace('/%%open%%brack%%/i', '{', $str);
        $str = preg_replace('/%%close%%brack%%/i', '}', $str);
        $str = preg_replace('/%%pipe%%/i', '|', $str);
        
        return $str;
    }

    /**
     * Processes a string by evaluating all function calls within it.
     * Recursively processes nested function calls.
     * Supports both synchronous and asynchronous function operations.
     *
     * @param string $str The string to process.
     * @param mixed $params Context object passed to all functions.
     * @return string The processed string with all function calls evaluated.
     */
    public function process(string $str, $params = []): string
    {
        // Prepare the string by replacing single brackets
        // that are not part of a pair
        $str = preg_replace_callback(
            '/(?:[^{])({)(?:[^{])/',
            function ($matches) {
                return $this->replaceSingleBrackets($matches[0]);
            },
            $str
        );
        
        $str = preg_replace_callback(
            '/(?:[^}])(})(?:[^}])/',
            function ($matches) {
                return $this->replaceSingleBrackets($matches[0]);
            },
            $str
        );

        // Replace all functions in the string recursively
        while (preg_match($this->regexlookup, $str)) {
            $newstr = $this->evaluateFunction($str, $params);
            $str = preg_replace($this->regexlookup, $newstr, $str, 1);
        }

        // Replace the temporary markers with the original {{ }} and return
        return $this->restorePlaceholders($str);
    }
}


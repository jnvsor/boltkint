<?php

namespace Bolt\Extension\jnvsor\boltkint;

/**
 * @author Jonathan Vollebregt <jnvsor@gmail.com>
 */
class Extension extends \Bolt\BaseExtension
{
    public function getName()
    {
        return "Bolt Kint";
    }

    public function initialize()
    {
        \Kint::enabled($this->app['config']->get('general/debug', false));

        foreach (['d', 'dd', 'ddd', 's', 'sd'] as $func) {
            $this->addTwigFunction($func, 'debug', ['is_safe' => ['html']]);
        }

        \Kint::$aliases['methods'][] = [strtolower(get_class($this)), 'debug'];
    }

    private function setup(callable $func)
    {
        $restore = [
            'die' => in_array($func, ['dd', 'ddd', 'sd']),
            'mode' => \Kint::enabled(),
        ];

        if (in_array($func, ['s', 'sd'])) {
            \Kint::enabled(PHP_SAPI === 'cli' ? \Kint::MODE_WHITESPACE : \Kint::MODE_PLAIN);
        }

        return $restore;
    }

    private function teardown(array $restore)
    {
        if ($restore['die']) {
            exit;
        }

        \Kint::enabled($restore['mode']);
    }

    private function dump(callable $func, array $args)
    {
        ob_start();

        $restore = $this->setup($func);
        $out = call_user_func_array(['\Kint', 'dump'], $args);
        $this->teardown($restore);

        if ($out === '') {
            $out = ob_get_clean();
        } else {
            ob_clean();
        }

        return $out;
    }

    private function trace(callable $func)
    {
        $trace = debug_backtrace(true);
        $trace = array_slice($trace, 2);

        $trace[0]['args'] = [$func, 1];

        ob_start();

        $restore = $this->setup($func);
        $out = \Kint::trace($trace);
        $this->teardown($restore);

        if ($out === '') {
            $out = ob_get_clean();
        } else {
            ob_clean();
        }

        return $out;
    }

    /**
     * Gets information from the calling template
     *
     * @return array Calling function, parameters are trace, arguments
     */
    private function getInfo(array $args)
    {
        $trace = debug_backtrace(true, 4);

        if (isset($trace[3]['object'])) {
            // Read the file from the cache to determine calling method and arguments
            if (is_readable($trace[2]['file'])) {
                $txt = file_get_contents($trace[2]['file']);
            }

            // If it's not cached, reproduce the source by compiling it
            else {
                $env = $trace[3]['object']->getEnvironment();
                $tpl = $trace[3]['object']->getTemplateName();
                $txt = $env->compileSource($env->getLoader()->getSource($tpl), $tpl);
            }

            // Get the line in question, and parse it for the called function and arguments
            $line = explode("\n", $txt)[$trace[2]['line'] - 1];
            $matches = [];
            preg_match('/echo call_user_func_array\(\\$this->env->getFunction\(\'([^\']+)\'\)->getCallable\(\), array\(([^\)]*)/', $line, $matches);

            if ($matches) {
                return [$matches[1], $args === [1] && $matches[2] === '1', $args];
            }

        }

        return ['d', false, array_merge(["Error: Could not determine calling function signature"], $args)];
    }

    public function debug()
    {
        list($func, $trace, $args) = $this->getInfo(func_get_args());

        if ($trace) {
            return $this->trace($func);
        } else {
            return $this->dump($func, $args);
        }

    }
}

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
            $this->addTwigFunction($func, 'debug', [
                'is_safe' => ['html'],
                'needs_environment' => true,
                'is_variadic' => true,
            ]);
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
     * @param TwigEnvironment $env The twig environment
     * @param array $args The arguments
     *
     * @return array Calling function, parameters are trace
     */
    private function getInfo(\Twig_Environment $env, array $args)
    {
        list($callframe, $templateframe) = array_slice(debug_backtrace(true, 4), 2);

        if (!isset($callframe['line'], $callframe['file'], $templateframe['object'])) {
            return;
        }

        $file = $callframe['file'];
        $line = $callframe['line'] - 1;
        $template = $templateframe['object'];

        if (is_readable($file)) {
            // Read the file from the cache to determine calling method and arguments
            $text = file_get_contents($file);
        } else {
            // If it's not cached, reproduce the source by compiling it
            $template_name = $template->getTemplateName();
            $text = $env->compileSource($env->getLoader()->getSource($template_name), $template_name);
        }

        // Get the line in question, and parse it for the called function and arguments
        $matches = [];
        preg_match(
            '/->getFunction\(\'([^\']+)\'\)->getCallable\(\), array\(\$this->env, array\(0 => ([^\)]*)\)/',
            explode("\n", $text)[$line],
            $matches
        );

        if ($matches) {
            return [$matches[1], $args === [1] && $matches[2] === '1'];
        }
    }

    public function debug(\Twig_Environment $env, array $args = [])
    {
        list($func, $trace) = $this->getInfo($env, $args);

        if (empty($func)) {
            $func = 'd';
            array_unshift($args, "Error: Could not determine calling function signature");
        }

        if ($trace) {
            return $this->trace($func);
        } else {
            return $this->dump($func, $args);
        }
    }
}

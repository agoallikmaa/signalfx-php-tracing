<?php

namespace DDTrace\Tests\Composer;

use DDTrace\Tests\Common\TracerTestTrait;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;
use DDTrace\Tests\Common\BaseTestCase;
use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\SpanAssertionTrait;
use PHPUnit\Framework\TestCase;

class ComposerInteroperabilityTest extends BaseTestCase
{
    use TracerTestTrait;
    use SpanAssertionTrait;

    public static function ddSetUpBeforeClass()
    {
        parent::ddSetUpBeforeClass();
        self::composerUpdateScenario(__DIR__ . '/app');
    }

    protected function ddSetUp()
    {
        parent::ddSetUp();
        if (\file_exists($this->getPreloadTouchFilePath())) {
            \unlink($this->getPreloadTouchFilePath());
        }
    }

    public function testComposerInteroperabilityWhenNoInitHook()
    {
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/'));
                TestCase::assertSame("OK - preload:''", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => 'do_not_exists',
            ]
        );

        $this->assertEmpty($traces);
    }

    public function testComposerInteroperabilityWhenInitHookWorks()
    {
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/'));
                TestCase::assertSame("OK - preload:''", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
            ]
        );

        $this->assertNotEmpty($traces);
    }

    /**
     * Simulates an autoloading scenario when preloading and composer are used. Manual tracing is not done, but DDTrace
     * classes are not used in the preloading script.
     */
    public function testPreloadDDTraceNotUsedNoManualTracing()
    {
        if (PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('opcache.preload is not available before PHP 7.4');
        }
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/no-manual-tracing'));
                TestCase::assertSame("OK - preload:'DDTrace classes NOT used in preload'", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
                'zend_extension' => 'opcache.so',
                'opcache.preload' => __DIR__ . '/app/preload.no.ddtrace.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /no-manual-tracing')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/no-manual-tracing',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ]),
        ]);
    }

    /**
     * Simulates an autoloading scenario when preloading and composer are used. Manual tracing is done, but DDTrace
     * classes are not used in the preloading script.
     */
    public function testPreloadDDTraceNotUsedManualTracing()
    {
        if (PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('opcache.preload is not available before PHP 7.4');
        }
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/manual-tracing'));
                TestCase::assertSame("OK - preload:'DDTrace classes NOT used in preload'", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
                'zend_extension' => 'opcache.so',
                'opcache.preload' => __DIR__ . '/app/preload.no.ddtrace.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /manual-tracing')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/manual-tracing',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ])
                ->withChildren([
                    SpanAssertion::build('my_operation', 'web.request', '', 'my_resource')
                        ->withExactTags([
                            'http.method' => 'GET',
                            'component' => 'web.request',
                        ]),
                ]),
        ]);
    }

    /**
     * Simulates an autoloading scenario when preloading and composer are used. Moreover, DDTrace classes are
     * referenced in the preloading script, but no manual tracing is performed.
     */
    public function testPreloadDDTraceUsedNoManualTracing()
    {
        $this->markTestSkipped('skip, depends on internal span impl');
        if (PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('opcache.preload is not available before PHP 7.4');
        }
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/no-manual-tracing'));
                TestCase::assertSame("OK - preload:'DDTrace classes USED in preload'", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
                'zend_extension' => 'opcache.so',
                'opcache.preload' => __DIR__ . '/app/preload.ddtrace.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /no-manual-tracing')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/no-manual-tracing',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ]),
        ]);
    }

    /**
     * Simulates an autoloading scenario when preloading and composer are used. Moreover, DDTrace classes are referenced
     * in the preloading script.
     */
    public function testPreloadDDTraceUsedManualTracing()
    {
        $this->markTestSkipped('skip, depends on internal span impl');
        if (PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('opcache.preload is not available before PHP 7.4');
        }
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/manual-tracing'));
                TestCase::assertSame("OK - preload:'DDTrace classes USED in preload'", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
                'zend_extension' => 'opcache.so',
                'opcache.preload' => __DIR__ . '/app/preload.ddtrace.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /manual-tracing')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/manual-tracing',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ])
                ->withChildren([
                    SpanAssertion::build('my_operation', 'unnamed-php-service', 'memcached', 'my_resource')
                        ->withExactTags([
                            'http.method' => 'GET',
                        ]),
                ]),
        ]);
    }

    /**
     * Simulates the basic scenario when neither preloading nor manual tracing are used.
     */
    public function testNoPreloadNoManualTracing()
    {
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/no-manual-tracing'));
                TestCase::assertSame("OK - preload:''", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /no-manual-tracing')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/no-manual-tracing',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ]),
        ]);
    }

    /**
     * Simulates an autoloading scenario when there is no composer and no preloading is used.
     */
    public function testNoComposerNoPreload()
    {
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/no-composer'));
                TestCase::assertSame("OK - preload:''", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /no-composer')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/no-composer',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ]),
        ]);
    }

    /**
     * Simulates an autoloading scenario when there is no composer and preloading is used.
     */
    public function testNoComposerYesPreload()
    {
        if (PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('opcache.preload is not available before PHP 7.4');
        }
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));
        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/no-composer'));
                TestCase::assertSame("OK - preload:'DDTrace classes NOT used in preload'", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
                'zend_extension' => 'opcache.so',
                'opcache.preload' => __DIR__ . '/app/preload.no.ddtrace.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /no-composer')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/no-composer',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ]),
        ]);
    }

    /**
     * Simulates an autoloading scenario when there is preloading but no composer, in addition to a 'terminal'
     * autoloader, meaning that the autoloader fails if the class is not found.
     */
    public function testNoComposerYesPreloadAutoloadFailing()
    {
        if (PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('opcache.preload is not available before PHP 7.4');
        }
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));

        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/no-composer-autoload-fails'));
                TestCase::assertSame("OK - preload:'DDTrace classes NOT used in preload'", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
                'zend_extension' => 'opcache.so',
                'opcache.preload' => __DIR__ . '/app/preload.no.ddtrace.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /no-composer-autoload-fails')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/no-composer-autoload-fails',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ]),
        ]);
    }

    /**
     * Simulates an autoloading scenario when there is preloading + composer in addition to a 'terminal' autoloader,
     * meaning that the autoloader fails if the class is not found.
     */
    public function testYesComposerYesPreloadAutoloadFailing()
    {
        if (PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('opcache.preload is not available before PHP 7.4');
        }
        $this->assertFalse(file_exists($this->getPreloadTouchFilePath()));

        $traces = $this->inWebServer(
            function ($execute) {
                $output = $execute(GetSpec::create('default', '/composer-autoload-fails'));
                TestCase::assertSame("OK - preload:'DDTrace classes NOT used in preload'", $output);
            },
            __DIR__ . "/app/index.php",
            [],
            [
                'ddtrace.request_init_hook' => __DIR__ . '/../../bridge/dd_wrap_autoloader.php',
                'zend_extension' => 'opcache.so',
                'opcache.preload' => __DIR__ . '/app/preload.no.ddtrace.php',
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'unnamed-php-service', SpanAssertion::NOT_TESTED, 'GET /composer-autoload-fails')
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/composer-autoload-fails',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ]),
        ]);
    }

    /**
     * Given a path to a composer working directory, this method runs composer update in it.
     *
     * @param string $workingDir
     */
    private static function composerUpdateScenario($workingDir)
    {
        exec(
            "composer --working-dir='$workingDir' update -q",
            $output,
            $return
        );
        if (0 !== $return) {
            self::fail('Error while preparing the env: ' . implode("", $output));
        }
    }

    /**
     * Returns the path to a file used to inspect whether opcache preloading was executed.
     * @return string
     */
    private function getPreloadTouchFilePath()
    {
        return __DIR__ . '/app/touch.preload';
    }
}

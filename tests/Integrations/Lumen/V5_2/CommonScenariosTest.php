<?php

namespace DDTrace\Tests\Integrations\Lumen\V5_2;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Lumen/Version_5_2/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'lumen_test_app',
        ]);
    }

    /**
     * @dataProvider provideSpecs
     * @param RequestSpec $spec
     * @param array $spanExpectations
     * @throws \Exception
     */
    public function testScenario(RequestSpec $spec, array $spanExpectations)
    {
        $traces = $this->tracesFromWebRequest(function () use ($spec) {
            $this->call($spec);
        });

        $this->assertFlameGraph($traces, $spanExpectations);
    }

    public function provideSpecs()
    {
        return $this->buildDataProvider(
            [
                'A simple GET request returning a string' => $this->getSimpleTrace(),
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\ExampleController@simpleView',
                        'lumen_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET App\Http\Controllers\ExampleController@simpleView'
                    )->withExactTags([
                        'lumen.route.action' => 'App\Http\Controllers\ExampleController@simpleView',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple_view',
                        'http.status_code' => '200',
                        'component' => 'lumen',
                    ])->withChildren([
                        SpanAssertion::exists('Laravel\Lumen\Application.handleFoundRoute')
                        ->withExactTags(['component' => 'laravel'])
                        ->withChildren([
                            SpanAssertion::exists('laravel.view.render')
                            ->withExactTags(['component' => 'laravel'])
                            ->withChildren([
                                SpanAssertion::build(
                                    'lumen.view',
                                    'lumen_test_app',
                                    SpanAssertion::NOT_TESTED,
                                    '*/resources/views/simple_view.blade.php'
                                )->withExactTags(['component' => 'laravel']),
                                SpanAssertion::build(
                                    'laravel.event.handle',
                                    'lumen_test_app',
                                    SpanAssertion::NOT_TESTED,
                                    'composing: simple_view'
                                )->withExactTags(['component' => 'laravel']),
                            ]),
                            SpanAssertion::build(
                                'laravel.event.handle',
                                'lumen_test_app',
                                SpanAssertion::NOT_TESTED,
                                'creating: simple_view'
                            )->withExactTags(['component' => 'laravel']),
                        ])
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\ExampleController@error',
                        'lumen_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET App\Http\Controllers\ExampleController@error'
                    )->withExactTags([
                        'lumen.route.action' => 'App\Http\Controllers\ExampleController@error',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error',
                        'http.status_code' => '500',
                        'component' => 'lumen',
                    ])->withExistingTagsNames(\PHP_MAJOR_VERSION === 5 ? [] : ['sfx.error.stack'])
                    ->setError(
                        \PHP_MAJOR_VERSION === 5 ? 'Internal Server Error' : 'Exception',
                        \PHP_MAJOR_VERSION === 5 ? null : 'Controller error'
                    )->withChildren([
                        SpanAssertion::exists('Laravel\Lumen\Application.handleFoundRoute')
                        ->withExistingTagsNames([
                            'sfx.error.stack'
                        ])->setError('Exception', 'Controller error'),
                        SpanAssertion::exists('Laravel\Lumen\Application.sendExceptionToHandler'),
                    ]),
                ],
            ]
        );
    }

    protected function getSimpleTrace()
    {
        return [
            SpanAssertion::build(
                'App\Http\Controllers\ExampleController@simple',
                'lumen_test_app',
                SpanAssertion::NOT_TESTED,
                'GET simple_route'
            )->withExactTags([
                'lumen.route.name' => 'simple_route',
                'lumen.route.action' => 'App\Http\Controllers\ExampleController@simple',
                'http.method' => 'GET',
                'http.url' => 'http://localhost:9999/simple',
                'http.status_code' => '200',
                'component' => 'lumen',
            ])->withChildren([
                SpanAssertion::exists('Laravel\Lumen\Application.handleFoundRoute'),
            ]),
        ];
    }
}

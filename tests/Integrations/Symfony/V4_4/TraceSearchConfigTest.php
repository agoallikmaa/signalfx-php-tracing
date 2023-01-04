<?php

namespace DDTrace\Tests\Integrations\Symfony\V4_4;

use DDTrace\Tag;
use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class TraceSearchConfigTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Symfony/Version_4_4/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'DD_TRACE_ANALYTICS_ENABLED' => 'true',
            'DD_SYMFONY_ANALYTICS_SAMPLE_RATE' => '0.3',
        ]);
    }

    /**
     * @throws \Exception
     */
    public function testScenario()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $this->call(GetSpec::create('Testing trace analytics config metric', '/simple'));
        });

        $this->assertFlameGraph(
            $traces,
            [
                SpanAssertion::build(
                    'symfony.request',
                    'symfony',
                    'web',
                    'simple'
                )->withExactTags([
                    'symfony.route.action' => 'App\Controller\CommonScenariosController@simpleAction',
                    'symfony.route.name' => 'simple',
                    'http.method' => 'GET',
                    'http.url' => 'http://localhost:9999/simple',
                    'http.status_code' => '200',
                    'component' => 'symfony',
                    Tag::SPAN_KIND => 'server',
                ])->withExactMetrics([
                    '_dd1.sr.eausr' => 0.3,
                    '_sampling_priority_v1' => 1,
                ])->withChildren([
                    SpanAssertion::exists('symfony.kernel.terminate'),
                    SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                        SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                        SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.kernel.request'),
                            SpanAssertion::exists('symfony.kernel.controller'),
                            SpanAssertion::exists('symfony.kernel.controller_arguments'),
                            SpanAssertion::build(
                                'symfony.controller',
                                'symfony',
                                'web',
                                'App\Controller\CommonScenariosController::simpleAction'
                            ),
                            SpanAssertion::exists('symfony.kernel.response')->withChildren([
                                SpanAssertion::exists('symfony.security.authentication.success')
                            ]),
                            SpanAssertion::exists('symfony.kernel.finish_request'),
                        ]),
                    ]),
                ]),
            ]
        );
    }
}

<?php

namespace DDTrace\Tests\Integrations\Laravel\V5_8;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class EloquentSandboxedTest extends EloquentTest
{
    const IS_SANDBOXED = true;

    public function testDestroy()
    {
        $this->connection()->exec("insert into users (id, email) VALUES (1, 'test-user-deleted@email.com')");
        $traces = $this->tracesFromWebRequest(function () {
            $spec  = GetSpec::create('Eloquent destroy', '/eloquent/destroy');
            $this->call($spec);
        });
        $this->assertOneExpectedSpan($traces, SpanAssertion::build(
            'eloquent.destroy',
            '',
            'sql',
            'App\User'
        )->withExactTags([
            'integration.name' => 'eloquent',
        ]));
    }

    public function testRefresh()
    {
        $this->connection()->exec("insert into users (id, email) VALUES (1, 'test-user-deleted@email.com')");
        $traces = $this->tracesFromWebRequest(function () {
            $spec  = GetSpec::create('Eloquent delete', '/eloquent/refresh');
            $this->call($spec);
        });
        $this->assertOneExpectedSpan($traces, SpanAssertion::build(
            'eloquent.refresh',
            '',
            'sql',
            'App\User'
        )->withExactTags([
            'integration.name' => 'eloquent',
        ]));
    }
}

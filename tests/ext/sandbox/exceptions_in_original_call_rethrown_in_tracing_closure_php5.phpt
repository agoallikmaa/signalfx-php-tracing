--TEST--
Exceptions from original call rethrown in tracing closure (PHP 5)
--SKIPIF--
<?php if (PHP_VERSION_ID >= 70000) die('skip PHP 7 tested in separate test'); ?>
--FILE--
<?php
register_shutdown_function(function () {
    array_map(function($span) {
        printf(
            "%s with exception: %s\n",
            $span['name'],
            $span['meta']['sfx.error.message']
        );
    }, dd_trace_serialize_closed_spans());
});

function a(){
    echo "a()\n";
    throw new Exception('Oops!');
}

DDTrace\trace_function('a', function($s, $args, $r, $ex) {
    $s->name = 'a';
    throw $ex;
});

/* Uncaught exceptions in PHP 5 leak the exception object
 * so tests catch the exception */
try {
    a();
    echo "This line should not be run\n";
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
?>
--EXPECT--
a()
Oops!
a with exception: Oops!

<?php

use App\Support\Hooks;

beforeEach(fn () => Hooks::reset());

test('action hook fires registered callbacks', function () {
    $called = false;
    Hooks::addAction('test.action', function () use (&$called) {
        $called = true;
    });

    Hooks::doAction('test.action');

    expect($called)->toBeTrue();
});

test('action hook passes arguments to callbacks', function () {
    $received = null;
    Hooks::addAction('test.action', function ($value) use (&$received) {
        $received = $value;
    });

    Hooks::doAction('test.action', 'hello');

    expect($received)->toBe('hello');
});

test('filter hook transforms value', function () {
    Hooks::addFilter('test.filter', fn ($value) => $value . ' world');

    $result = Hooks::applyFilters('test.filter', 'hello');

    expect($result)->toBe('hello world');
});

test('filter hooks are applied in priority order', function () {
    Hooks::addFilter('test.filter', fn ($v) => $v . 'B', priority: 20);
    Hooks::addFilter('test.filter', fn ($v) => $v . 'A', priority: 10);

    $result = Hooks::applyFilters('test.filter', '');

    expect($result)->toBe('AB');
});

test('multiple actions can be registered for same hook', function () {
    $log = [];
    Hooks::addAction('test.action', function () use (&$log) { $log[] = 1; });
    Hooks::addAction('test.action', function () use (&$log) { $log[] = 2; });

    Hooks::doAction('test.action');

    expect($log)->toBe([1, 2]);
});

test('firing unknown hook does nothing', function () {
    expect(fn () => Hooks::doAction('nonexistent.hook'))->not->toThrow(\Throwable::class);
});

test('reset clears all hooks', function () {
    Hooks::addAction('test.action', fn () => null);
    Hooks::reset();

    $called = false;
    Hooks::doAction('test.action', function () use (&$called) {
        $called = true;
    });

    expect($called)->toBeFalse();
});

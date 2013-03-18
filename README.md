# Hoathis\Lua

This library proposes an interpreter and a compiler for the
[Lua](http://lua.org) language in PHP, based on
[`Hoa\Compiler`](https://github.com/hoaproject/Compiler).

## Quick usage

This is a work in progress, but here is a toy example. The `Input.lua` file:

    a = 39
    b = '3'
    a, b = b, a + 1
    z = 42

    function f ( x, y )

        var_dump(x, y)

        function g ( h )
            var_dump(h)
        end

        z = y / 2

        g(z)
    end

    f(a, b)

And the interpreter:

    <?php

    require '/usr/local/lib/Hoa/Core/Core.php';

    from('Hoa')
    -> import('File.Read')
    -> import('Compiler.Llk.~')
    -> import('Lua.Visitor.Interpreter');

    $compiler = \Hoa\Compiler\Llk::load(
        new \Hoa\File\Read('hoa://Library/Lua/Grammar.pp')
    );
    $input    = (new \Hoa\File\Read('Input.lua'))->readAll();
    $ast      = $compiler->parse($input);
    $visitor  = new \Hoa\Lua\Visitor\Interpreter();

    $visitor->visit($ast);

    /**
     * Will output:
     *     string(1) "3"
     *     int(40)
     *     int(20)
     */

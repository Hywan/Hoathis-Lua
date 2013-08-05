<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2013, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoa')

/**
 * \Hoa\Visitor\Visit
 */
-> import('Visitor.Visit');


from('Hoathis')

/**
 * \Hoathis\Lua\Exception\Interpreter
 */
-> import('Lua.Exception.Interpreter')

/**
 * \Hoathis\Lua\Model\Environment
 */
-> import('Lua.Model.Environment')

/**
 * \Hoathis\Lua\Model\Variable
 */
-> import('Lua.Model.Variable')

/**
 * \Hoathis\Lua\Model\Closure
 */
-> import('Lua.Model.Closure');

}

namespace Hoathis\Lua\Visitor {

/**
 * Class \Hoathis\Lua\Visitor\Interpreter.
 *
 * Interpreter.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2013 Ivan Enderlin.
 * @license    New BSD License
 */

class Interpreter implements \Hoa\Visitor\Visit {

    const AS_SYMBOL = 0;
    const AS_VALUE  = 1;

    protected $_environment = null;


    public function __construct ( ) {

        $this->_environment = new \Hoathis\Lua\Model\Environment('_G');

        return;
    }

    /**
     * Visit an element.
     *
     * @access  public
     * @param   \Hoa\Visitor\Element  $element    Element to visit.
     * @param   mixed                 &$handle    Handle (reference).
     * @param   mixed                 $eldnah     Handle (not reference).
     * @return  float
     */
    public function visit ( \Hoa\Visitor\Element $element,
                            &$handle = null, $eldnah = null ) {

        $type     = $element->getId();
        $children = $element->getChildren();

        switch($type) {

            case '#chunk':
            case '#function_body':
                foreach($children as $child)
                    $child->accept($this, $handle, $eldnah);
              break;

            case '#assignation':
                $count = count($children);

                if(0 !== $count % 2)
                    throw new \Hoathis\Lua\Exception\Interpreter(
                        'Not the same number of symbols and values for the ' .
                        'affection', 1024);

                for($i = $limit = ceil($count / 2); $i < $count; ++$i)
                    $children[$i] = $children[$i]->accept(
                        $this,
                        $handle,
                        self::AS_VALUE
                    );

                for($i = 0; $i < $limit; ++$i) {

                    $symbol = $children[$i]->accept(
                        $this,
                        $handle,
                        $eldnah
                    );
                    $value  = $children[$i + $limit];

                    if(!isset($this->_environment[$symbol]))
                        $this->_environment[$symbol] = new \Hoathis\Lua\Model\Variable(
                            $symbol,
                            $this->_environment
                        );

                    $this->_environment[$symbol]->setValue($value);
                }
              break;

            case '#negative':
                return -($children[0]->accept($this, $handle, self::AS_VALUE));
              break;

            case '#addition':
                $parent = $element->getParent();
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                if(null !== $parent && '#substraction' === $parent->getId())
                    return $child0 - $child1;

                return $child0 + $child1;
              break;

            case '#substraction':
                $parent = $element->getParent();
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                if(   null            !== $parent
                   && '#substraction' === $parent->getId()
                   && $element        === $parent->getChild(1))
                    return $child0 - -$child1;

                return $child0 - $child1;
              break;

            case '#power':
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                return pow($child0, $child1);
              break;

            case '#modulo':
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                return $child0 % $child1;
              break;

            case '#multiplication':
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                return $child0 * $child1;
              break;

            case '#division':
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                if(0 == $child1)
                    throw new \Hoathis\Lua\Exception\Interpreter(
                        'Tried to divide %f by zero, impossible.',
                        0, $child0);

                return $child0 / $child1;
              break;

            case '#function_call':
                $symbol    = $children[0]->accept($this, $handle, $eldnah);
                $arguments = $children[1]->accept($this, $handle, $eldnah);

                if(true === function_exists($symbol))
                    return call_user_func_array($symbol, $arguments);

                $closure = $this->_environment[$symbol];

                if(!($closure instanceof \Hoathis\Lua\Model\Closure))
                    throw new \Hoathis\Lua\Exception\Interpreter(
                        'Symbol %s() is not a function.', 42, $symbol);

                $this->_environment = $closure;
                $out                = $closure->call($arguments, $this);
                $this->_environment = $this->_environment->getParent();

                return $out;
              break;

            case '#arguments':
                foreach($children as &$child)
                    $child = $child->accept($this, $handle, self::AS_VALUE);

                return $children;

            case '#function':
                $symbol     = $children[0]->accept($this, $handle, $eldnah);
                $parameters = $children[1]->accept($this, $handle, $eldnah);
                $body       = $children[2];
                $closure    = new \Hoathis\Lua\Model\Closure(
                    $symbol,
                    $this->_environment,
                    $parameters,
                    $body
                );
                $this->_environment[$symbol] = $closure;
              break;

            case '#parameters':
                foreach($children as &$child)
                    $child = $child->accept($this, $handle, $eldnah);

                return $children;
              break;

            case 'token':
                $token = $element->getValueToken();
                $value = $element->getValueValue();

                switch($token) {

                    case 'identifier':
                        if(self::AS_VALUE === $eldnah)
                            return $this->_environment[$value]->getValue();

                        return $value;

                    case 'number':
                        return floatval($value);

                    case 'string':
                        return trim($value, '\'"');

                    default:
                        throw new \Hoathis\Lua\Exception\Interpreter(
                            'Token %s is not yet implemented.', 1, $token);
                }
              break;

            default:
                throw new \Hoathis\Lua\Exception\Interpreter(
                    '%s is not yet implemented.', 2, $type);
        }
    }

    /**
     *
     */
    public function getRoot ( ) {

        return $this->_environment;
    }
}

}

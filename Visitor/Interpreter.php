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
-> import('Lua.Model.Closure')

/**
 * \Hoathis\Lua\Model\Closure
 */
-> import('Lua.Model.Value');
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
                // Search for the equal position in the child list
                $equalPosition = 0;
                while ($equalPosition < $count && $children[$equalPosition]->getValueToken() != 'equal') {
                    $equalPosition++;
                }

                for ($i = $equalPosition + 1; $i < $count; ++$i)
                    $children[$i] = $children[$i]->accept(
                        $this,
                        $handle,
                        self::AS_VALUE
                    );

                for ($i = 0; $i < $equalPosition; ++$i) {

                    $symbol = $children[$i]->accept(
                        $this,
                        $handle,
                        $eldnah
                    );
                    $value  = $children[$i + $equalPosition + 1];

                    if ($symbol instanceof \Hoathis\Lua\Model\Value) {
                        if ($value instanceof \Hoathis\Lua\Model\Value) {
                            if ($value->isReference()) {
                                //$value->copyAsReferenceTo($symbol);
                                $symbol->setReference($value->getReference());
                            } else {
                                $symbol->setValue($value->getValue());
                            }
                        } else {
                            $symbol->setValue($value);
                        }
                    } else {            // $symbol is an identifier
                        if(!isset($this->_environment[$symbol])) {
                            $this->_environment[$symbol] = new \Hoathis\Lua\Model\Variable(
                                $symbol,
                                $this->_environment
                            );
                        }
                        if ($value instanceof \Hoathis\Lua\Model\Value) {
                            if ($value->isReference()) {
                                //$value->copyAsReferenceTo($this->_environment[$symbol]);
                                $this->_environment[$symbol]->setValue($value->getReference());
                            } else {
                                $this->_environment[$symbol]->setValue($value);
                            }
                        } else {
                            $this->_environment[$symbol]->setValue($value);
                        }
                    }
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
                    return new \Hoathis\Lua\Model\Value($child0->getValue() - $child1->getValue());

                return new \Hoathis\Lua\Model\Value($child0->getValue() + $child1->getValue());
              break;

            case '#substraction':
                $parent = $element->getParent();
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                if(   null            !== $parent
                   && '#substraction' === $parent->getId()
                   && $element        === $parent->getChild(1))
                    return new \Hoathis\Lua\Model\Value($child0->getValue() - -$child1->getValue());

                return new \Hoathis\Lua\Model\Value($child0->getValue() - $child1->getValue());
              break;

            case '#power':
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                return new \Hoathis\Lua\Model\Value(pow($child0->getValue(), $child1->getValue()));
              break;

            case '#modulo':
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                return new \Hoathis\Lua\Model\Value($child0->getValue() % $child1->getValue());
              break;

            case '#multiplication':
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                return new \Hoathis\Lua\Model\Value($child0->getValue() * $child1->getValue());
              break;

            case '#division':
                $child0 = $children[0]->accept($this, $handle, self::AS_VALUE);
                $child1 = $children[1]->accept($this, $handle, self::AS_VALUE);

                if(0 == $child1->getValue())
                    throw new \Hoathis\Lua\Exception\Interpreter(
                        'Tried to divide %f by zero, impossible.',
                        0, $child0->getValue());

                return new \Hoathis\Lua\Model\Value($child0->getValue() / $child1->getValue());
              break;

            case '#function_call':
                $symbol    = $children[0]->accept($this, $handle, $eldnah);
                $arguments = $children[1]->accept($this, $handle, $eldnah);

                if(true === function_exists($symbol)) {
                    $argValues = array();
                    foreach ($arguments as $arg) {
                        $argValues[] = $arg->getPHPValue();
                    }
                   return call_user_func_array($symbol, $argValues);
                }

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

			case '#table':
				$arr = array();
				foreach($children as $child) {
					$field = $child->accept($this, $handle, $eldnah);
					$value = $field['value']->getValue();
					if (true === isset($field['key'])) {
						$key = $field['key'];
						$arr[$key] = $value;
					} else {
						$arr[] = $value;		// @todo what to do with Lua compatibility : first table element start at 1 (unlike 0 in php)
					}
				}
				return new \Hoathis\Lua\Model\Value($arr, \Hoathis\Lua\Model\Value::REFERENCE);
				break;

			case '#field':
				$nbchildren = count($children);

				switch ($nbchildren) {
					case 1:
						return array('value' => $children[0]->accept($this, $handle, $eldnah));
					case 2:
						$nameChild = $children[0]->accept($this, $handle, self::AS_SYMBOL);
						// Test $name must not be 0
						$valueChild = $children[1]->accept($this, $handle, self::AS_VALUE);
						return array('key' => $nameChild, 'value' => new \Hoathis\Lua\Model\Value($valueChild));
						break;
				}
				break;

			case '#table_access':
				if (false === isset($this->_environment[$children[0]->getValueValue()])) {
					throw new \Hoathis\Lua\Exception\Interpreter(
                            'Symbol %s is unknown', 1, $children[0]->getValueValue());
				}

                $var = $this->_environment[$children[0]->getValueValue()]->getValue()->getValue();
                $symbol = $children[0]->getValueValue();
                if (false === is_array($var)) {
					throw new \Hoathis\Lua\Exception\Interpreter(
                            'Symbol %s is not a table', 1, $symbol);
				}
                $nbchildren = count($children);
                for ($i = 1; $i < $nbchildren - 1; $i++) {
                    $symbol .= '.' . $children[$i]->getValueValue();
                    $parentVar = $var[$children[$i]->getValueValue()];
                    $var = $parentVar->getValue();
                    if (false === is_array($var)) {
                    	throw new \Hoathis\Lua\Exception\Interpreter(
                             'Symbol %s is not a table', 1, $symbol);
                    }
                }

                if (false === array_key_exists($children[$i]->getValueValue(), $var)) {
                    if ($eldnah === self::AS_VALUE) {
                        throw new \Hoathis\Lua\Exception\Interpreter(
                             'Unknown symbol %s in table %s', 13, array($children[$i]->getValueValue(), $symbol));
                    } else {
                        $newval = null;
                        $var[$children[$i]->getValueValue()] = new \Hoathis\Lua\Model\Value($newval);
                        $parentVar->setValue($var);
                    }
                }

                return $var[$children[$i]->getValueValue()];

				break;

			case "#function_lambda":
				throw new \Hoathis\Lua\Exception\Interpreter(
                    '%s is not yet implemented.', 2, $type);
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
                        if (intval($value) == $value) {
                            // parse $value string as int
                            $value = intval($value);
                        } else {
                            // parse $value string as float
                            $value = floatval($value);
                        }

                        return new \Hoathis\Lua\Model\Value($value);

                    case 'string':
                        return new \Hoathis\Lua\Model\Value(trim($value, '\'"'));	//@todo attention ca trim trop!

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

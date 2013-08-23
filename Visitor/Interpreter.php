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
        // Temp declaration for debug
        $this->_environment['print'] = new \Hoathis\Lua\Model\Variable('print', $this->_environment);
        $this->_environment['print']->setValue(new \Hoathis\Lua\Model\Value(new \Hoathis\Lua\Model\Closure('print', $this->_environment, array(), function () {
            $args = func_get_args();
            $sep = '';
            foreach ($args as $arg) {
                if (true === is_null($arg)) {
                    echo 'nil';
                } elseif (false === $arg) {
                    echo 'false';
                } elseif (true === is_array($arg)) {
                    echo 'array';
                } else {
                    echo $sep, $arg;
                }
                $sep = "\t";
            }
            echo PHP_EOL;
        })));

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
                foreach($children as $child) {
                    if ('#return' !== $child->getId()) {
                        $child->accept($this, $handle, $eldnah);
                    } else {
                        return $child->accept($this, $handle, $eldnah);
                    }
                }
              break;

            case '#assignation_local':
                $assignation_local = true;
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

                    if ($symbol instanceof \Hoathis\Lua\Model\Value) {      // use for table access
                        if ($value instanceof \Hoathis\Lua\Model\Value) {
                            if ($value->isReference()) {
                                $symbol->setReference($value->getReference());
                            } else {
                                $symbol->setValue($value->getValue());
                            }
                        } else {
                            $symbol->setValue($value);
                        }
                    } else {            // $symbol is an identifier
                        if (isset($assignation_local) && !$this->_environment->localExists($symbol)) {
                            $this->_environment->localSet($symbol, new \Hoathis\Lua\Model\Variable(
                                $symbol,
                                $this->_environment
                            ));
                        } elseif(!isset($this->_environment[$symbol])) {
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
                //print_r($this->_environment->_symbols);
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

            case '#comparison':
                $val1       = $children[0]->accept($this, $handle, self::AS_VALUE);
                $comparison = $children[1]->getValueToken();
                $val2       = $children[2]->accept($this, $handle, self::AS_VALUE);
                switch ($comparison) {
                    case 'dequal':
                        return new \Hoathis\Lua\Model\Value($val1->getValue() === $val2->getValue());
                        break;
                    case 'nequal':
                        return new \Hoathis\Lua\Model\Value($val1->getValue() !== $val2->getValue());
                        break;
                    case 'lt':
                        if (is_numeric($val1->getValue()) && is_numeric($val2->getValue())
                                || is_string($val1->getValue()) && is_string($val2->getValue())) {
                            return new \Hoathis\Lua\Model\Value($val1->getValue() < $val2->getValue());
                        } // TODO must manage when comparing two tables
                        break;
                    case 'gt':
                        if (is_numeric($val1->getValue()) && is_numeric($val2->getValue())
                                || is_string($val1->getValue()) && is_string($val2->getValue())) {
                            return new \Hoathis\Lua\Model\Value($val1->getValue() > $val2->getValue());
                        } // TODO must manage when comparing two tables
                        break;
                    case 'lte':
                        if (is_numeric($val1->getValue()) && is_numeric($val2->getValue())
                                || is_string($val1->getValue()) && is_string($val2->getValue())) {
                            return new \Hoathis\Lua\Model\Value($val1->getValue() <= $val2->getValue());
                        }
                        // TODO must manage when comparing two tables

                        break;
                    case 'gte':
                        if (is_numeric($val1->getValue()) && is_numeric($val2->getValue())
                                || is_string($val1->getValue()) && is_string($val2->getValue())) {
                            return new \Hoathis\Lua\Model\Value($val1->getValue() >= $val2->getValue());
                        } // TODO must manage when comparing two tables

                        break;
                }
                break;

            case '#function_call':
                $symbol    = $children[0]->accept($this, $handle, $eldnah);
                $arguments = $children[1]->accept($this, $handle, $eldnah);

                if ($symbol instanceof \Hoathis\Lua\Model\Value) {
                    $closure = $symbol->getValue();
                } else {
                    if (true === function_exists($symbol)) {
                        $argValues = array();
                        foreach ($arguments as $arg) {
                            $argValues[] = $arg->getPHPValue();
                        }
                       return call_user_func_array($symbol, $argValues);
                    }

                    if (false === isset($this->_environment[$symbol])) {
                        throw new \Hoathis\Lua\Exception\Interpreter(
                            'Unknown symbol %s()', 42, $symbol);
                    }
                    $closure = $this->_environment[$symbol]->getValue()->getValue();
                    if(!($closure instanceof \Hoathis\Lua\Model\Closure))
                        throw new \Hoathis\Lua\Exception\Interpreter(
                            'Symbol %s() is not a function.', 42, $symbol);
                }

                $oldEnvironment = $this->_environment;
                $this->_environment = $closure;
                $out                = $closure->call($arguments, $this);
                $this->_environment = $oldEnvironment;

                return $out;
              break;

            case '#return':
                if (false === empty($children)) {
                    $val = $children[0]->accept($this, $handle, $eldnah);
                    return $val;
                }
                break;

            case '#arguments':
                foreach($children as &$child) {
                    $child = $child->accept($this, $handle, self::AS_VALUE);
                }

                return $children;

            case '#parameters':
                foreach($children as &$child) {
                    $child = $child->accept($this, $handle, $eldnah);
                }

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

            case '#field_val':
			case '#field_name':
				$nbchildren = count($children);

				switch ($nbchildren) {
					case 1:
						return array('value' => $children[0]->accept($this, $handle, $eldnah));
					case 2:
                        if ('#field_val' === $type) {
                            $nameChild = $children[0]->accept($this, $handle, self::AS_VALUE)->getValue();
                        } else {
                            $nameChild = $children[0]->accept($this, $handle, self::AS_SYMBOL);
                        }
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

                $symbol = $children[0]->getValueValue();
                $var = $this->_environment[$symbol]->getValue()->getValue();
                if (false === is_array($var)) {
					throw new \Hoathis\Lua\Exception\Interpreter(
                            'Symbol %s is not a table', 1, $symbol);
				}
                $nbchildren = count($children);
                $sep_ = '.';
                $_sep = '';
                $mode = self::AS_SYMBOL;
                for ($i = 1; $i < $nbchildren - 1; $i++) {
                    if ($children[$i]->getValueToken() === 'bracket_') {
                        $sep_ = '[\'';
                        $_sep = '\']';
                        $mode = self::AS_VALUE;
                    } else {
                        if ($mode === self::AS_VALUE) {
                            $field = $children[$i]->accept($this, $handle, self::AS_VALUE)->getValue();
                        } else {
                            $field = $children[$i]->getValueValue();
                        }
                        $symbol .= $sep_ . $field . $_sep;
                        if (false === array_key_exists($field, $var)) {
                            throw new \Hoathis\Lua\Exception\Interpreter(
                             'attempt to index field \'%s\' (a nil value) in %s', 13, array($field, $symbol));
                        } else {
                            $parentVar = $var[$field];
                            $var = $parentVar->getValue();
                            $sep_ = '.';
                            $_sep = '';
                            $mode = self::AS_SYMBOL;
                            if (false === is_array($var)) {
                                throw new \Hoathis\Lua\Exception\Interpreter(
                                     'Symbol %s is not a table', 1, $symbol);
                            }
                        }
                    }
                }
                if ($mode === self::AS_VALUE) {
                    $field = $children[$i]->accept($this, $handle, self::AS_VALUE)->getValue();
                } else {
                    $field = $children[$i]->getValueValue();
                }
                $symbol .= $sep_ . $field . $_sep;
                if (false === array_key_exists($field, $var)) {
                    if ($eldnah === self::AS_VALUE) {
                        throw new \Hoathis\Lua\Exception\Interpreter(
                             'Unknown symbol %s in table %s', 13, array($field, $symbol));
                    } else {
                        $newval = null;
                        $var[$children[$i]->getValueValue()] = new \Hoathis\Lua\Model\Value($newval);
                        $parentVar->setValue($var);
                    }
                }

                return $var[$field];

				break;

            case '#local_function':
                $local_function = true;
            case '#function':
                $symbol     = reset($children)->accept($this, $handle, $eldnah);
			case "#function_lambda":
                $nbchildren = count($children);
                $body       = $children[$nbchildren-1];
                if ($nbchildren > 2) {
                    $parameters = $children[1]->accept($this, $handle, self::AS_SYMBOL);
                }
                if (false === isset($parameters)) {
                    $parameters = array();
                }
                if (false === isset($symbol)) {
                    $closuresymbol = 'lambda_' . md5(print_r($body, true));
                } else {
                    $closuresymbol = $symbol;
                }
                $closure    = new \Hoathis\Lua\Model\Closure(
                    $closuresymbol,
                    $this->_environment,
                    $parameters,
                    $body
                );
                if (true === isset($symbol)) {         // it's a function declaration with the symbol
                    if (isset($local_function)) {
                        $this->_environment->localSet($symbol, new \Hoathis\Lua\Model\Variable($symbol, $this->_environment));
                    } else {
                        $this->_environment[$symbol] = new \Hoathis\Lua\Model\Variable($symbol, $this->_environment);
                    }
                    $this->_environment[$symbol]->setValue(new \Hoathis\Lua\Model\Value($closure));
                    return $this->_environment[$symbol];
                } else {                // it's a lambda function
                    return new \Hoathis\Lua\Model\Value($closure);//, \Hoathis\Lua\Model\Value::REFERENCE);
                }
				break;

            case '#and':
                $leftVal    = $children[0]->accept($this, $handle, $eldnah);
                if (self::valueAsBool($leftVal->getValue())) {
                    return $children[1]->accept($this, $handle, $eldnah);
                } else {
                    return new \Hoathis\Lua\Model\Value(false);
                }
                break;


            case '#or':
                $leftVal    = $children[0]->accept($this, $handle, $eldnah);
                if (self::valueAsBool($leftVal->getValue())) {
                    return $leftVal;
                } else {
                    return $children[1]->accept($this, $handle, $eldnah);
                }
                break;

            case '#not':
                $val    = $children[0]->accept($this, $handle, $eldnah);
                return new \Hoathis\Lua\Model\Value(!self::valueAsBool($val));
                break;

            case '#if':
                $conditionPos = 0;
                $ifDone = false;
                $nbchildren = count($children);
                // loop for each if/elseif
                while (false === $ifDone) {
                    $conditions = $children[$conditionPos]->accept($this, $handle, $eldnah);
                    for ($elsePos = $conditionPos + 1; $elsePos < $nbchildren - 2 ;) {
                        $token = $children[$elsePos]->getValueToken();
                        if ('else' === $token || 'elseif' === $token) break;
                        $elsePos++;
                    }
                    if (true === self::valueAsBool($conditions->getValue())) {
                        for ($i = $conditionPos + 1; $i < $elsePos; $i++) {
                            $children[$i]->accept($this, $handle, $eldnah);
                        }
                        $ifDone = true;
                    } elseif ('elseif' === $token) {
                        $conditionPos = $elsePos + 1;
                    } else {
                        for ($i = $elsePos+1; $i < $nbchildren; $i++) {
                            $children[$i]->accept($this, $handle, $eldnah);
                        }
                        $ifDone = true;
                    }

                }
                break;

            case 'token':
                $token = $element->getValueToken();
                $value = $element->getValueValue();

                switch($token) {

                    case 'identifier':
                        if(self::AS_VALUE === $eldnah) {
                            return $this->_environment[$value]->getValue();
                        }
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

                    case 'nil':
                        return new \Hoathis\Lua\Model\Value(null);

                    case 'false':
                        return new \Hoathis\Lua\Model\Value(false);

                    case 'true':
                        return new \Hoathis\Lua\Model\Value(true);

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

    public static function valueAsBool($val) {
        if (true === is_null($val) || false === $val) {
            return false;
        } else {
            return true;
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

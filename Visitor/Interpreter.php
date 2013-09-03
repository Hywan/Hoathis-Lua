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
 * \Hoathis\Lua\Model\Value
 */
-> import('Lua.Model.Value')
/**
 * \Hoathis\Lua\Model\ReturnedValue
 */
-> import('Lua.Model.ReturnedValue')
/**
 * \Hoathis\Lua\Model\BreakStatement
 */
-> import('Lua.Model.BreakStatement')
/**
 * \Hoathis\Lua\Model\ValueGroup
 */
-> import('Lua.Model.ValueGroup');
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
                echo $sep;
                if (true === is_null($arg)) {
                    echo 'nil';
                } elseif (false === $arg) {
                    echo 'false';
                } elseif (true === is_array($arg)) {
                    echo 'array';
                } elseif (true === is_callable($arg) || $arg instanceof \Hoathis\Lua\Model\Closure) {
                    echo 'function';
                } else {
                    echo $arg;
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

            case '#ignored':
                break;

            case '#block':
                $oldEnvironment = $this->_environment;
                $data = $element->getData();
                if (false === isset($data['env'])) {
                    $data['env'] = new \Hoathis\Lua\Model\Environment('block', $this->_environment);
                }
                $this->_environment = $data['env'];
                $nbchildren = count($children);
                for ($i = 0; $i < $nbchildren; $i++) {
                    $val = $children[$i]->accept($this, $handle, $eldnah);
                    if ($val instanceof \Hoathis\Lua\Model\ReturnedValue) {
                        $parent = $element->getParent();
                        if (true === is_null($parent)) {
                            $returnedValue = $val->getValue()->getPHPValue();
                        } else {
                            $returnedValue = $val;
                        }
                        break;
                    } elseif ($val instanceof \Hoathis\Lua\Model\BreakStatement) {
                        $parent = $element->getParent();
                        if (true === is_null($parent)) {
                            throw new \Hoathis\Lua\Exception\Interpreter(
                            'Break found outside of loop.', 1);
                        } else {
                            $returnedValue = $val;
                        }
                        break;
                    }
                }
                $this->_environment = $oldEnvironment;
                if (isset($returnedValue)) {
                    return $returnedValue;
                }
                break;

            case '#chunk':
            case '#function_body':
                foreach($children as $child) {
                    $execValue = $child->accept($this, $handle, $eldnah);
                    if ($execValue instanceof \Hoathis\Lua\Model\ReturnedValue) {
                        if ($type === '#chunk') {
                            return $execValue->getValue()->getPHPValue();
                        } else {
                            return $execValue->getValue();
                        }
                    }
                }
              break;

            case '#assignation_local':
                $assignation_local = true;
            case '#assignation':
                if (false === isset($assignation_local)) {
                    $assignation_local = false;
                }
                $count = count($children);
                $leftVar = $children[0]->accept($this, $handle,$eldnah);
                $rightVar = $children[1]->accept($this, $handle,self::AS_VALUE);

                if ($leftVar instanceof \Hoathis\Lua\Model\ValueGroup) {
                    $symbols = $leftVar->getValue();
                } else {
                    $symbols = array($leftVar);
                }

                if ($rightVar instanceof \Hoathis\Lua\Model\ValueGroup) {
                    $values = $rightVar->getValue();
                } else {
                    $values = array($rightVar);
                }
                $this->setValueGroupToValueGroup($symbols, $values, $assignation_local);
                break;

            case '#expression_group':
                $group = new \Hoathis\Lua\Model\ValueGroup(null);
                foreach ($children as $child) {
                    $group->addValue($child->accept($this, $handle,$eldnah));
                }
                return $group;
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
                $symbol    = $children[0]->accept($this, $handle, self::AS_SYMBOL);
                $arguments = $children[1]->accept($this, $handle, self::AS_SYMBOL);

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
                //$this->_environment = $closure;
                $out                = $closure->call($arguments, $this);
                //$this->_environment = $oldEnvironment;

                return $out;
              break;

            case '#return':
                if (false === empty($children)) {
                    $val = $children[0]->accept($this, $handle, self::AS_VALUE);
                    return new \Hoathis\Lua\Model\ReturnedValue($val);
                }
                break;

            case '#arguments':
                if (false === empty($children)) {
                    $children[0] = $children[0]->accept($this, $handle, self::AS_VALUE);
                    if ($children[0] instanceof \Hoathis\Lua\Model\ValueGroup) {
                        $children = $children[0]->getValue();
                    }
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
                $numericIndex = 1;      // this variable is for compatibility between php array and lua table (first numeric index is 1)
				foreach($children as $child) {
					$field = $child->accept($this, $handle, $eldnah);
					$value = $field['value']->getValue();
					if (true === isset($field['key'])) {
						$key = $field['key'];
						$arr[$key] = $value;
					} else {
						$arr[$numericIndex] = $value;
                        $numericIndex++;
					}
				}
                $newVal = new \Hoathis\Lua\Model\Value($arr, \Hoathis\Lua\Model\Value::REFERENCE);
				return $newVal;
				break;

            case '#field_val':
			case '#field_name':
            case '#field':
				$nbchildren = count($children);

				switch ($nbchildren) {
					case 1:
						return array('value' => $children[0]->accept($this, $handle, self::AS_VALUE));
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
                $parentVar = null;
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
                        $var[$field] = null;
                    } else {
                        $newval = null;
                        $var[$field] = new \Hoathis\Lua\Model\Value($newval);
                        if ($parentVar instanceof \Hoathis\Lua\Model\Value) {
                            $parentVar->setValue($var);
                        }
                    }
                }

                return new \Hoathis\Lua\Model\Value($var[$field]);

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
                    $conditions = $children[$conditionPos]->accept($this, $handle, self::AS_VALUE);
                    if (true === self::valueAsBool($conditions->getValue())) {
                        $val = $children[$conditionPos + 1]->accept($this, $handle, $eldnah);
                        if ($val instanceof \Hoathis\Lua\Model\ReturnedValue
                                || $val instanceof \Hoathis\Lua\Model\BreakStatement) {
                            return $val;
                        }
                        $ifDone = true;
                    }
                    if ($conditionPos + 3 < $nbchildren) {      // there is an else or elseif part
                        if ('elseif' === $children[$conditionPos + 2]->getValueToken()) {
                            $conditionPos = $conditionPos + 3;  // condition of elseif
                        } else {            // the else statement
                            $val = $children[$conditionPos + 3]->accept($this, $handle, $eldnah);
                            if ($val instanceof \Hoathis\Lua\Model\ReturnedValue
                                    || $val instanceof \Hoathis\Lua\Model\BreakStatement) {
                                return $val;
                            }
                            $ifDone = true;
                        }
                    } else {        // nothing more to do
                        $ifDone = true;
                    }
                }
                break;

            case '#while_loop':
            case '#do_while_loop':
                $nbchildren = count($children);
                if ('#while_loop' === $type) {
                    $conditionPos = 0;                      // in while_loop condition is at the beginning
                    $firstStmt = 1;
                    $lastStmt = $nbchildren - 1;
                    $condition = $children[$conditionPos]->accept($this, $handle, $eldnah);
                } else {
                    $conditionPos = $nbchildren - 1;        // in do_while_loop condition is at the end
                    $firstStmt = 0;
                    $lastStmt = $nbchildren - 2;
                    $condition = new \Hoathis\Lua\Model\Value(true); // simulate first condition
                }
                $val = null;
                while (true === self::valueAsBool($condition->getValue())
                        && !($val instanceof \Hoathis\Lua\Model\BreakStatement)) {      // break stop the loop
                    for ($i = $firstStmt; $i <= $lastStmt && !($val instanceof \Hoathis\Lua\Model\BreakStatement); $i++) {
                        $val = $children[$i]->accept($this, $handle, $eldnah);
                        if ($val instanceof \Hoathis\Lua\Model\ReturnedValue) {
                            return $val;            // there is a return in the while
                        }
                    }
                    $condition = $children[$conditionPos]->accept($this, $handle, $eldnah);
                }
                break;

            case '#for_loop':
                $nbchildren = count($children);
                $varName = $children[0]->accept($this, $handle, $eldnah);
                $firstVal = $children[1]->accept($this, $handle, $eldnah)->getValue();
                $lastVal = $children[2]->accept($this, $handle, $eldnah)->getValue();
                if ($nbchildren === 5) {
                    $step = $children[3]->accept($this, $handle, $eldnah)->getValue();
                    $code = $children[4];
                } else {
                    $step = 1;
                    $code = $children[3];
                }
                for ($i = $firstVal; $i <= $lastVal; $i += $step) {
                    $this->setValueToSymbol($varName, $i, true);
                    $stmtValue = $code->accept($this, $handle, $eldnah);
                    if ($stmtValue instanceof \Hoathis\Lua\Model\BreakStatement) {
                        break;
                    } elseif ($stmtValue instanceof \Hoathis\Lua\Model\ReturnedValue) {
                        return $stmtValue;
                    }
                }
                break;

            case '#for_in_loop':
                $oldEnvironment = $this->_environment;
                $this->_environment = new \Hoathis\Lua\Model\Environment('block', $this->_environment);
                $forVars = $children[0]->accept($this, $handle, self::AS_SYMBOL);
                $iterator = $children[1]->accept($this, $handle, self::AS_VALUE);
                $forBlock = $children[2];
                if ($iterator instanceof \Hoathis\Lua\Model\ValueGroup) {
                    $iteratorValues = $iterator->getValue();
                    if ( !($iteratorValues[0]->getValue() instanceof \Hoathis\Lua\Model\Closure) ) {
                        throw new \Hoathis\Lua\Exception\Interpreter(
                            'Invalid first value in for in loop', 1);
                    }
                    $iteratorFunction = $iteratorValues[0]->getValue();
                    // TODO maybe add more controls about iteratorSubject type, etc.
                    $iteratorSubject = $iteratorValues[1];
                    if (true === isset($iteratorValues[2])) {
                        $iteratorStart = $iteratorValues[2];
                    } else {
                        $iteratorStart = new \Hoathis\Lua\Model\Value(null);
                    }
                    $args = array($iteratorSubject,$iteratorStart);
                    //$oldForEnvironment = $this->_environment;
                    //$this->_environment = $iteratorFunction;
                    $vals = $iteratorFunction->call($args, $this);
                    //$this->_environment = $oldForEnvironment;
                    if ( !($vals instanceof \Hoathis\Lua\Model\ValueGroup) ) {
                        throw new \Hoathis\Lua\Exception\Interpreter(
                            'Invalid iterator that does\'nt return two values', 1);
                    }
                    while (false === is_null($vals[0])) {
                        $this->setValueGroupToValueGroup($forVars, $vals, true);
                        $blockVal = $forBlock->accept($this,$handle,self::AS_VALUE);
                        if ($blockVal instanceof \Hoathis\Lua\Model\ReturnedValue) {
                            $this->_environment = $oldEnvironment;
                            return $blockVal;
                        } elseif ($blockVal instanceof \Hoathis\Lua\Model\BreakStatement) {
                            break;
                        }
                        $args = array($iteratorSubject,$vals[0]);
                        //$oldForEnvironment = $this->_environment;
                        //$this->_environment = $iteratorFunction;
                        $vals = $iteratorFunction->call($args, $this);
                        //$this->_environment = $oldForEnvironment;
                        if (false === is_null($vals)            // null is the value of a non returning function
                             && !($vals instanceof \Hoathis\Lua\Model\ValueGroup) ) {
                            throw new \Hoathis\Lua\Exception\Interpreter(
                                'Invalid iterator that does\'nt return two values', 1);
                        }
                    }
                }
                $this->_environment = $oldEnvironment;
                break;

            case '#break':
                return new \Hoathis\Lua\Model\BreakStatement(null);
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

    /**
     *
     */
    public function setRoot (\Hoathis\Lua\Model\Environment $env ) {

        $this->_environment = $env;
    }

    public function setValueToSymbol($symbol, $value, $local = false) {
        if (true === $local && false === $this->_environment->localExists($symbol)) {
            $this->_environment->localSet($symbol, new \Hoathis\Lua\Model\Variable(
                $symbol,
                $this->_environment
            ));
        } elseif(false === isset($this->_environment[$symbol])) {
            $this->_environment[$symbol] = new \Hoathis\Lua\Model\Variable(
                $symbol,
                $this->_environment
            );
        }
        if ($value instanceof \Hoathis\Lua\Model\Value) {
            if ($value->isReference()) {
                //$value->copyAsReferenceTo($this->_environment[$symbol]);
                $this->_environment[$symbol]->setValue($value);
            } else {
                $this->_environment[$symbol]->setValue($value);
            }
        } else {
            $this->_environment[$symbol]->setValue(new \Hoathis\Lua\Model\Value($value));
        }
    }

    public function setValueGroupToValueGroup($psymbols, $pvalues, $local = false) {
        if ($psymbols instanceof \Hoathis\Lua\Model\ValueGroup) {
            $symbols = $psymbols->getValue();
        } else {
            $symbols = $psymbols;
        }
        if ($pvalues instanceof \Hoathis\Lua\Model\ValueGroup) {
            $values = $pvalues->getValue();
        } else {
            $values = $pvalues;
        }
        $nbSymbols = count($symbols);
        $nbValues = count($values);
        for ($i = 0; $i < $nbSymbols; $i++) {
            $symbol = $symbols[$i];
            if ($i < $nbValues) {
                $value = $values[$i];
            } else {
                $value = new \Hoathis\Lua\Model\Value(null);
            }
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
                $this->setValueToSymbol($symbol, $value, $local);
            }
        }
    }
}

}

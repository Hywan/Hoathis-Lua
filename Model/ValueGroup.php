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

from('Hoathis')

/**
 * \Hoathis\Lua\Exception\Model
 */
-> import('Lua.Exception.Model')

/**
 * \Hoathis\Lua\Model\Environment
 */
-> import('Lua.Model.Environment');

}

namespace Hoathis\Lua\Model {

/**
 * Class \Hoathis\Lua\Model\Closure.
 *
 * Closure.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2013 Ivan Enderlin.
 * @license    New BSD License
 */

class ValueGroup extends Value implements \ArrayAccess {


    public function __construct($value, $referenceType = self::SCALAR) {
        parent::__construct($value, $referenceType);
        $this->_referenceType = self::SCALAR;
        $this->_value = array();
    }

    public function addValue($value) {
        $this->_value[] = $value;
    }

    public function offsetExists($offset) {
        return array_key_exists($offset, $this->_value);
    }

    public function offsetGet($offset) {
        return $this->_value[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->_value[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->_value[$offset]);
    }

    public function getPHPValue() {
        $value = $this->getValue();
        $result = array();
        foreach ($value as $key => $val) {
            $result[$key] = $val->getPHPValue();
        }
        return $result;
    }

}

}
